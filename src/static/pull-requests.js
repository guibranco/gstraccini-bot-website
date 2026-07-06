let isUpdatingPRs = false;
let isFetchingPRs = false;
let retryCount = 0;
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;

const COLLAPSE_STATE_KEY = 'pr_groups_collapse_state';
const FLAT_GROUP_KEY = '__all__';

const STATE_ORDER = ['success', 'failure', 'pending', 'error', 'skipped', ''];
const MERGEABLE_STATES = {
    CLEAN: 'clean',
    UNSTABLE: 'unstable',
    BLOCKED: 'blocked',
    BEHIND: 'behind',
    DIRTY: 'dirty'
};

/** Serialised snapshot of the last successfully rendered payload. */
let previousDataHash = null;

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
 * Groups and sorts pull requests by owner. When grouping is disabled, every
 * pull request is placed into a single flat group.
 */
function groupPRsByOwner(items, groupingEnabled = true) {
    const grouped = {};

    items.forEach(item => {
        const owner = groupingEnabled ? (item?.owner || 'Unknown') : FLAT_GROUP_KEY;
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
 * When `flat` is true, the owner header/collapse chrome is omitted and the PR
 * list is rendered directly.
 *
 * @param {string}   owner          - Owner display name.
 * @param {Array}    pullRequests   - Sorted PRs for this owner.
 * @param {string}   groupId        - Unique DOM id for the collapse target.
 * @param {boolean}  startCollapsed - Whether the group should begin collapsed.
 * @param {boolean}  flat           - Whether to render without the owner header.
 * @returns {HTMLElement}
 */
function createOwnerGroup(owner, pullRequests, groupId, startCollapsed, flat = false) {
    const ownerDiv = document.createElement('div');
    ownerDiv.className = 'mb-4 card';
    ownerDiv.dataset.owner = owner;
    ownerDiv.dataset.prHash = hashItems(pullRequests);

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
    }

    // PR list (collapsible unless flat)
    const list = document.createElement('ul');
    list.className = `list-group list-group-flush${flat ? '' : ` collapse${startCollapsed ? '' : ' show'}`}`;
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

        if (!validateListItems(items)) {
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
        const groupingEnabled = isGroupByOrgEnabled();
        const groupedData = groupPRsByOwner(items, groupingEnabled);

        let validPRCount = 0;
        Object.values(groupedData).forEach(prs => {
            validPRCount += prs.filter(isValidPR).length;
        });

        counterContainer.textContent      = items.length;
        validCounterContainer.textContent = validPRCount;

        // ── Read persisted collapse state ──────────────────────────────────
        const collapseState = getCollapseState(COLLAPSE_STATE_KEY);

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
                const card = createOwnerGroup(owner, prs, groupId, isCollapsed, !groupingEnabled);
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

const polling = createPollingLifecycle(loadData, 60000);

/**
 * Fetches pull request data from the API, updates the UI, and schedules the
 * next refresh. Retries up to MAX_RETRIES times with exponential back-off on
 * failure.
 */
function loadData() {
    if (isFetchingPRs) {
        console.log('Fetch already in progress, skipping...');
        return;
    }

    polling.clearPending();

    if (document.hidden) {
        polling.scheduleNextLoad();
        return;
    }

    isFetchingPRs = true;
    showLoadingIndicator('groupedPullRequests', 'Loading pull requests...');

    fetch('/api/v1/pull-requests')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') throw new Error('Invalid response format');

            const pullRequests = data.openPullRequests || data.pullRequests || [];
            populateIssuesGroupedByOwner(pullRequests);
            polling.scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading data:', error);
            hideLoadingIndicator();

            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;

            showErrorAlert(`Failed to load pull requests: ${error.message}`, isRetriable);

            if (isRetriable) {
                polling.scheduleRetry(RETRY_DELAY * Math.pow(2, retryCount - 1));
            } else {
                retryCount = 0;
                polling.scheduleNextLoad();
            }
        })
        .finally(() => {
            isFetchingPRs = false;
        });
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

    polling.initializeEventListeners();
    initCollapseTracking('groupedPullRequests', COLLAPSE_STATE_KEY);
    loadData();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
} else {
    initialize();
}
