let isUpdatingPRs = false;
let isFetchingPRs = false;
let loadDataTimeout = null;
let retryCount = 0;
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;

const STATE_ORDER = ['success', 'failure', 'pending', 'error', 'skipped', ''];
const MERGEABLE_STATES = {
    CLEAN: 'clean',
    UNSTABLE: 'unstable',
    BLOCKED: 'blocked',
    BEHIND: 'behind',
    DIRTY: 'dirty'
};

// ─── Data diffing ────────────────────────────────────────────────────────────

/** Serialised snapshot of the last successfully rendered payload. */
let previousDataHash = null;

/**
 * Returns a stable JSON string for a PR array, used as a cheap change-detection
 * hash.  Items are sorted by URL so order-only changes in the API response
 * don't trigger a full re-render.
 */
function hashItems(items) {
    const sorted = [...items].sort((a, b) => (a.url || '').localeCompare(b.url || ''));
    return JSON.stringify(sorted);
}

// ─── Collapse-state persistence ──────────────────────────────────────────────

const COLLAPSE_STATE_KEY = 'pr_groups_collapse_state';

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
 * Wires Bootstrap collapse events on the grouped container so that every
 * open/close action is immediately written to localStorage.  Safe to call
 * multiple times – uses a single delegated listener on the container.
 */
function initCollapseTracking() {
    const container = document.getElementById('groupedPullRequests');
    if (!container || container._collapseTrackingInit) return;
    container._collapseTrackingInit = true;

    container.addEventListener('hide.bs.collapse', e => {
        const id = e.target.id;
        if (!id) return;
        const state = getCollapseState();
        state.add(id);
        saveCollapseState(state);
        // Rotate chevron
        const btn = container.querySelector(`[data-bs-target="#${id}"] .fa-chevron-down`);
        if (btn) btn.classList.add('chevron-collapsed');
    });

    container.addEventListener('show.bs.collapse', e => {
        const id = e.target.id;
        if (!id) return;
        const state = getCollapseState();
        state.delete(id);
        saveCollapseState(state);
        // Restore chevron
        const btn = container.querySelector(`[data-bs-target="#${id}"] .fa-chevron-down`);
        if (btn) btn.classList.remove('chevron-collapsed');
    });
}

// ─── Utility helpers (unchanged) ─────────────────────────────────────────────

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
 * Displays a loading indicator in the grouped pull requests container.
 */
function showLoadingIndicator() {
    const groupedContainer = document.getElementById("groupedPullRequests");
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
        <p class="mt-2 text-muted">Loading pull requests...</p>
    `;

    groupedContainer.innerHTML = '';
    groupedContainer.appendChild(loadingDiv);
}

/**
 * Hides the loading indicator by removing it from the DOM.
 */
function hideLoadingIndicator() {
    const loadingIndicator = document.getElementById('loading-indicator');
    if (loadingIndicator) loadingIndicator.remove();
}

/**
 * Validates an array of PR data items.
 */
function validatePRData(items) {
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
 * Returns HTML badge markup based on CI state.
 */
function getStateBadge(state) {
    const badges = {
        success: { class: 'bg-success',              icon: 'fa-check-circle',       text: 'CI Success' },
        failure: { class: 'bg-danger',               icon: 'fa-times-circle',       text: 'CI Failure' },
        pending: { class: 'bg-warning text-dark',    icon: 'fa-hourglass-half',     text: 'CI Pending' },
        error:   { class: 'bg-danger',               icon: 'fa-exclamation-triangle', text: 'CI Error' },
        skipped: { class: 'bg-dark',                 icon: 'fa-arrow-circle-right', text: 'CI Skipped' },
    };

    const badge = badges[state] || { class: 'bg-secondary', icon: 'fa-question-circle', text: 'CI Unknown' };

    return `<span class="badge ${badge.class} me-1 mb-1" title="Continuous Integration Status">
        <i class="fas ${badge.icon} me-1"></i>${badge.text}
    </span>`;
}

/**
 * Generates a badge indicating the merge status of a pull request.
 */
function getMergeableBadge(mergeable, mergeable_state) {
    if (mergeable === null || mergeable === undefined) {
        return `<span class="badge bg-secondary me-1 mb-1" title="Merge Status Unknown">
            <i class="fas fa-question-circle me-1"></i>Unknown
        </span>`;
    }

    if (mergeable === false) {
        return `<span class="badge bg-danger me-1 mb-1" title="Pull Request Cannot Be Merged">
            <i class="fas fa-times-circle me-1"></i>Not Mergeable
        </span>`;
    }

    const stateMap = {
        [MERGEABLE_STATES.CLEAN]:    { class: 'bg-success',          icon: 'fa-check-circle',      text: 'Mergeable', title: 'Ready to merge - no conflicts' },
        [MERGEABLE_STATES.UNSTABLE]: { class: 'bg-info',             icon: 'fa-info-circle',       text: 'Unstable',  title: 'Mergeable but CI checks failed' },
        [MERGEABLE_STATES.BLOCKED]:  { class: 'bg-warning text-dark', icon: 'fa-lock',             text: 'Blocked',   title: 'Merge blocked by branch protection rules' },
        [MERGEABLE_STATES.BEHIND]:   { class: 'bg-secondary',        icon: 'fa-arrow-circle-down', text: 'Behind',    title: 'Branch is behind the base branch' },
        [MERGEABLE_STATES.DIRTY]:    { class: 'bg-danger',           icon: 'fa-exclamation-circle', text: 'Dirty',    title: 'Merge conflicts detected' },
    };

    const info = stateMap[mergeable_state] || {
        class: 'bg-info',
        icon: 'fa-info-circle',
        text: escapeHtml(mergeable_state || 'Unknown'),
        title: `Merge state: ${mergeable_state || 'Unknown'}`,
    };

    return `<span class="badge ${info.class} me-1 mb-1" title="${info.title}">
        <i class="fas ${info.icon} me-1"></i>${info.text}
    </span>`;
}

/**
 * Determines whether a PR should be highlighted as ready-to-merge.
 */
function isValidPR(pr) {
    if (pr.is_valid_pr !== undefined) return pr.is_valid_pr;

    return (
        pr.state === 'success' &&
        pr.mergeable === true &&
        pr.mergeable_state &&
        [MERGEABLE_STATES.CLEAN, MERGEABLE_STATES.UNSTABLE].includes(pr.mergeable_state)
    );
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
 * Groups and sorts pull requests by owner.
 */
function groupPRsByOwner(items) {
    const grouped = {};

    items.forEach(item => {
        const owner = item?.owner || 'Unknown';
        if (!grouped[owner]) grouped[owner] = [];
        grouped[owner].push(item);
    });

    Object.keys(grouped).forEach(owner => {
        grouped[owner].sort((a, b) => {
            const aValid = isValidPR(a);
            const bValid = isValidPR(b);
            if (aValid !== bValid) return aValid ? -1 : 1;

            const aIdx = STATE_ORDER.indexOf(a.state || '');
            const bIdx = STATE_ORDER.indexOf(b.state || '');
            if (aIdx !== bIdx) return aIdx - bIdx;

            return new Date(b.created_at || 0) - new Date(a.created_at || 0);
        });
    });

    return grouped;
}

// ─── PR list-item builder ─────────────────────────────────────────────────────

/**
 * Creates a list item element representing a pull request.
 */
function createPRListItem(pr) {
    const itemLi = document.createElement('li');
    itemLi.className = 'list-group-item';
    itemLi.dataset.prUrl = pr.url || '';

    if (isValidPR(pr)) {
        itemLi.classList.add('valid-pr', 'border-success');
        itemLi.style.borderLeftWidth = '4px';
    }

    const container = document.createElement('div');
    container.className = 'd-flex justify-content-between align-items-start';
    itemLi.appendChild(container);

    // Left section
    const leftSection = document.createElement('div');
    leftSection.className = 'flex-grow-1 pe-3';
    container.appendChild(leftSection);

    // Title
    const titleDiv = document.createElement('div');
    titleDiv.className = 'mb-2';
    const titleLink = document.createElement('a');
    titleLink.href = escapeHtml(pr.url || '#');
    titleLink.target = '_blank';
    titleLink.rel = 'noopener noreferrer';
    titleLink.className = 'text-decoration-none fw-bold';
    titleLink.textContent = pr.title || 'Untitled Pull Request';
    titleDiv.appendChild(titleLink);
    leftSection.appendChild(titleDiv);

    // Repo
    const repoDiv = document.createElement('div');
    repoDiv.className = 'mb-2';
    const repoSpan = document.createElement('span');
    repoSpan.className = 'text-muted small';
    if (pr.repository && pr.full_name) {
        const repoLink = document.createElement('a');
        repoLink.href = `https://github.com/${escapeHtml(pr.full_name)}`;
        repoLink.target = '_blank';
        repoLink.rel = 'noopener noreferrer';
        repoLink.className = 'text-muted text-decoration-none';
        repoLink.textContent = pr.repository;
        repoSpan.appendChild(repoLink);
    } else {
        repoSpan.textContent = pr.repository || 'Unknown repository';
    }
    repoDiv.appendChild(repoSpan);
    leftSection.appendChild(repoDiv);

    // Date
    const timeDiv = document.createElement('div');
    timeDiv.className = 'mb-2';
    const timeSpan = document.createElement('span');
    timeSpan.className = 'text-muted small';
    timeSpan.innerHTML = `<i class="fas fa-clock me-1"></i>${formatDate(pr.created_at)}`;
    timeDiv.appendChild(timeSpan);
    leftSection.appendChild(timeDiv);

    // Labels
    if (pr.labels?.length) {
        const labelsDiv = document.createElement('div');
        labelsDiv.className = 'mt-2';
        pr.labels.forEach(label => {
            const el = createLabelElement(label);
            if (el) labelsDiv.appendChild(el);
        });
        leftSection.appendChild(labelsDiv);
    }

    // Right section – badges
    const rightSection = document.createElement('div');
    rightSection.className = 'badge-container d-flex flex-column align-items-end';
    container.appendChild(rightSection);

    const stateDiv = document.createElement('div');
    stateDiv.className = 'mb-1';
    stateDiv.innerHTML = getStateBadge(pr.state);
    rightSection.appendChild(stateDiv);

    if (pr.mergeable !== undefined || pr.mergeable_state !== undefined) {
        const mergeDiv = document.createElement('div');
        mergeDiv.innerHTML = getMergeableBadge(pr.mergeable, pr.mergeable_state);
        rightSection.appendChild(mergeDiv);
    }

    return itemLi;
}

// ─── Owner-group builders ─────────────────────────────────────────────────────

/**
 * Builds a complete owner-group card element (header + collapsible PR list).
 *
 * @param {string}   owner          - Owner display name.
 * @param {Array}    pullRequests   - Sorted PRs for this owner.
 * @param {string}   groupId        - Unique DOM id for the collapse target.
 * @param {boolean}  startCollapsed - Whether the group should begin collapsed.
 * @returns {HTMLElement}
 */
function createOwnerGroup(owner, pullRequests, groupId, startCollapsed) {
    const ownerDiv = document.createElement('div');
    ownerDiv.className = 'mb-4 card';
    ownerDiv.dataset.owner = owner;
    ownerDiv.dataset.prHash = hashItems(pullRequests);

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

    const totalBadge = document.createElement('span');
    totalBadge.className = 'badge bg-primary me-1';
    totalBadge.title = 'Total Pull Requests';
    totalBadge.textContent = pullRequests.length;
    badgeAndChevron.appendChild(totalBadge);

    const validCount = pullRequests.filter(isValidPR).length;
    if (validCount > 0) {
        const validBadge = document.createElement('span');
        validBadge.className = 'badge bg-success me-2';
        validBadge.title = 'Ready to Merge';
        validBadge.textContent = validCount;
        badgeAndChevron.appendChild(validBadge);
    }

    const chevron = document.createElement('i');
    chevron.className = 'fas fa-chevron-down';
    if (startCollapsed) chevron.classList.add('chevron-collapsed');
    badgeAndChevron.appendChild(chevron);

    btn.appendChild(badgeAndChevron);
    header.appendChild(btn);

    // PR list (collapsible)
    const list = document.createElement('ul');
    list.className = `list-group list-group-flush collapse${startCollapsed ? '' : ' show'}`;
    list.id = groupId;

    pullRequests.forEach(pr => list.appendChild(createPRListItem(pr)));
    ownerDiv.appendChild(list);

    return ownerDiv;
}

/**
 * Surgically updates an existing owner-group card if its PR data has changed.
 * The collapse state of the group is left entirely undisturbed.
 *
 * @param {HTMLElement} ownerDiv     - Existing card element in the DOM.
 * @param {Array}       pullRequests - Fresh sorted PRs for this owner.
 */
function updateOwnerGroup(ownerDiv, pullRequests) {
    const newHash = hashItems(pullRequests);
    if (ownerDiv.dataset.prHash === newHash) return; // nothing changed
    ownerDiv.dataset.prHash = newHash;

    // Update header counts
    const badges = ownerDiv.querySelectorAll('.card-header .badge');
    if (badges[0]) badges[0].textContent = pullRequests.length;

    const validCount = pullRequests.filter(isValidPR).length;
    if (badges[1]) {
        if (validCount > 0) {
            badges[1].textContent = validCount;
            badges[1].hidden = false;
        } else {
            badges[1].hidden = true;
        }
    }

    // Rebuild only the PR list, preserving the collapse state on the <ul>
    const list = ownerDiv.querySelector('ul.list-group');
    if (!list) return;

    // Diff existing items by URL
    const existingItems = new Map();
    list.querySelectorAll('li[data-pr-url]').forEach(li => {
        existingItems.set(li.dataset.prUrl, li);
    });

    const newUrls = new Set(pullRequests.map(pr => pr.url || ''));

    // Remove items no longer present
    existingItems.forEach((li, url) => {
        if (!newUrls.has(url)) li.remove();
    });

    // Insert / update / reorder items
    pullRequests.forEach((pr, index) => {
        const url = pr.url || '';
        const newItem = createPRListItem(pr);

        if (existingItems.has(url)) {
            // Replace in-place so we only touch changed rows
            const old = existingItems.get(url);
            const oldHash = old.dataset.itemHash;
            const newItemHash = JSON.stringify(pr);
            if (oldHash !== newItemHash) {
                newItem.dataset.itemHash = newItemHash;
                list.replaceChild(newItem, old);
                existingItems.set(url, newItem);
            }
            // Ensure correct position
            const currentIndex = [...list.children].indexOf(existingItems.get(url));
            if (currentIndex !== index) list.insertBefore(existingItems.get(url), list.children[index] || null);
        } else {
            newItem.dataset.itemHash = JSON.stringify(pr);
            list.insertBefore(newItem, list.children[index] || null);
            existingItems.set(url, newItem);
        }
    });
}

// ─── Main render ─────────────────────────────────────────────────────────────

/**
 * Populates (or incrementally updates) the UI with pull requests grouped by owner.
 * Re-renders only what has actually changed; collapse states are preserved.
 *
 * @param {Array} items - Array of pull request objects from the API.
 */
function populateIssuesGroupedByOwner(items) {
    if (isUpdatingPRs) {
        console.log('Update already in progress, skipping...');
        return;
    }

    isUpdatingPRs = true;

    try {
        hideLoadingIndicator();

        if (!validatePRData(items)) {
            throw new Error('Invalid pull request data format');
        }

        // ── Early-exit if nothing changed ──────────────────────────────────
        const newHash = hashItems(items);
        if (newHash === previousDataHash) {
            console.log('PR data unchanged – skipping re-render.');
            return;
        }
        previousDataHash = newHash;

        // ── Verify required DOM nodes ──────────────────────────────────────
        const counterContainer      = document.getElementById('openPullRequestsCount');
        const validCounterContainer = document.getElementById('validPullRequestsCount');
        const groupedContainer      = document.getElementById('groupedPullRequests');

        if (!counterContainer || !validCounterContainer || !groupedContainer) {
            throw new Error('Counter container elements not found');
        }

        // ── Empty-state ────────────────────────────────────────────────────
        if (items.length === 0) {
            groupedContainer.innerHTML = `
                <div class="list-group-item list-group-item-light text-center py-5">
                    <i class="fas fa-code-branch text-success fa-3x mb-3"></i>
                    <h5 class="text-muted">No open pull requests found!</h5>
                    <p class="text-muted">All caught up with your pull requests.</p>
                </div>`;
            counterContainer.textContent      = '0';
            validCounterContainer.textContent = '0';
            return;
        }

        // ── Group & count ──────────────────────────────────────────────────
        const groupedData = groupPRsByOwner(items);

        let validPRCount = 0;
        Object.values(groupedData).forEach(prs => {
            validPRCount += prs.filter(isValidPR).length;
        });

        counterContainer.textContent      = items.length;
        validCounterContainer.textContent = validPRCount;

        // ── Read persisted collapse state ──────────────────────────────────
        const collapseState = getCollapseState();

        // ── Build index of existing owner cards ───────────────────────────
        const existingCards = new Map(); // owner → HTMLElement
        groupedContainer.querySelectorAll('[data-owner]').forEach(el => {
            existingCards.set(el.dataset.owner, el);
        });

        const sortedOwners  = Object.keys(groupedData).sort();
        const activeOwners  = new Set(sortedOwners);

        // Remove cards for owners no longer in the data
        existingCards.forEach((el, owner) => {
            if (!activeOwners.has(owner)) el.remove();
        });

        // Insert / update / reorder cards
        sortedOwners.forEach((owner, index) => {
            const prs     = groupedData[owner];
            const groupId = `group-${owner.replace(/[^a-zA-Z0-9]+/g, '-')}`;

            if (existingCards.has(owner)) {
                // Update existing card in-place (preserves collapse animation state)
                const card = existingCards.get(owner);
                updateOwnerGroup(card, prs);

                // Ensure alphabetical ordering in the DOM
                const currentPos = [...groupedContainer.children].indexOf(card);
                if (currentPos !== index) {
                    groupedContainer.insertBefore(card, groupedContainer.children[index] || null);
                }
            } else {
                // Create a brand-new card, restoring its persisted collapse state
                const isCollapsed = collapseState.has(groupId);
                const card = createOwnerGroup(owner, prs, groupId, isCollapsed);
                groupedContainer.insertBefore(card, groupedContainer.children[index] || null);
                existingCards.set(owner, card);
            }
        });

        retryCount = 0;

    } catch (error) {
        console.error('Error populating pull requests:', error);
        showErrorAlert(`Failed to display pull requests: ${error.message}`);

        const groupedContainer = document.getElementById('groupedPullRequests');
        if (groupedContainer) {
            groupedContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> Failed to load pull requests. Please refresh the page.
                </div>`;
        }
    } finally {
        isUpdatingPRs = false;
    }
}

// ─── Data loading ─────────────────────────────────────────────────────────────

/**
 * Fetches pull request data from the API, updates the UI, and schedules the
 * next refresh.  Retries up to MAX_RETRIES times with exponential back-off on
 * failure.
 */
function loadData() {
    if (isFetchingPRs) {
        console.log('Fetch already in progress, skipping...');
        return;
    }

    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
        loadDataTimeout = null;
    }

    if (document.hidden) {
        scheduleNextLoad();
        return;
    }

    isFetchingPRs = true;
    showLoadingIndicator();

    fetch('/api/v1/pull-requests')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') throw new Error('Invalid response format');

            const pullRequests = data.openPullRequests || data.pullRequests || [];
            populateIssuesGroupedByOwner(pullRequests);
            scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading data:', error);
            hideLoadingIndicator();

            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;

            showErrorAlert(`Failed to load pull requests: ${error.message}`, isRetriable);

            if (isRetriable) {
                loadDataTimeout = setTimeout(loadData, RETRY_DELAY * Math.pow(2, retryCount - 1));
            } else {
                retryCount = 0;
                scheduleNextLoad();
            }
        })
        .finally(() => {
            isFetchingPRs = false;
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
    console.log('Initializing Pull Requests Management Script...');

    const required       = ['openPullRequestsCount', 'validPullRequestsCount', 'groupedPullRequests'];
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
