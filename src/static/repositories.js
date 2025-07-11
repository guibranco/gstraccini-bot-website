let isUpdatingFilters = false;
let filterTimeout = null;

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

function debouncedFilterRepositories() {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }
    filterTimeout = setTimeout(filterRepositories, 100);
}

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
