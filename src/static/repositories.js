let isUpdatingRepositories = false;
let isFetchingRepositories = false;
let isUpdatingFilters = false;
let retryCount = 0;
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;

const COLLAPSE_STATE_KEY = 'repository_groups_collapse_state';

/** Debounce timer for filterRepositories. */
let filterTimeout = null;

/** Serialised snapshot of the last successfully rendered payload. */
let previousDataHash = null;

// ─── Repository row builder ────────────────────────────────────────────────

/**
 * Creates a table row element representing a repository.
 */
function createRepositoryRow(repo) {
    const row = document.createElement('tr');
    row.className = 'repository-row';
    row.dataset.organization = repo.organization || 'Unknown';
    row.dataset.language     = repo.language || '';
    row.dataset.visibility   = repo.visibility || '';
    row.dataset.fork         = repo.fork ? 'true' : 'false';
    row.dataset.hasForks     = repo.forks > 0 ? 'true' : 'false';
    row.dataset.hasIssues    = repo.issues > 0 ? 'true' : 'false';
    row.dataset.repoUrl      = repo.url || '';
    row.dataset.itemHash     = JSON.stringify(repo);

    const slash = repo.visibility === 'private' ? '-slash status-uninstalled' : ' status-installed';

    row.innerHTML = `
        <td><a href="${escapeHtml(repo.url || '#')}" target="_blank" rel="noopener noreferrer">${escapeHtml(repo.name)}</a></td>
        <td><i class="fas fa-star status-suspended"></i> ${repo.stars}</td>
        <td>${repo.fork ? '<i class="fas fa-circle-check status-installed"></i> Yes' : '<i class="fas fa-circle-xmark status-uninstalled"></i> No'}</td>
        <td><i class="fas fa-code-branch"></i> ${repo.forks}</td>
        <td><i class="fas fa-circle-exclamation"></i> ${repo.issues}</td>
        <td><span class="badge bg-primary">${escapeHtml(repo.language || '-')}</span></td>
        <td><i class="fas fa-eye${slash}"></i> ${escapeHtml(repo.visibility)}</td>
    `;

    return row;
}

// ─── Organization-group builders ────────────────────────────────────────────

/**
 * Groups repositories by organization and sorts each group by name.
 */
function groupRepositoriesByOrganization(items) {
    const grouped = {};

    items.forEach(item => {
        const organization = item?.organization || 'Unknown';
        if (!grouped[organization]) grouped[organization] = [];
        grouped[organization].push(item);
    });

    Object.keys(grouped).forEach(organization => {
        grouped[organization].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    });

    return grouped;
}

/**
 * Builds a complete organization-group card element (header + collapsible repository table).
 *
 * @param {string}   organization   - Organization display name.
 * @param {Array}    repositories   - Sorted repositories for this organization.
 * @param {string}   groupId        - Unique DOM id for the collapse target.
 * @param {boolean}  startCollapsed - Whether the group should begin collapsed.
 * @returns {HTMLElement}
 */
function createOrganizationGroup(organization, repositories, groupId, startCollapsed) {
    const orgDiv = document.createElement('div');
    orgDiv.className = 'mb-4 card';
    orgDiv.dataset.organization = organization;
    orgDiv.dataset.groupHash = hashRepositories(repositories);

    // Header
    const header = document.createElement('div');
    header.className = 'card-header bg-light';
    orgDiv.appendChild(header);

    const btn = document.createElement('button');
    btn.className = 'btn btn-link text-decoration-none p-0 fw-bold text-start w-100 d-flex justify-content-between align-items-center';
    btn.type = 'button';
    btn.setAttribute('data-bs-toggle', 'collapse');
    btn.setAttribute('data-bs-target', `#${groupId}`);
    btn.setAttribute('aria-expanded', String(!startCollapsed));
    btn.setAttribute('aria-controls', groupId);

    const orgText = document.createElement('span');
    orgText.textContent = organization;
    btn.appendChild(orgText);

    const badgeAndChevron = document.createElement('div');
    badgeAndChevron.className = 'd-flex align-items-center';

    const countBadge = document.createElement('span');
    countBadge.className = 'badge bg-primary me-2';
    countBadge.title = 'Total Repositories';
    countBadge.textContent = repositories.length;
    badgeAndChevron.appendChild(countBadge);

    const chevron = document.createElement('i');
    chevron.className = 'fas fa-chevron-down';
    if (startCollapsed) chevron.classList.add('chevron-collapsed');
    badgeAndChevron.appendChild(chevron);

    btn.appendChild(badgeAndChevron);
    header.appendChild(btn);

    // Repository table (collapsible)
    const collapseDiv = document.createElement('div');
    collapseDiv.className = `collapse${startCollapsed ? '' : ' show'}`;
    collapseDiv.id = groupId;

    const table = document.createElement('table');
    table.className = 'table table-striped mb-0';
    table.innerHTML = `
        <thead>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">Stars</th>
                <th scope="col">Fork</th>
                <th scope="col">Forks</th>
                <th scope="col">Open Issues</th>
                <th scope="col">Languages</th>
                <th scope="col">Visibility</th>
            </tr>
        </thead>
    `;

    const tbody = document.createElement('tbody');
    repositories.forEach(repo => tbody.appendChild(createRepositoryRow(repo)));
    table.appendChild(tbody);

    collapseDiv.appendChild(table);
    orgDiv.appendChild(collapseDiv);

    return orgDiv;
}

/**
 * Returns a stable JSON string for a repository array, used as a cheap
 * change-detection hash. Repositories are sorted by URL so order-only changes
 * don't trigger a full re-render.
 */
function hashRepositories(repositories) {
    const sorted = [...repositories].sort((a, b) => (a.url || '').localeCompare(b.url || ''));
    return JSON.stringify(sorted);
}

/**
 * Surgically updates an existing organization-group card if its repository
 * data has changed. The collapse state of the group is left undisturbed.
 *
 * @param {HTMLElement} orgDiv       - Existing card element in the DOM.
 * @param {Array}       repositories - Fresh sorted repositories for this organization.
 */
function updateOrganizationGroup(orgDiv, repositories) {
    const newHash = hashRepositories(repositories);
    if (orgDiv.dataset.groupHash === newHash) return; // nothing changed
    orgDiv.dataset.groupHash = newHash;

    // Update header count badge
    const countBadge = orgDiv.querySelector('.card-header .badge');
    if (countBadge) countBadge.textContent = repositories.length;

    const tbody = orgDiv.querySelector('table tbody');
    if (!tbody) return;

    // Index existing rows by URL
    const existingRows = new Map();
    tbody.querySelectorAll('tr[data-repo-url]').forEach(row => {
        existingRows.set(row.dataset.repoUrl, row);
    });

    const newUrls = new Set(repositories.map(repo => repo.url || ''));

    // Remove rows no longer present
    existingRows.forEach((row, url) => {
        if (!newUrls.has(url)) row.remove();
    });

    // Insert / update / reorder rows
    repositories.forEach((repo, index) => {
        const url    = repo.url || '';
        const newRow = createRepositoryRow(repo);

        if (existingRows.has(url)) {
            const old = existingRows.get(url);
            if (old.dataset.itemHash !== newRow.dataset.itemHash) {
                tbody.replaceChild(newRow, old);
                existingRows.set(url, newRow);
            }
            // Ensure correct position
            const currentIndex = [...tbody.children].indexOf(existingRows.get(url));
            if (currentIndex !== index) {
                tbody.insertBefore(existingRows.get(url), tbody.children[index] || null);
            }
        } else {
            tbody.insertBefore(newRow, tbody.children[index] || null);
            existingRows.set(url, newRow);
        }
    });
}

// ─── Main render ─────────────────────────────────────────────────────────────

/**
 * Populates (or incrementally updates) the UI with repositories grouped by
 * organization. Re-renders only what has actually changed; collapse states
 * are preserved.
 *
 * @param {Array} repositories - Array of repository objects from the API.
 */
function populateRepositoriesGroupedByOrganization(repositories) {
    if (isUpdatingRepositories) {
        console.log('Update already in progress, skipping...');
        return;
    }

    isUpdatingRepositories = true;

    try {
        hideLoadingIndicator();

        if (!Array.isArray(repositories)) {
            throw new Error('Invalid repository data format');
        }

        // ── Early-exit if nothing changed ──────────────────────────────────
        const newHash = hashRepositories(repositories);
        if (newHash === previousDataHash) {
            console.log('Repository data unchanged – skipping re-render.');
            debouncedFilterRepositories();
            return;
        }
        previousDataHash = newHash;

        // ── Verify required DOM nodes ──────────────────────────────────────
        const counterContainer = document.getElementById('repositoriesCount');
        const groupedContainer = document.getElementById('groupedRepositories');

        if (!counterContainer) throw new Error('Counter container element not found');
        if (!groupedContainer) throw new Error('Grouped repositories container element not found');

        // ── Empty-state ────────────────────────────────────────────────────
        if (repositories.length === 0) {
            groupedContainer.innerHTML = `
                <div class="list-group-item list-group-item-light text-center py-5">
                    <i class="fas fa-folder-open text-muted fa-3x mb-3"></i>
                    <h5 class="text-muted">No repositories found!</h5>
                </div>`;
            counterContainer.textContent = '0';
            return;
        }

        counterContainer.textContent = repositories.length;

        // ── Group ──────────────────────────────────────────────────────────
        const groupedData = groupRepositoriesByOrganization(repositories);

        // ── Read persisted collapse state ──────────────────────────────────
        const collapseState = getCollapseState(COLLAPSE_STATE_KEY);

        // ── Build index of existing organization cards ─────────────────────
        const existingCards = new Map();
        groupedContainer.querySelectorAll('[data-organization]').forEach(el => {
            existingCards.set(el.dataset.organization, el);
        });

        const sortedOrganizations = Object.keys(groupedData).sort();
        const activeOrganizations = new Set(sortedOrganizations);

        // Remove cards for organizations no longer in the data
        existingCards.forEach((el, organization) => {
            if (!activeOrganizations.has(organization)) el.remove();
        });

        // Insert / update / reorder cards
        sortedOrganizations.forEach((organization, index) => {
            const repos   = groupedData[organization];
            const groupId = `group-${organization.replace(/[^a-zA-Z0-9]+/g, '-')}`;

            if (existingCards.has(organization)) {
                // Update existing card in-place (preserves Bootstrap collapse state)
                const card = existingCards.get(organization);
                updateOrganizationGroup(card, repos);

                // Ensure alphabetical ordering in the DOM
                const currentPos = [...groupedContainer.children].indexOf(card);
                if (currentPos !== index) {
                    groupedContainer.insertBefore(card, groupedContainer.children[index] || null);
                }
            } else {
                // Create a brand-new card, restoring persisted collapse state
                const isCollapsed = collapseState.has(groupId);
                const card = createOrganizationGroup(organization, repos, groupId, isCollapsed);
                groupedContainer.insertBefore(card, groupedContainer.children[index] || null);
                existingCards.set(organization, card);
            }
        });

        retryCount = 0;

        if (!isUpdatingFilters) {
            debouncedFilterRepositories();
        }
    } catch (error) {
        console.error('Error populating repositories:', error);
        showErrorAlert(`Failed to display repositories: ${error.message}`);

        const groupedContainer = document.getElementById('groupedRepositories');
        if (groupedContainer) {
            groupedContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> Failed to load repositories. Please refresh the page.
                </div>`;
        }
    } finally {
        isUpdatingRepositories = false;
    }
}

// ---------------------------------------------------------------------------
// Filter dropdowns
// ---------------------------------------------------------------------------

/**
 * Updates the language filter dropdown, preserving any current selection.
 *
 * @param {Array} repositories
 */
function updateLanguageFilter(repositories) {
    const languageFilter = document.getElementById('languageFilter');
    if (!languageFilter) return;

    const existingValue = languageFilter.value;
    const languages = Array.from(new Set(repositories.map(repo => repo.language)))
        .filter(Boolean)
        .sort();

    languageFilter.innerHTML = '<option value="">All Languages</option>';
    languages.forEach(language => {
        const option = document.createElement('option');
        option.value = language;
        option.textContent = language;
        if (language === existingValue) {
            option.selected = true;
        }
        languageFilter.appendChild(option);
    });
}

// ---------------------------------------------------------------------------
// Filtering
// ---------------------------------------------------------------------------

/**
 * Filters repository rows against the current UI selections, hides
 * organization groups that end up with no visible rows, and syncs the URL
 * query string.
 */
function filterRepositories() {
    if (isUpdatingFilters) return;

    isUpdatingFilters = true;

    try {
        const language   = document.getElementById('languageFilter')?.value     || '';
        const visibility = document.getElementById('visibilityFilter')?.value   || '';
        const isFork     = document.getElementById('isForkFilter')?.checked     || false;
        const hasForks   = document.getElementById('hasForkFilter')?.checked    || false;
        const hasIssues  = document.getElementById('hasIssuesFilter')?.checked  || false;

        updateURLParameters(language, visibility, isFork, hasForks, hasIssues);

        let counter = 0;
        document.querySelectorAll('#groupedRepositories [data-organization]').forEach(group => {
            let visibleInGroup = 0;

            group.querySelectorAll('.repository-row').forEach(row => {
                const matches =
                    (!language     || row.dataset.language     === language)     &&
                    (!visibility   || row.dataset.visibility   === visibility)   &&
                    (!isFork       || row.dataset.fork         === 'true')       &&
                    (!hasForks     || row.dataset.hasForks     === 'true')       &&
                    (!hasIssues    || row.dataset.hasIssues    === 'true');

                row.style.display = matches ? '' : 'none';
                if (matches) {
                    visibleInGroup++;
                    counter++;
                }
            });

            group.style.display = visibleInGroup > 0 ? '' : 'none';
        });

        const countElement = document.getElementById('repositoriesCount');
        if (countElement) {
            countElement.textContent = counter;
        }
    } finally {
        isUpdatingFilters = false;
    }
}

/**
 * Debounces calls to filterRepositories to avoid redundant work during rapid
 * state changes (e.g. dropdown open/close, bulk filter resets).
 */
function debouncedFilterRepositories() {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }
    filterTimeout = setTimeout(filterRepositories, 100);
}

// ---------------------------------------------------------------------------
// URL ↔ filter sync
// ---------------------------------------------------------------------------

/**
 * Reflects active filter state into the browser's URL without a page reload.
 *
 * @param {string}  language
 * @param {string}  visibility
 * @param {boolean} isFork
 * @param {boolean} hasForks
 * @param {boolean} hasIssues
 */
function updateURLParameters(language, visibility, isFork, hasForks, hasIssues) {
    try {
        const queryString = new URLSearchParams(window.location.search);

        const params = { language, visibility };
        for (const [key, value] of Object.entries(params)) {
            if (value) queryString.set(key, value);
            else queryString.delete(key);
        }

        if (isFork)    queryString.set('isFork', 'true');    else queryString.delete('isFork');
        if (hasForks)  queryString.set('hasForks', 'true');  else queryString.delete('hasForks');
        if (hasIssues) queryString.set('hasIssues', 'true'); else queryString.delete('hasIssues');

        const newURL = queryString.toString()
            ? '?' + queryString.toString()
            : window.location.pathname;
        window.history.replaceState({}, '', newURL);
    } catch (error) {
        console.error('Error updating URL parameters:', error);
    }
}

/**
 * Restores all filter controls from the current URL query string.
 * Called once on page load, before the first data fetch.
 * NOTE: The language option elements are not yet populated at this point —
 * their values are re-applied by updateLanguageFilter once the fetch
 * completes and it preserves `existingValue`.
 */
function loadFiltersFromURL() {
    const params = new URLSearchParams(window.location.search);

    const textFilters = ['language', 'visibility'];
    textFilters.forEach(key => {
        const el = document.getElementById(`${key}Filter`);
        if (el && params.has(key)) {
            el.value = params.get(key);
        }
    });

    const checkboxFilters = [
        { id: 'isForkFilter',    param: 'isFork'    },
        { id: 'hasForkFilter',   param: 'hasForks'  },
        { id: 'hasIssuesFilter', param: 'hasIssues' },
    ];
    checkboxFilters.forEach(({ id, param }) => {
        const el = document.getElementById(id);
        if (el) el.checked = params.get(param) === 'true';
    });
}

// ---------------------------------------------------------------------------
// Reset
// ---------------------------------------------------------------------------

/**
 * Resets all filter controls to their default state and re-runs the filter.
 */
function resetFilters() {
    if (isUpdatingFilters) return;

    const selects    = ['languageFilter', 'visibilityFilter'];
    const checkboxes = ['isForkFilter', 'hasForkFilter', 'hasIssuesFilter'];

    selects.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    checkboxes.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });

    debouncedFilterRepositories();
}

// ---------------------------------------------------------------------------
// Event wiring
// ---------------------------------------------------------------------------

/**
 * Attaches change listeners to every filter control and the reset button.
 */
function setupEventListeners() {
    const filterIds = [
        'languageFilter',
        'visibilityFilter',
        'isForkFilter',
        'hasForkFilter',
        'hasIssuesFilter',
    ];

    filterIds.forEach(id => {
        document.getElementById(id)?.addEventListener('change', debouncedFilterRepositories);
    });

    document.getElementById('resetFilters')?.addEventListener('click', resetFilters);
}

// ─── Data loading ────────────────────────────────────────────────────────────

const polling = createPollingLifecycle(loadData, 60000);

/**
 * Fetches repository data from the API, updates the UI, and schedules the
 * next refresh. Retries up to MAX_RETRIES times with exponential back-off on
 * failure.
 */
function loadData() {
    if (isFetchingRepositories) {
        console.log('Fetch already in progress, skipping...');
        return;
    }

    polling.clearPending();

    if (document.hidden) {
        polling.scheduleNextLoad();
        return;
    }

    isFetchingRepositories = true;
    showLoadingIndicator('groupedRepositories', 'Loading repositories...');

    fetch('/api/v1/repositories')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') throw new Error('Invalid response format');

            const repositories = data.repositories || [];
            populateRepositoriesGroupedByOrganization(repositories);
            updateLanguageFilter(repositories);
            polling.scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading data:', error);
            hideLoadingIndicator();

            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;

            showErrorAlert(`Failed to load repository data: ${error.message}`, isRetriable);

            if (isRetriable) {
                polling.scheduleRetry(RETRY_DELAY * Math.pow(2, retryCount - 1));
            } else {
                retryCount = 0;
                polling.scheduleNextLoad();
            }
        })
        .finally(() => {
            isFetchingRepositories = false;
        });
}

/**
 * Entry point – validates required DOM, wires events, and kicks off the first
 * data load.
 */
function initialize() {
    console.log('Initializing Repositories Management Script...');

    const required        = ['repositoriesCount', 'groupedRepositories'];
    const missingElements = required.filter(id => !document.getElementById(id));

    if (missingElements.length > 0) {
        console.error('Missing required DOM elements:', missingElements);
        showErrorAlert('Page not properly initialized. Please refresh the page.');
        return;
    }

    setupEventListeners();
    loadFiltersFromURL();
    polling.initializeEventListeners();
    initCollapseTracking('groupedRepositories', COLLAPSE_STATE_KEY);
    loadData();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
} else {
    initialize();
}
