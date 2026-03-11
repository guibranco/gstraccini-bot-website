/** @type {boolean} Guards against re-entrant filter runs. */
let isUpdatingFilters = false;

/** @type {number|null} Debounce timer for filterRepositories. */
let filterTimeout = null;

/** @type {number|null} Scheduled auto-reload timer. Tracked so it can be cancelled before re-scheduling. */
let reloadTimeout = null;

/** @type {AbortController|null} Controls the in-flight fetch so stale requests can be cancelled. */
let fetchController = null;

// ---------------------------------------------------------------------------
// Data loading
// ---------------------------------------------------------------------------

/**
 * Fetches repository data and updates the UI with filters and a scheduled
 * reload. Any previously scheduled reload or in-flight request is cancelled
 * before a new one starts, preventing timer stacking and race conditions.
 */
function loadData() {
    // Cancel any pending auto-reload so we never have two timers alive at once.
    if (reloadTimeout !== null) {
        clearTimeout(reloadTimeout);
        reloadTimeout = null;
    }

    // Abort the previous fetch if it hasn't finished yet.
    if (fetchController !== null) {
        fetchController.abort();
    }
    fetchController = new AbortController();

    fetch('/api/v1/repositories', { signal: fetchController.signal })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch repositories');
            }
            return response.json();
        })
        .then(data => {
            fetchController = null;

            populateRepositoriesTable(data.repositories);
            updateOrganizationFilter(data.repositories);
            updateLanguageFilter(data.repositories);

            // Schedule the next poll only once, and only when the tab is visible.
            // The visibilitychange handler will call loadData() directly when the
            // tab becomes visible again, so no timer is needed while hidden.
            if (document.visibilityState === 'visible') {
                reloadTimeout = setTimeout(loadData, 60_000);
            }
        })
        .catch(error => {
            fetchController = null;

            // AbortError is expected when we intentionally cancel — not a real error.
            if (error.name === 'AbortError') return;

            console.error('Error:', error);
            showErrorAlert('Failed to load repository data.');

            // Back off and retry so a transient failure doesn't stop polling.
            if (document.visibilityState === 'visible') {
                reloadTimeout = setTimeout(loadData, 60_000);
            }
        });
}

// ---------------------------------------------------------------------------
// Table population
// ---------------------------------------------------------------------------

/**
 * Populates the repositories table with data from an array of repository objects.
 *
 * @param {Array} repositories - Repository objects to render.
 */
function populateRepositoriesTable(repositories) {
    const repositoriesTable = document.getElementById('repositories');
    if (!repositoriesTable) return;

    // Build a document fragment to avoid repeated reflows while inserting rows.
    const fragment = document.createDocumentFragment();

    repositories.forEach(repo => {
        const slash = repo.visibility === 'private' ? '-slash status-uninstalled' : ' status-installed';
        const row = document.createElement('tr');
        row.classList.add('repository-row');
        row.setAttribute('data-organization', repo.organization);
        row.setAttribute('data-language', repo.language || '');
        row.setAttribute('data-visibility', repo.visibility);
        row.setAttribute('data-fork', repo.fork ? 'true' : 'false');
        row.setAttribute('data-has-forks', repo.forks > 0 ? 'true' : 'false');
        row.setAttribute('data-has-issues', repo.issues > 0 ? 'true' : 'false');

        row.innerHTML = `
        <td>${repo.organization}</td>
        <td><a href='${repo.url}' target='_blank'>${repo.name}</a></td>
        <td><i class="fas fa-star status-suspended"></i> ${repo.stars}</td>
        <td>${repo.fork ? '<i class="fas fa-circle-check status-installed"></i> Yes' : '<i class="fas fa-circle-xmark status-uninstalled"></i> No'}</td>
        <td><i class="fas fa-code-branch"></i> ${repo.forks}</td>
        <td><i class="fas fa-circle-exclamation"></i> ${repo.issues}</td>
        <td><span class="badge bg-primary">${repo.language ?? '-'}</span></td>
        <td><i class="fas fa-eye${slash}"></i> ${repo.visibility}</td>
        `;

        fragment.appendChild(row);
    });

    // Single DOM write — replaces all rows at once.
    repositoriesTable.replaceChildren(fragment);

    if (!isUpdatingFilters) {
        debouncedFilterRepositories();
    }
}

// ---------------------------------------------------------------------------
// Filter dropdowns
// ---------------------------------------------------------------------------

/**
 * Updates the organization filter dropdown, preserving any current selection.
 *
 * @param {Array} repositories
 */
function updateOrganizationFilter(repositories) {
    const organizationFilter = document.getElementById('organizationFilter');
    if (!organizationFilter) return;

    const existingValue = organizationFilter.value;
    const organizations = Array.from(new Set(repositories.map(repo => repo.organization)));

    organizationFilter.innerHTML = '<option value="">All Organizations</option>';
    organizations.forEach(organization => {
        const option = document.createElement('option');
        option.value = organization;
        option.textContent = organization;
        if (organization === existingValue) {
            option.selected = true;
        }
        organizationFilter.appendChild(option);
    });
}

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
 * Filters repository rows against the current UI selections, updates row
 * visibility, and syncs the URL query string.
 */
function filterRepositories() {
    if (isUpdatingFilters) return;

    isUpdatingFilters = true;

    try {
        const organization = document.getElementById('organizationFilter')?.value || '';
        const language     = document.getElementById('languageFilter')?.value     || '';
        const visibility   = document.getElementById('visibilityFilter')?.value   || '';
        const isFork       = document.getElementById('isForkFilter')?.checked     || false;
        const hasForks     = document.getElementById('hasForkFilter')?.checked    || false;
        const hasIssues    = document.getElementById('hasIssuesFilter')?.checked  || false;

        updateURLParameters(organization, language, visibility, isFork, hasForks, hasIssues);

        let counter = 0;
        document.querySelectorAll('.repository-row').forEach(row => {
            const matches =
                (!organization || row.dataset.organization === organization) &&
                (!language     || row.dataset.language     === language)     &&
                (!visibility   || row.dataset.visibility   === visibility)   &&
                (!isFork       || row.dataset.fork         === 'true')       &&
                (!hasForks     || row.dataset.hasForks     === 'true')       &&
                (!hasIssues    || row.dataset.hasIssues    === 'true');

            row.style.display = matches ? '' : 'none';
            if (matches) counter++;
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
 * @param {string}  organization
 * @param {string}  language
 * @param {string}  visibility
 * @param {boolean} isFork
 * @param {boolean} hasForks
 * @param {boolean} hasIssues
 */
function updateURLParameters(organization, language, visibility, isFork, hasForks, hasIssues) {
    try {
        const queryString = new URLSearchParams(window.location.search);

        const params = { organization, language, visibility };
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
 * NOTE: Organization and language option elements are not yet populated at
 * this point — their values are re-applied by updateOrganizationFilter /
 * updateLanguageFilter once the fetch completes and they preserve
 * `existingValue`.
 */
function loadFiltersFromURL() {
    const params = new URLSearchParams(window.location.search);

    const textFilters = ['organization', 'language', 'visibility'];
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

    const selects   = ['organizationFilter', 'languageFilter', 'visibilityFilter'];
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
        'organizationFilter',
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

// Restart polling when the tab becomes visible again.  The timer is already
// cleared inside loadData(), so this cannot stack with an existing countdown.
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        loadData();
    }
});

window.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
    loadFiltersFromURL();
    loadData();
});
