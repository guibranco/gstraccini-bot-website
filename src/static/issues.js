let isUpdatingIssues = false;
let loadDataTimeout = null;
let retryCount = 0;
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;

/** Serialised snapshot of the last successfully rendered payload. */
let previousDataHash = null;

/**
 * Returns a stable JSON string for an issue array used as a cheap change-detection
 * hash. Items are sorted by URL so order-only changes don't trigger a re-render.
 */
function hashItems(items) {
    const sorted = [...items].sort((a, b) => (a.url || '').localeCompare(b.url || ''));
    return JSON.stringify(sorted);
}

const COLLAPSE_STATE_KEY = 'issue_groups_collapse_state';

/**
 * Reads the persisted set of *collapsed* group IDs from localStorage.
 * Returns an empty Set when nothing is stored or the data is corrupt.
 */
function getCollapseState() {
    try {
        const raw = localStorage.getItem(COLLAPSE_STATE_KEY);
        return raw ? new Set(JSON.parse(raw)) : new Set();
    } catch {
        return new Set();
    }
}

/**
 * Persists the provided Set of collapsed group IDs to localStorage.
 *
 * @param {Set<string>} collapsedIds
 */
function saveCollapseState(collapsedIds) {
    try {
        localStorage.setItem(COLLAPSE_STATE_KEY, JSON.stringify([...collapsedIds]));
    } catch (e) {
        console.warn('Could not save collapse state:', e);
    }
}

/**
 * Wires Bootstrap collapse events on the grouped container so every open/close
 * action is immediately written to localStorage. Safe to call multiple times –
 * uses a single delegated listener on the container.
 */
function initCollapseTracking() {
    const container = document.getElementById('groupedIssues');
    if (!container || container._collapseTrackingInit) return;
    container._collapseTrackingInit = true;

    container.addEventListener('hide.bs.collapse', e => {
        const id = e.target.id;
        if (!id) return;
        const state = getCollapseState();
        state.add(id);
        saveCollapseState(state);
        const btn = container.querySelector(`[data-bs-target="#${id}"] .fa-chevron-down`);
        if (btn) btn.classList.add('chevron-collapsed');
    });

    container.addEventListener('show.bs.collapse', e => {
        const id = e.target.id;
        if (!id) return;
        const state = getCollapseState();
        state.delete(id);
        saveCollapseState(state);
        const btn = container.querySelector(`[data-bs-target="#${id}"] .fa-chevron-down`);
        if (btn) btn.classList.remove('chevron-collapsed');
    });
}

/**
 * Escapes HTML special characters in a given string.
 */
function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Displays a loading indicator in the grouped issues container.
 * Skipped when content is already rendered, to avoid a flash.
 */
function showLoadingIndicator() {
    const groupedContainer = document.getElementById("groupedIssues");
    if (!groupedContainer) return;

    // Don't flash the spinner when we already have content rendered
    if (groupedContainer.children.length > 0) return;

    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loading-indicator';
    loadingDiv.className = 'text-center p-4';
    loadingDiv.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading issues...</p>
    `;

    groupedContainer.innerHTML = '';
    groupedContainer.appendChild(loadingDiv);
}

/**
 * Hides the loading indicator by removing it from the document.
 */
function hideLoadingIndicator() {
    const loadingIndicator = document.getElementById('loading-indicator');
    if (loadingIndicator) loadingIndicator.remove();
}

/**
 * Validates an array of issue data items.
 */
function validateIssueData(items) {
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
 * Formats a date string into a human-readable relative time.
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
 * Groups issues by owner and sorts each group by creation date descending.
 */
function groupIssuesByOwner(items) {
    const grouped = {};

    items.forEach(item => {
        const owner = item?.owner || 'Unknown';
        if (!grouped[owner]) grouped[owner] = [];
        grouped[owner].push(item);
    });

    Object.keys(grouped).forEach(owner => {
        grouped[owner].sort((a, b) =>
            new Date(b.created_at || 0) - new Date(a.created_at || 0)
        );
    });

    return grouped;
}

/**
 * Creates a list item element representing an issue.
 * Sets `data-issue-url` and `data-item-hash` for diffing on future updates.
 */
function createIssueListItem(issue) {
    const itemLi = document.createElement('li');
    itemLi.className = 'list-group-item';
    itemLi.dataset.issueUrl  = issue.url || '';
    itemLi.dataset.itemHash  = JSON.stringify(issue);

    const container = document.createElement('div');
    container.className = 'd-flex justify-content-between align-items-start';
    itemLi.appendChild(container);

    const leftSection = document.createElement('div');
    leftSection.className = 'flex-grow-1';
    container.appendChild(leftSection);

    // Title
    const titleDiv = document.createElement('div');
    titleDiv.className = 'mb-2';
    const titleLink = document.createElement('a');
    titleLink.href = escapeHtml(issue.url || '#');
    titleLink.target = '_blank';
    titleLink.rel = 'noopener noreferrer';
    titleLink.className = 'text-decoration-none fw-bold';
    titleLink.textContent = issue.title || 'Untitled Issue';
    titleDiv.appendChild(titleLink);
    leftSection.appendChild(titleDiv);

    // Repo
    const repoDiv = document.createElement('div');
    repoDiv.className = 'mb-2';
    const repoSpan = document.createElement('span');
    repoSpan.className = 'text-muted small';
    if (issue.repository && issue.full_name) {
        const repoLink = document.createElement('a');
        repoLink.href = `https://github.com/${escapeHtml(issue.full_name)}`;
        repoLink.target = '_blank';
        repoLink.rel = 'noopener noreferrer';
        repoLink.className = 'text-muted text-decoration-none';
        repoLink.textContent = issue.repository;
        repoSpan.appendChild(repoLink);
    } else {
        repoSpan.textContent = issue.repository || 'Unknown repository';
    }
    repoDiv.appendChild(repoSpan);
    leftSection.appendChild(repoDiv);

    // Date
    const timeDiv = document.createElement('div');
    timeDiv.className = 'mb-2';
    const timeSpan = document.createElement('span');
    timeSpan.className = 'text-muted small';
    timeSpan.innerHTML = `<i class="fas fa-clock me-1"></i>${formatDate(issue.created_at)}`;
    timeDiv.appendChild(timeSpan);
    leftSection.appendChild(timeDiv);

    // Labels
    if (issue.labels?.length) {
        const labelsDiv = document.createElement('div');
        labelsDiv.className = 'mt-2';
        issue.labels.forEach(label => {
            const el = createLabelElement(label);
            if (el) labelsDiv.appendChild(el);
        });
        leftSection.appendChild(labelsDiv);
    }

    return itemLi;
}

/**
 * Builds a complete owner-group card element (header + collapsible issue list).
 *
 * @param {string}   owner          - Owner display name.
 * @param {Array}    issues         - Sorted issues for this owner.
 * @param {string}   groupId        - Unique DOM id for the collapse target.
 * @param {boolean}  startCollapsed - Whether the group should begin collapsed.
 * @returns {HTMLElement}
 */
function createOwnerGroup(owner, issues, groupId, startCollapsed) {
    const ownerDiv = document.createElement('div');
    ownerDiv.className = 'mb-4 card';
    ownerDiv.dataset.owner   = owner;
    ownerDiv.dataset.prHash  = hashItems(issues);

    // Header
    const header = document.createElement('div');
    header.className = 'card-header bg-light';
    ownerDiv.appendChild(header);

    const btn = document.createElement('button');
    btn.className = 'btn btn-link text-decoration-none p-0 fw-bold text-start w-100 d-flex justify-content-between align-items-center';
    btn.type = 'button';
    btn.setAttribute('data-bs-toggle', 'collapse');
    btn.setAttribute('data-bs-target', `#${groupId}`);
    btn.setAttribute('aria-expanded', String(!startCollapsed));
    btn.setAttribute('aria-controls', groupId);

    const ownerText = document.createElement('span');
    ownerText.textContent = escapeHtml(owner);
    btn.appendChild(ownerText);

    const badgeAndChevron = document.createElement('div');
    badgeAndChevron.className = 'd-flex align-items-center';

    const countBadge = document.createElement('span');
    countBadge.className = 'badge bg-primary me-2';
    countBadge.title = 'Total Issues';
    countBadge.textContent = issues.length;
    badgeAndChevron.appendChild(countBadge);

    const chevron = document.createElement('i');
    chevron.className = 'fas fa-chevron-down';
    if (startCollapsed) chevron.classList.add('chevron-collapsed');
    badgeAndChevron.appendChild(chevron);

    btn.appendChild(badgeAndChevron);
    header.appendChild(btn);

    // Issue list (collapsible)
    const list = document.createElement('ul');
    list.className = `list-group list-group-flush collapse${startCollapsed ? '' : ' show'}`;
    list.id = groupId;

    issues.forEach(issue => list.appendChild(createIssueListItem(issue)));
    ownerDiv.appendChild(list);

    return ownerDiv;
}

/**
 * Surgically updates an existing owner-group card if its issue data has changed.
 * The collapse state of the group is left entirely undisturbed.
 *
 * @param {HTMLElement} ownerDiv - Existing card element in the DOM.
 * @param {Array}       issues   - Fresh sorted issues for this owner.
 */
function updateOwnerGroup(ownerDiv, issues) {
    const newHash = hashItems(issues);
    if (ownerDiv.dataset.prHash === newHash) return; // nothing changed
    ownerDiv.dataset.prHash = newHash;

    // Update header count badge
    const countBadge = ownerDiv.querySelector('.card-header .badge');
    if (countBadge) countBadge.textContent = issues.length;

    // Rebuild only the issue list, preserving the <ul>'s collapse state
    const list = ownerDiv.querySelector('ul.list-group');
    if (!list) return;

    // Index existing items by URL
    const existingItems = new Map();
    list.querySelectorAll('li[data-issue-url]').forEach(li => {
        existingItems.set(li.dataset.issueUrl, li);
    });

    const newUrls = new Set(issues.map(i => i.url || ''));

    // Remove items no longer present
    existingItems.forEach((li, url) => {
        if (!newUrls.has(url)) li.remove();
    });

    // Insert / update / reorder items
    issues.forEach((issue, index) => {
        const url     = issue.url || '';
        const newItem = createIssueListItem(issue);

        if (existingItems.has(url)) {
            const old = existingItems.get(url);
            if (old.dataset.itemHash !== newItem.dataset.itemHash) {
                list.replaceChild(newItem, old);
                existingItems.set(url, newItem);
            }
            // Ensure correct position
            const currentIndex = [...list.children].indexOf(existingItems.get(url));
            if (currentIndex !== index) {
                list.insertBefore(existingItems.get(url), list.children[index] || null);
            }
        } else {
            list.insertBefore(newItem, list.children[index] || null);
            existingItems.set(url, newItem);
        }
    });
}

/**
 * Populates (or incrementally updates) the UI with issues grouped by owner.
 * Re-renders only what has actually changed; collapse states are preserved.
 *
 * @param {Array} items - Array of issue objects from the API.
 */
function populateIssuesGroupedByOwner(items) {
    if (isUpdatingIssues) {
        console.log('Update already in progress, skipping...');
        return;
    }

    isUpdatingIssues = true;

    try {
        hideLoadingIndicator();

        if (!validateIssueData(items)) {
            throw new Error('Invalid issue data format');
        }

        // ── Early-exit if nothing changed ──────────────────────────────────
        const newHash = hashItems(items);
        if (newHash === previousDataHash) {
            console.log('Issue data unchanged – skipping re-render.');
            return;
        }
        previousDataHash = newHash;

        // ── Verify required DOM nodes ──────────────────────────────────────
        const counterContainer = document.getElementById('openIssuesCount');
        const groupedContainer = document.getElementById('groupedIssues');

        if (!counterContainer) throw new Error('Counter container element not found');
        if (!groupedContainer) throw new Error('Grouped issues container element not found');

        // ── Empty-state ────────────────────────────────────────────────────
        if (items.length === 0) {
            groupedContainer.innerHTML = `
                <div class="list-group-item list-group-item-light text-center py-5">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5 class="text-muted">No open issues found!</h5>
                    <p class="text-muted">Great job keeping up with your issues.</p>
                </div>`;
            counterContainer.textContent = '0';
            return;
        }

        counterContainer.textContent = items.length;

        // ── Group ──────────────────────────────────────────────────────────
        const groupedData = groupIssuesByOwner(items);

        // ── Read persisted collapse state ──────────────────────────────────
        const collapseState = getCollapseState();

        // ── Build index of existing owner cards ───────────────────────────
        const existingCards = new Map();
        groupedContainer.querySelectorAll('[data-owner]').forEach(el => {
            existingCards.set(el.dataset.owner, el);
        });

        const sortedOwners = Object.keys(groupedData).sort();
        const activeOwners = new Set(sortedOwners);

        // Remove cards for owners no longer in the data
        existingCards.forEach((el, owner) => {
            if (!activeOwners.has(owner)) el.remove();
        });

        // Insert / update / reorder cards
        sortedOwners.forEach((owner, index) => {
            const issues  = groupedData[owner];
            const groupId = `group-${owner.replace(/[^a-zA-Z0-9]+/g, '-')}`;

            if (existingCards.has(owner)) {
                // Update existing card in-place (preserves Bootstrap collapse state)
                const card = existingCards.get(owner);
                updateOwnerGroup(card, issues);

                // Ensure alphabetical ordering in the DOM
                const currentPos = [...groupedContainer.children].indexOf(card);
                if (currentPos !== index) {
                    groupedContainer.insertBefore(card, groupedContainer.children[index] || null);
                }
            } else {
                // Create a brand-new card, restoring persisted collapse state
                const isCollapsed = collapseState.has(groupId);
                const card = createOwnerGroup(owner, issues, groupId, isCollapsed);
                groupedContainer.insertBefore(card, groupedContainer.children[index] || null);
                existingCards.set(owner, card);
            }
        });

        retryCount = 0;

    } catch (error) {
        console.error('Error populating issues:', error);
        showErrorAlert(`Failed to display issues: ${error.message}`);

        const groupedContainer = document.getElementById('groupedIssues');
        if (groupedContainer) {
            groupedContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> Failed to load issues. Please refresh the page.
                </div>`;
        }
    } finally {
        isUpdatingIssues = false;
    }
}

/**
 * Fetches issue data from the API, updates the UI, and schedules the next
 * refresh. Retries up to MAX_RETRIES times with exponential back-off on failure.
 */
function loadData() {
    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
        loadDataTimeout = null;
    }

    if (document.hidden) {
        scheduleNextLoad();
        return;
    }

    showLoadingIndicator();

    fetch('/api/v1/issues')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') throw new Error('Invalid response format');

            const issues = data.openIssues || data.issues || [];
            populateIssuesGroupedByOwner(issues);
            scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading data:', error);
            hideLoadingIndicator();

            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;

            showErrorAlert(`Failed to load issues: ${error.message}`, isRetriable);

            if (isRetriable) {
                loadDataTimeout = setTimeout(loadData, RETRY_DELAY * Math.pow(2, retryCount - 1));
            } else {
                retryCount = 0;
                scheduleNextLoad();
            }
        });
}

/**
 * Schedules the next data refresh in 60 s, unless the page is hidden.
 */
function scheduleNextLoad() {
    if (loadDataTimeout) clearTimeout(loadDataTimeout);
    if (!document.hidden) loadDataTimeout = setTimeout(loadData, 60000);
}

/**
 * Pauses polling when the tab is hidden; resumes immediately on visibility.
 */
function handleVisibilityChange() {
    if (document.hidden) {
        if (loadDataTimeout) {
            clearTimeout(loadDataTimeout);
            loadDataTimeout = null;
        }
    } else {
        loadData();
    }
}

/**
 * Clears the polling timer when the page is about to unload.
 */
function handlePageUnload() {
    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
        loadDataTimeout = null;
    }
}

/**
 * Registers all global event listeners.
 */
function initializeEventListeners() {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('beforeunload', handlePageUnload);
    window.addEventListener('pagehide', handlePageUnload);
}

/**
 * Entry point – validates required DOM, wires events, and kicks off the first
 * data load.
 */
function initialize() {
    console.log('Initializing Issues Management Script...');

    const required        = ['openIssuesCount', 'groupedIssues'];
    const missingElements = required.filter(id => !document.getElementById(id));

    if (missingElements.length > 0) {
        console.error('Missing required DOM elements:', missingElements);
        showErrorAlert('Page not properly initialized. Please refresh the page.');
        return;
    }

    initializeEventListeners();
    initCollapseTracking();
    loadData();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
} else {
    initialize();
}
