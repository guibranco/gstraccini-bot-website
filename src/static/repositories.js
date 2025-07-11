let isUpdatingFilters = false;
let filterTimeout = null;

/**
 * Fetches repository data and updates the UI with filters and a scheduled reload.
 */
function loadData() {
    fetch('/api/v1/repositories')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch repositories');
            }
            return response.json();
        })
        .then(data => {
            populateRepositoriesTable(data.repositories);
            updateOrganizationFilter(data.repositories);
            updateLanguageFilter(data.repositories);
            if (document.visibilityState === 'visible') {
                setTimeout(loadData, 1000 * 60);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorAlert('Failed to load repository data.');
        });
}

/**
 * Populates the repositories table with data from an array of repository objects.
 *
 * This function first retrieves the DOM element for the repositories table and clears its contents.
 * It then iterates over each repository object, creating a new table row for each one. Each row is populated
 * with various attributes and HTML content based on the repository's properties, such as visibility,
 * fork status, number of stars, forks, issues, language, and organization.
 * After populating all rows, it conditionally calls the `debouncedFilterRepositories` function if global
 * variable `isUpdatingFilters` is falsy.
 *
 * @param repositories - An array of repository objects containing data to populate the table.
 */
function populateRepositoriesTable(repositories) {
    const repositoriesTable = document.getElementById('repositories');
    if (!repositoriesTable) return; // Safety check
    
    repositoriesTable.innerHTML = '';

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
        repositoriesTable.appendChild(row);
    });

    if (!isUpdatingFilters) {
        debouncedFilterRepositories();
    }
}

/**
 * Updates the organization filter dropdown with unique organizations from repositories.
 */
function updateOrganizationFilter(repositories) {
    const organizationFilter = document.getElementById('organizationFilter');
    if (!organizationFilter) return; // Safety check
    
    const existingValue = organizationFilter.value;

    const organizations = Array.from(new Set(repositories.map(repo => repo.organization)));
    organizationFilter.innerHTML = `<option value="">All Organizations</option>`;
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
 * Updates the language filter dropdown based on the list of repositories provided.
 *
 * This function retrieves the current value of the language filter, extracts unique languages from the repositories,
 * sorts them, and populates the dropdown with these languages. It also ensures that the previously selected language,
 * if still present in the new list, remains selected.
 *
 * @param {Array} repositories - An array of repository objects, each containing a 'language' property.
 */
function updateLanguageFilter(repositories) {
    const languageFilter = document.getElementById('languageFilter');
    if (!languageFilter) return; // Safety check
    
    const existingValue = languageFilter.value;

    const languages = Array.from(new Set(repositories.map(repo => repo.language)))
        .filter(lang => lang)
        .sort();
        
    languageFilter.innerHTML = `<option value="">All Languages</option>`;
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

/**
 * Filters repositories based on user-selected criteria and updates the UI accordingly.
 *
 * This function retrieves filter values from the DOM, updates the URL parameters,
 * and iterates through each repository row to determine if it matches the specified filters.
 * It then toggles the display of matching rows and updates the count of visible repositories.
 *
 * @returns void
 */
function filterRepositories() {
    if (isUpdatingFilters) return;
    
    isUpdatingFilters = true;
    
    try {
        const organization = document.getElementById('organizationFilter')?.value || '';
        const language = document.getElementById('languageFilter')?.value || '';
        const visibility = document.getElementById('visibilityFilter')?.value || '';
        const isFork = document.getElementById('isForkFilter')?.checked || false;
        const hasForks = document.getElementById('hasForkFilter')?.checked || false;
        const hasIssues = document.getElementById('hasIssuesFilter')?.checked || false;

        updateURLParameters(organization, language, visibility, isFork, hasForks, hasIssues);

        let counter = 0;
        document.querySelectorAll('.repository-row').forEach(row => {
            const organizationMatch = !organization || row.dataset.organization === organization;
            const languageMatch = !language || row.dataset.language === language;
            const visibilityMatch = !visibility || row.dataset.visibility === visibility;
            const isForkMatch = !isFork || row.dataset.fork === 'true';
            const hasForksMatch = !hasForks || row.dataset.hasForks === 'true';
            const hasIssuesMatch = !hasIssues || row.dataset.hasIssues === 'true';

            const matches = organizationMatch && languageMatch && visibilityMatch && 
                            isForkMatch && hasForksMatch && hasIssuesMatch;
            
            row.style.display = matches ? '' : 'none';
            if (matches) {
                counter++;
            }
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
 * Clears and sets a timeout to delay the execution of filterRepositories.
 */
function debouncedFilterRepositories() {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }
    filterTimeout = setTimeout(filterRepositories, 100);
}

/**
 * Updates the URL parameters based on the given filter criteria.
 *
 * This function constructs a new query string using the provided filters and updates the current URL's search parameters accordingly.
 * If a filter value is falsy, its corresponding parameter is removed from the query string.
 *
 * @param organization - The organization filter value to be set or removed.
 * @param language - The language filter value to be set or removed.
 * @param visibility - The visibility filter value to be set or removed.
 * @param isFork - A boolean indicating whether to include forked repositories in the results.
 * @param hasForks - A boolean indicating whether to include repositories that have forks in the results.
 * @param hasIssues - A boolean indicating whether to include repositories with issues in the results.
 */
function updateURLParameters(organization, language, visibility, isFork, hasForks, hasIssues) {
    try {
        const queryString = new URLSearchParams(window.location.search);
        
        if (organization) queryString.set('organization', organization);
        else queryString.delete('organization');
        
        if (language) queryString.set('language', language);
        else queryString.delete('language');
        
        if (visibility) queryString.set('visibility', visibility);
        else queryString.delete('visibility');
        
        if (isFork) queryString.set('isFork', 'true');
        else queryString.delete('isFork');
        
        if (hasForks) queryString.set('hasForks', 'true');
        else queryString.delete('hasForks');
        
        if (hasIssues) queryString.set('hasIssues', 'true');
        else queryString.delete('hasIssues');
        
        const newURL = queryString.toString() ? '?' + queryString.toString() : window.location.pathname;
        window.history.replaceState({}, '', newURL);
    } catch (error) {
        console.error('Error updating URL parameters:', error);
    }
}

/**
 * Resets all filter options to their default states and triggers a filtered repository update.
 *
 * This function checks if filters are currently being updated. If not, it resets various filter elements
 * such as dropdowns and checkboxes to their initial values. After resetting the filters, it calls
 * `debouncedFilterRepositories` to apply the changes and update the displayed repositories.
 */
function resetFilters() {
    if (isUpdatingFilters) return;
    
    const organizationFilter = document.getElementById('organizationFilter');
    const languageFilter = document.getElementById('languageFilter');
    const visibilityFilter = document.getElementById('visibilityFilter');
    const isForkFilter = document.getElementById('isForkFilter');
    const hasForkFilter = document.getElementById('hasForkFilter');
    const hasIssuesFilter = document.getElementById('hasIssuesFilter');
    
    if (organizationFilter) organizationFilter.value = '';
    if (languageFilter) languageFilter.value = '';
    if (visibilityFilter) visibilityFilter.value = '';
    if (isForkFilter) isForkFilter.checked = false;
    if (hasForkFilter) hasForkFilter.checked = false;
    if (hasIssuesFilter) hasIssuesFilter.checked = false;
    
    debouncedFilterRepositories();
}

/**
 * Loads filter settings from the current URL query parameters and applies them to corresponding UI elements.
 *
 * The function retrieves query parameters related to language, visibility, and various boolean filters (isFork, hasForks, hasIssues).
 * It checks if the respective UI elements exist and sets their values or states based on the parsed query parameters.
 */
function loadFiltersFromURL() {
    const params = new URLSearchParams(window.location.search);
    
    if (params.has('language')) {
        const language = params.get('language');
        const languageFilter = document.getElementById('languageFilter');
        if (languageFilter?.querySelector(`option[value="${language}"]`)) {
            languageFilter.value = language;
        }
    }

    if (params.has('visibility')) {
        const visibilityFilter = document.getElementById('visibilityFilter');
        if (visibilityFilter) {
            visibilityFilter.value = params.get('visibility');
        }
    }
    
    const isForkFilter = document.getElementById('isForkFilter');
    const hasForkFilter = document.getElementById('hasForkFilter');
    const hasIssuesFilter = document.getElementById('hasIssuesFilter');
    
    if (isForkFilter) isForkFilter.checked = params.get('isFork') === 'true';
    if (hasForkFilter) hasForkFilter.checked = params.get('hasForks') === 'true';
    if (hasIssuesFilter) hasIssuesFilter.checked = params.get('hasIssues') === 'true';
}

/**
 * Logs an error message to the console and displays it as an alert.
 */
function showErrorAlert(message) {
    console.error(message);
    alert(message);
}

/**
 * Sets up event listeners for filter elements and the reset button.
 */
function setupEventListeners() {
    const elements = [
        { id: 'organizationFilter', event: 'change' },
        { id: 'languageFilter', event: 'change' },
        { id: 'visibilityFilter', event: 'change' },
        { id: 'isForkFilter', event: 'change' },
        { id: 'hasForkFilter', event: 'change' },
        { id: 'hasIssuesFilter', event: 'change' }
    ];
    
    elements.forEach(({ id, event }) => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener(event, debouncedFilterRepositories);
        }
    });
    
    const resetButton = document.getElementById('resetFilters');
    if (resetButton) {
        resetButton.addEventListener('click', resetFilters);
    }
}

document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        loadData();
    }
});

window.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
    loadFiltersFromURL();
    loadData();
});
