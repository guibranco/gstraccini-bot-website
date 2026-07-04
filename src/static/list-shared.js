/**
 * Shared helpers used by issues.js and pull-requests.js: generic list utilities
 * (escaping, formatting, diffing), collapse-state persistence, loading
 * indicators, and the visibility-aware polling lifecycle. Rendering logic that
 * actually differs between issues and pull requests stays in their own files.
 */

/**
 * Escapes HTML characters in a string to prevent XSS attacks.
 */
function escapeHtml(unsafe) {
    if (unsafe === undefined || unsafe === null) return '';
    const div = document.createElement('div');
    div.textContent = String(unsafe);
    return div.innerHTML;
}

/**
 * Formats a given date string into a human-readable relative time.
 */
function formatDate(dateString) {
    if (!dateString) return 'Unknown date';

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid date';

        const diffDays = Math.ceil(Math.abs(new Date() - date) / 864e5);

        if (diffDays === 1) return '1 day ago';
        if (diffDays < 7) return `${diffDays} days ago`;
        if (diffDays < 30) return `${Math.ceil(diffDays / 7)} weeks ago`;
        if (diffDays < 365) return `${Math.ceil(diffDays / 30)} months ago`;

        return date.toLocaleDateString();
    } catch (error) {
        console.error('Error formatting date:', error);
        return 'Invalid date';
    }
}

/**
 * Calculates background and text colours from a hex colour string.
 */
function calculateLabelColors(color) {
    if (!color || typeof color !== 'string') return null;

    color = color.replace(/^#/, '');
    if (!/^[0-9A-Fa-f]{6}$/.test(color)) return null;

    const r = parseInt(color.substr(0, 2), 16);
    const g = parseInt(color.substr(2, 2), 16);
    const b = parseInt(color.substr(4, 2), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    return {
        backgroundColor: `#${color}`,
        textColor: luminance > 0.5 ? '#000' : '#fff',
    };
}

/**
 * Creates a styled label badge element.
 */
function createLabelElement(label) {
    if (!label || !label.name) return null;

    const colors = calculateLabelColors(label.color);
    if (!colors) return null;

    const span = document.createElement('span');
    span.classList.add('badge', 'label-badge', 'me-1', 'mb-1');
    span.style.backgroundColor = colors.backgroundColor;
    span.style.color = colors.textColor;
    span.style.border = '1px solid rgba(0,0,0,0.1)';
    span.setAttribute('title', escapeHtml(label.description || label.name));
    span.textContent = escapeHtml(label.name);

    return span;
}

/**
 * Returns a stable JSON string for an item array, used as a cheap
 * change-detection hash. Items are sorted by URL so order-only changes in the
 * API response don't trigger a full re-render.
 */
function hashItems(items) {
    const sorted = [...items].sort((a, b) => (a.url || '').localeCompare(b.url || ''));
    return JSON.stringify(sorted);
}

/**
 * Validates an array of list items (issues or pull requests).
 */
function validateListItems(items) {
    if (!Array.isArray(items)) {
        console.error('Invalid data format: expected array');
        return false;
    }

    const sampleSize = Math.min(5, items.length);
    for (let i = 0; i < sampleSize; i++) {
        const item = items[i];
        if (!item || typeof item !== 'object') {
            console.error(`Invalid item at index ${i}:`, item);
            return false;
        }

        for (const field of ['title', 'url', 'repository']) {
            if (!item[field]) {
                console.warn(`Missing required field '${field}' in item:`, item);
            }
        }
    }

    return true;
}

/**
 * Displays a loading indicator in the given container, unless it already has
 * content rendered (to avoid a flash).
 */
function showLoadingIndicator(containerId, message) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (container.children.length > 0) return;

    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loading-indicator';
    loadingDiv.className = 'text-center p-4';
    loadingDiv.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">${message}</p>
    `;

    container.innerHTML = '';
    container.appendChild(loadingDiv);
}

/**
 * Hides the loading indicator by removing it from the DOM.
 */
function hideLoadingIndicator() {
    const loadingIndicator = document.getElementById('loading-indicator');
    if (loadingIndicator) loadingIndicator.remove();
}

/**
 * Reads the persisted set of *collapsed* group IDs from localStorage.
 * Returns an empty Set when nothing is stored or the data is corrupt.
 */
function getCollapseState(storageKey) {
    try {
        const raw = localStorage.getItem(storageKey);
        return raw ? new Set(JSON.parse(raw)) : new Set();
    } catch {
        return new Set();
    }
}

/**
 * Persists the provided Set of collapsed group IDs to localStorage.
 *
 * @param {string} storageKey
 * @param {Set<string>} collapsedIds
 */
function saveCollapseState(storageKey, collapsedIds) {
    try {
        localStorage.setItem(storageKey, JSON.stringify([...collapsedIds]));
    } catch (e) {
        console.warn('Could not save collapse state:', e);
    }
}

/**
 * Wires Bootstrap collapse events on the grouped container so that every
 * open/close action is immediately written to localStorage. Safe to call
 * multiple times – uses a single delegated listener on the container.
 */
function initCollapseTracking(containerId, storageKey) {
    const container = document.getElementById(containerId);
    if (!container || container._collapseTrackingInit) return;
    container._collapseTrackingInit = true;

    container.addEventListener('hide.bs.collapse', e => {
        const id = e.target.id;
        if (!id) return;
        const state = getCollapseState(storageKey);
        state.add(id);
        saveCollapseState(storageKey, state);
        const btn = container.querySelector(`[data-bs-target="#${id}"] .fa-chevron-down`);
        if (btn) btn.classList.add('chevron-collapsed');
    });

    container.addEventListener('show.bs.collapse', e => {
        const id = e.target.id;
        if (!id) return;
        const state = getCollapseState(storageKey);
        state.delete(id);
        saveCollapseState(storageKey, state);
        const btn = container.querySelector(`[data-bs-target="#${id}"] .fa-chevron-down`);
        if (btn) btn.classList.remove('chevron-collapsed');
    });
}

/**
 * Creates a visibility-aware polling lifecycle around a `load` function:
 * schedules the next call on an interval, pauses while the tab is hidden,
 * resumes immediately when it becomes visible again, and supports scheduling
 * a one-off retry at a custom delay.
 *
 * @param {() => void} load       - The function to invoke on each poll/retry.
 * @param {number} intervalMs     - Delay between successful polls. Defaults to 60s.
 */
function createPollingLifecycle(load, intervalMs = 60000) {
    let timeoutId = null;

    function clearPending() {
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    }

    function scheduleNextLoad() {
        clearPending();
        if (!document.hidden) timeoutId = setTimeout(load, intervalMs);
    }

    function scheduleRetry(delay) {
        clearPending();
        timeoutId = setTimeout(load, delay);
    }

    function handleVisibilityChange() {
        if (document.hidden) {
            clearPending();
        } else {
            load();
        }
    }

    function initializeEventListeners() {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('beforeunload', clearPending);
        window.addEventListener('pagehide', clearPending);
    }

    return { clearPending, scheduleNextLoad, scheduleRetry, initializeEventListeners };
}
