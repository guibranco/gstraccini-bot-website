let isUpdatingIssues = false;
let isFetchingIssues = false;
let retryCount = 0;
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;

const COLLAPSE_STATE_KEY = 'issue_groups_collapse_state';
const FLAT_GROUP_KEY = '__all__';

/** Serialised snapshot of the last successfully rendered payload. */
let previousDataHash = null;

// ─── Issue list-item builder ──────────────────────────────────────────────────

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

// ─── Owner-group builders ─────────────────────────────────────────────────────

/**
 * Builds a complete owner-group card element (header + collapsible issue list).
 * When `flat` is true, the owner header/collapse chrome is omitted and the
 * issue list is rendered directly.
 *
 * @param {string}   owner          - Owner display name.
 * @param {Array}    issues         - Sorted issues for this owner.
 * @param {string}   groupId        - Unique DOM id for the collapse target.
 * @param {boolean}  startCollapsed - Whether the group should begin collapsed.
 * @param {boolean}  flat           - Whether to render without the owner header.
 * @returns {HTMLElement}
 */
function createOwnerGroup(owner, issues, groupId, startCollapsed, flat = false) {
    const ownerDiv = document.createElement('div');
    ownerDiv.className = 'mb-4 card';
    ownerDiv.dataset.owner   = owner;
    ownerDiv.dataset.prHash  = hashItems(issues);

    if (!flat) {
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
    }

    // Issue list (collapsible unless flat)
    const list = document.createElement('ul');
    list.className = `list-group list-group-flush${flat ? '' : ` collapse${startCollapsed ? '' : ' show'}`}`;
    list.id = groupId;

    issues.forEach(issue => list.appendChild(createIssueListItem(issue)));
    ownerDiv.appendChild(list);

    return ownerDiv;
}

/**
 * Groups issues by owner and sorts each group by creation date descending.
 * When grouping is disabled, every issue is placed into a single flat group.
 */
function groupIssuesByOwner(items, groupingEnabled = true) {
    const grouped = {};

    items.forEach(item => {
        const owner = groupingEnabled ? (item?.owner || 'Unknown') : FLAT_GROUP_KEY;
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

// ─── Main render ──────────────────────────────────────────────────────────────

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

        if (!validateListItems(items)) {
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
        const groupingEnabled = isGroupByOrgEnabled();
        const groupedData = groupIssuesByOwner(items, groupingEnabled);

        // ── Read persisted collapse state ──────────────────────────────────
        const collapseState = getCollapseState(COLLAPSE_STATE_KEY);

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
                const card = createOwnerGroup(owner, issues, groupId, isCollapsed, !groupingEnabled);
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

// ─── Data loading ─────────────────────────────────────────────────────────────

const polling = createPollingLifecycle(loadData, 60000);

/**
 * Fetches issue data from the API, updates the UI, and schedules the next
 * refresh. Retries up to MAX_RETRIES times with exponential back-off on failure.
 */
function loadData() {
    if (isFetchingIssues) {
        console.log('Fetch already in progress, skipping...');
        return;
    }

    polling.clearPending();

    if (document.hidden) {
        polling.scheduleNextLoad();
        return;
    }

    isFetchingIssues = true;
    showLoadingIndicator('groupedIssues', 'Loading issues...');

    fetch('/api/v1/issues')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') throw new Error('Invalid response format');

            const issues = data.openIssues || data.issues || [];
            populateIssuesGroupedByOwner(issues);
            polling.scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading data:', error);
            hideLoadingIndicator();

            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;

            showErrorAlert(`Failed to load issues: ${error.message}`, isRetriable);

            if (isRetriable) {
                polling.scheduleRetry(RETRY_DELAY * Math.pow(2, retryCount - 1));
            } else {
                retryCount = 0;
                polling.scheduleNextLoad();
            }
        })
        .finally(() => {
            isFetchingIssues = false;
        });
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

    polling.initializeEventListeners();
    initCollapseTracking('groupedIssues', COLLAPSE_STATE_KEY);   // start listening for collapse events immediately
    loadData();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
} else {
    initialize();
}
