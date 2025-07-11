let isUpdatingPRs = false;
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
 * Displays an error alert on the page with an option to retry automatically.
 */
function showErrorAlert(message, isRetriable = false) {
    console.error(message);
    
    let alertContainer = document.getElementById('error-alert-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'error-alert-container';
        alertContainer.className = 'position-fixed top-0 start-50 translate-middle-x';
        alertContainer.style.zIndex = '9999';
        alertContainer.style.marginTop = '20px';
        document.body.appendChild(alertContainer);
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-danger alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <strong>Error:</strong> ${escapeHtml(message)}
        ${isRetriable ? '<br><small>Retrying automatically...</small>' : ''}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

/**
 * Displays a loading indicator in the "groupedPullRequests" container.
 */
function showLoadingIndicator() {
    const groupedContainer = document.getElementById("groupedPullRequests");
    if (!groupedContainer) return;
    
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
    if (loadingIndicator) {
        loadingIndicator.remove();
    }
}

/**
 * Validates an array of PR data items.
 *
 * This function checks if the input is an array and iterates over a sample of its elements.
 * It validates each item to ensure it is an object and checks for the presence of required fields.
 * If any item fails validation, it logs an error or warning message and returns false.
 * If all items pass validation, it returns true.
 *
 * @param {Array} items - The array of PR data items to validate.
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

        const requiredFields = ['title', 'url', 'repository'];
        for (const field of requiredFields) {
            if (!item[field]) {
                console.warn(`Missing required field '${field}' in item:`, item);
            }
        }
    }
    
    return true;
}

/**
 * Formats a given date string into a human-readable relative time or locale-specific format.
 *
 * The function checks if the input is valid and calculates the difference between the current date and the provided date.
 * It returns a string indicating how many days, weeks, months, or years ago the date was, or the formatted date itself if older than a year.
 *
 * @param dateString - A string representing the date to be formatted.
 * @returns A human-readable string representing the relative time or locale-specific formatted date.
 */
function formatDate(dateString) {
    if (!dateString) return 'Unknown date';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid date';
        
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
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
 * Retrieves HTML badge markup based on CI state.
 */
function getStateBadge(state) {
    const badges = {
        'success': {
            class: 'bg-success',
            icon: 'fa-check-circle',
            text: 'CI Success'
        },
        'failure': {
            class: 'bg-danger',
            icon: 'fa-times-circle',
            text: 'CI Failure'
        },
        'pending': {
            class: 'bg-warning text-dark',
            icon: 'fa-hourglass-half',
            text: 'CI Pending'
        },
        'error': {
            class: 'bg-danger',
            icon: 'fa-exclamation-triangle',
            text: 'CI Error'
        },
        'skipped': {
            class: 'bg-dark',
            icon: 'fa-arrow-circle-right',
            text: 'CI Skipped'
        }
    };
    
    const badge = badges[state] || {
        class: 'bg-secondary',
        icon: 'fa-question-circle',
        text: 'CI Unknown'
    };
    
    return `<span class="badge ${badge.class} me-1 mb-1" title="Continuous Integration Status">
        <i class="fas ${badge.icon} me-1"></i>${badge.text}
    </span>`;
}

/**
 * Generates a badge indicating the merge status of a pull request.
 *
 * The function checks the `mergeable` and `mergeable_state` parameters to determine the appropriate badge style, icon,
 * text, and title. It returns an HTML span element with a Bootstrap badge class, Font Awesome icon, and descriptive
 * text based on the merge state.
 *
 * @param mergeable - A boolean or null indicating whether the pull request is mergeable.
 * @param mergeable_state - A string representing the current merge state of the pull request.
 * @returns An HTML span element with a badge indicating the merge status.
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
        [MERGEABLE_STATES.CLEAN]: {
            class: 'bg-success',
            icon: 'fa-check-circle',
            text: 'Mergeable',
            title: 'Ready to merge - no conflicts'
        },
        [MERGEABLE_STATES.UNSTABLE]: {
            class: 'bg-info',
            icon: 'fa-info-circle',
            text: 'Unstable',
            title: 'Mergeable but CI checks failed'
        },
        [MERGEABLE_STATES.BLOCKED]: {
            class: 'bg-warning text-dark',
            icon: 'fa-lock',
            text: 'Blocked',
            title: 'Merge blocked by branch protection rules'
        },
        [MERGEABLE_STATES.BEHIND]: {
            class: 'bg-secondary',
            icon: 'fa-arrow-circle-down',
            text: 'Behind',
            title: 'Branch is behind the base branch'
        },
        [MERGEABLE_STATES.DIRTY]: {
            class: 'bg-danger',
            icon: 'fa-exclamation-circle',
            text: 'Dirty',
            title: 'Merge conflicts detected'
        }
    };
    
    const stateInfo = stateMap[mergeable_state] || {
        class: 'bg-info',
        icon: 'fa-info-circle',
        text: escapeHtml(mergeable_state || 'Unknown'),
        title: `Merge state: ${mergeable_state || 'Unknown'}`
    };
    
    return `<span class="badge ${stateInfo.class} me-1 mb-1" title="${stateInfo.title}">
        <i class="fas ${stateInfo.icon} me-1"></i>${stateInfo.text}
    </span>`;
}

/**
 * Determines if a pull request (PR) is valid based on its state and mergeability.
 *
 * This function checks the `is_valid_pr` property of the PR object first. If it exists, the function returns that value.
 * Otherwise, it evaluates the PR's state to determine validity. A PR is considered valid if its state is 'success',
 * it is marked as mergeable, and its mergeable state is either 'clean' or 'unstable'.
 *
 * @param {Object} pr - The pull request object to validate.
 * @returns {boolean} - True if the PR is valid, false otherwise.
 */
function isValidPR(pr) {
    if (pr.is_valid_pr !== undefined) {
        return pr.is_valid_pr;
    }
    
    return (pr.state === 'success' && 
           pr.mergeable === true && 
           pr.mergeable_state && 
           [MERGEABLE_STATES.CLEAN, MERGEABLE_STATES.UNSTABLE].includes(pr.mergeable_state));
}

/**
 * Calculates the background and text colors based on a given hex color.
 *
 * This function first validates the input color string, ensuring it is a valid hex color.
 * It then extracts the red, green, and blue components from the hex code.
 * Using these components, it calculates the luminance of the color to determine
 * whether a light or dark text color will be more readable against the background.
 * The function returns an object containing both the background and text colors.
 *
 * @param {string} color - The hex color string for which to calculate the label colors.
 * @returns {Object|null} An object with `backgroundColor` and `textColor` properties, or null if input is invalid.
 */
function calculateLabelColors(color) {
    if (!color || typeof color !== 'string') return null;
    
    color = color.replace(/^#/, '');
    
    if (!/^[0-9A-Fa-f]{6}$/.test(color)) return null;
    
    const r = parseInt(color.substr(0, 2), 16);
    const g = parseInt(color.substr(2, 2), 16);
    const b = parseInt(color.substr(4, 2), 16);
    
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    const textColor = luminance > 0.5 ? '#000' : '#fff';
    
    return {
        backgroundColor: `#${color}`,
        textColor: textColor
    };
}

/**
 * Creates a label element with specified styling and attributes.
 *
 * This function takes a label object, calculates its colors, and creates an HTML span element
 * styled according to those colors. It sets the text content and tooltip of the span based on
 * the label's name and description. If any required properties are missing or color calculation fails,
 * it returns null.
 */
function createLabelElement(label) {
    if (!label || !label.name) return null;
    
    const colors = calculateLabelColors(label.color);
    if (!colors) return null;
    
    const labelSpan = document.createElement("span");
    labelSpan.classList.add("badge", "label-badge", "me-1", "mb-1");
    labelSpan.style.backgroundColor = colors.backgroundColor;
    labelSpan.style.color = colors.textColor;
    labelSpan.style.border = '1px solid rgba(0,0,0,0.1)';
    labelSpan.setAttribute("title", escapeHtml(label.description || label.name));
    labelSpan.textContent = escapeHtml(label.name);
    
    return labelSpan;
}

/**
 * Groups pull requests (PRs) by their owner and sorts them based on validity, state, and creation date.
 *
 * This function iterates over each item in the input array, grouping them under their respective owners.
 * After grouping, it sorts each group of PRs first by validity, then by a predefined order of states,
 * and finally by their creation date in descending order.
 *
 * @param items - An array of pull request objects to be grouped and sorted.
 * @returns An object where keys are owner names and values are arrays of sorted pull requests.
 */
function groupPRsByOwner(items) {
    const groupedData = {};
    
    items.forEach(item => {
        const owner = item?.owner || 'Unknown';
        if (!groupedData[owner]) {
            groupedData[owner] = [];
        }
        groupedData[owner].push(item);
    });
    
    Object.keys(groupedData).forEach(owner => {
        groupedData[owner].sort((a, b) => {
            const aValid = isValidPR(a);
            const bValid = isValidPR(b);
            
            if (aValid && !bValid) return -1;
            if (!aValid && bValid) return 1;
            
            const aStateIndex = STATE_ORDER.indexOf(a.state || '');
            const bStateIndex = STATE_ORDER.indexOf(b.state || '');
            if (aStateIndex !== bStateIndex) {
                return aStateIndex - bStateIndex;
            }
            
            const dateA = new Date(a.created_at || 0);
            const dateB = new Date(b.created_at || 0);
            return dateB - dateA;
        });
    });
    
    return groupedData;
}

/**
 * Creates a list item element representing a pull request.
 *
 * This function constructs an HTML list item element to display information about a given pull request,
 * including its title, repository, creation time, labels, and merge state badges. It conditionally adds
 * styling based on the validity of the pull request and appends various child elements for structured
 * presentation.
 *
 * @param pr - An object containing pull request data.
 * @returns The constructed list item element representing the pull request.
 */
function createPRListItem(pr) {
    const ciState = getStateBadge(pr.state);
    const mergeState = getMergeableBadge(pr.mergeable, pr.mergeable_state);
    
    const itemLi = document.createElement('li');
    itemLi.className = 'list-group-item';
    
    if (isValidPR(pr)) {
        itemLi.classList.add('valid-pr', 'border-success');
        itemLi.style.borderLeftWidth = '4px';
    }

    const container = document.createElement("div");
    container.className = "d-flex justify-content-between align-items-start";
    itemLi.appendChild(container);

    const leftSection = document.createElement("div");
    leftSection.className = "flex-grow-1 pe-3";
    container.appendChild(leftSection);
    
    const titleDiv = document.createElement('div');
    titleDiv.className = 'mb-2';
    
    const titleLink = document.createElement('a');
    titleLink.href = escapeHtml(pr.url || '#');
    titleLink.target = '_blank';
    titleLink.className = 'text-decoration-none fw-bold';
    titleLink.textContent = pr.title || 'Untitled Pull Request';
    titleLink.setAttribute('rel', 'noopener noreferrer');
    
    titleDiv.appendChild(titleLink);
    leftSection.appendChild(titleDiv);
    
    const repoDiv = document.createElement('div');
    repoDiv.className = 'mb-2';
    
    const repoSpan = document.createElement('span');
    repoSpan.className = 'text-muted small';
    
    if (pr.repository && pr.full_name) {
        const repoLink = document.createElement('a');
        repoLink.href = `https://github.com/${escapeHtml(pr.full_name)}`;
        repoLink.target = '_blank';
        repoLink.className = 'text-muted text-decoration-none';
        repoLink.textContent = pr.repository;
        repoLink.setAttribute('rel', 'noopener noreferrer');
        repoSpan.appendChild(repoLink);
    } else {
        repoSpan.textContent = pr.repository || 'Unknown repository';
    }
    
    repoDiv.appendChild(repoSpan);
    leftSection.appendChild(repoDiv);
    
    const timeDiv = document.createElement('div');
    timeDiv.className = 'mb-2';
    
    const timeSpan = document.createElement('span');
    timeSpan.className = 'text-muted small';
    timeSpan.innerHTML = `<i class="fas fa-clock me-1"></i>${formatDate(pr.created_at)}`;
    timeDiv.appendChild(timeSpan);
    leftSection.appendChild(timeDiv);

    const containerLabels = document.createElement('div');
    containerLabels.className = 'mt-2';
    leftSection.appendChild(containerLabels);

    if (pr.labels && Array.isArray(pr.labels) && pr.labels.length > 0) {
        pr.labels.forEach(label => {
            const labelElement = createLabelElement(label);
            if (labelElement) {
                containerLabels.appendChild(labelElement);
            }
        });
    }

    const rightSection = document.createElement("div");
    rightSection.className = "badge-container d-flex flex-column align-items-end";
    container.appendChild(rightSection);
    
    const stateDiv = document.createElement('div');
    stateDiv.className = 'mb-1';
    stateDiv.innerHTML = ciState;
    rightSection.appendChild(stateDiv);
    
    if (pr.mergeable !== undefined || pr.mergeable_state !== undefined) {
        const mergeableDiv = document.createElement('div');
        mergeableDiv.innerHTML = mergeState;
        rightSection.appendChild(mergeableDiv);
    }
    
    return itemLi;
}

/**
 * Populates the UI with pull requests grouped by their owners.
 *
 * This function first checks if an update is already in progress and skips if true.
 * It then validates the input data, throws errors if validation fails, and proceeds to populate the UI.
 * The function groups PRs by owner, counts valid PRs, and updates counters accordingly.
 * It also handles edge cases like empty data and appends error alerts on failure.
 *
 * @param items - An array of pull request objects.
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
        
        const counterContainer = document.getElementById("openPullRequestsCount");
        const validCounterContainer = document.getElementById("validPullRequestsCount");
        
        if (!counterContainer || !validCounterContainer) {
            throw new Error('Counter container elements not found');
        }
        
        const groupedContainer = document.getElementById("groupedPullRequests");
        if (!groupedContainer) {
            throw new Error('Grouped pull requests container element not found');
        }
        
        groupedContainer.innerHTML = '';

        if (items.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'list-group-item list-group-item-light text-center py-5';
            emptyDiv.innerHTML = `
                <i class="fas fa-code-branch text-success fa-3x mb-3"></i>
                <h5 class="text-muted">No open pull requests found!</h5>
                <p class="text-muted">All caught up with your pull requests.</p>
            `;
            groupedContainer.appendChild(emptyDiv);
            counterContainer.textContent = '0';
            validCounterContainer.textContent = '0';
            return;
        }

        const groupedData = groupPRsByOwner(items);
        
        let validPRCount = 0;
        Object.values(groupedData).forEach(prs => {
            validPRCount += prs.filter(pr => isValidPR(pr)).length;
        });

        counterContainer.textContent = items.length;
        validCounterContainer.textContent = validPRCount;

        const sortedOwners = Object.keys(groupedData).sort();

        sortedOwners.forEach(owner => {
            const pullRequests = groupedData[owner];
            const groupId = `group-${owner.replace(/[^a-zA-Z0-9]+/g, '-')}`;
            
            const ownerDiv = document.createElement('div');
            ownerDiv.className = 'mb-4 card';

            const ownerHeader = document.createElement('div');
            ownerHeader.className = 'card-header bg-light';
            ownerDiv.appendChild(ownerHeader);

            const ownerButton = document.createElement('button');
            ownerButton.className = 'btn btn-link text-decoration-none p-0 fw-bold text-start w-100 d-flex justify-content-between align-items-center';
            ownerButton.type = 'button';
            ownerButton.setAttribute('data-bs-toggle', 'collapse');
            ownerButton.setAttribute('data-bs-target', `#${groupId}`);
            ownerButton.setAttribute('aria-expanded', 'true');
            ownerButton.setAttribute('aria-controls', groupId);
            
            const ownerText = document.createElement('span');
            ownerText.textContent = `${escapeHtml(owner)}`;
            ownerButton.appendChild(ownerText);
            
            const badgeAndChevron = document.createElement('div');
            badgeAndChevron.className = 'd-flex align-items-center';

            const totalCountBadge = document.createElement('span');
            totalCountBadge.className = 'badge bg-primary me-1';
            totalCountBadge.textContent = pullRequests.length;
            totalCountBadge.setAttribute('title', 'Total Pull Requests');
            badgeAndChevron.appendChild(totalCountBadge);

            const validCount = pullRequests.filter(pr => isValidPR(pr)).length;
            if (validCount > 0) {
                const validCountBadge = document.createElement('span');
                validCountBadge.className = 'badge bg-success me-2';
                validCountBadge.textContent = validCount;
                validCountBadge.setAttribute('title', 'Ready to Merge');
                badgeAndChevron.appendChild(validCountBadge);
            }
            
            const ownerChevron = document.createElement("i");
            ownerChevron.className = 'fas fa-chevron-down';
            badgeAndChevron.appendChild(ownerChevron);
            
            ownerButton.appendChild(badgeAndChevron);
            ownerHeader.appendChild(ownerButton);
            
            const pullRequestList = document.createElement('ul');
            pullRequestList.className = 'list-group list-group-flush collapse show';
            pullRequestList.id = groupId;
            
            pullRequests.forEach(pr => {
                const prItem = createPRListItem(pr);
                pullRequestList.appendChild(prItem);
            });

            ownerDiv.appendChild(pullRequestList);
            groupedContainer.appendChild(ownerDiv);
        });

        retryCount = 0;
        
    } catch (error) {
        console.error('Error populating pull requests:', error);
        showErrorAlert(`Failed to display pull requests: ${error.message}`);
        
        const groupedContainer = document.getElementById("groupedPullRequests");
        if (groupedContainer) {
            groupedContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> Failed to load pull requests. Please refresh the page.
                </div>
            `;
        }
    } finally {
        isUpdatingPRs = false;
    }
}

/**
 * Initiates the process of loading pull requests data from the server.
 *
 * This function handles the lifecycle of data loading, including checking if the document is hidden,
 * scheduling subsequent loads, showing and hiding a loading indicator, fetching data via an API call,
 * processing the response, and managing retries in case of errors. It also updates the UI based on
 * the fetched data and error handling.
 *
 * @returns void
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
    
    fetch('/api/v1/pull-requests')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response format');
            }
            
            const pullRequests = data.openPullRequests || data.pullRequests || [];
            populateIssuesGroupedByOwner(pullRequests);
            scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading data:', error);
            hideLoadingIndicator();
            
            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;
            
            showErrorAlert(
                `Failed to load pull requests: ${error.message}`,
                isRetriable
            );
            
            if (isRetriable) {
                const delay = RETRY_DELAY * Math.pow(2, retryCount - 1);
                loadDataTimeout = setTimeout(loadData, delay);
            } else {
                retryCount = 0;
                scheduleNextLoad();
            }
        });
}

/**
 * Clears any existing load timeout and schedules a new one if the document is not hidden.
 */
function scheduleNextLoad() {
    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
    }
    
    if (!document.hidden) {
        loadDataTimeout = setTimeout(loadData, 60000);
    }
}

/**
 * Handles visibility change events, clearing or triggering data loading as needed.
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
 * Clears the loadDataTimeout to prevent further data loading on page unload.
 */
function handlePageUnload() {
    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
        loadDataTimeout = null;
    }
}

/**
 * Initializes event listeners for visibility change and page unload events.
 */
function initializeEventListeners() {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('beforeunload', handlePageUnload);
    window.addEventListener('pagehide', handlePageUnload);
}

/**
 * Initializes the Pull Requests Management Script by checking for required DOM elements and setting up event listeners.
 */
function initialize() {
    console.log('Initializing Pull Requests Management Script...');
    
    const requiredElements = ['openPullRequestsCount', 'validPullRequestsCount', 'groupedPullRequests'];
    const missingElements = requiredElements.filter(id => !document.getElementById(id));
    
    if (missingElements.length > 0) {
        console.error('Missing required DOM elements:', missingElements);
        showErrorAlert('Page not properly initialized. Please refresh the page.');
        return;
    }
    
    initializeEventListeners();
    loadData();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
} else {
    initialize();
}
