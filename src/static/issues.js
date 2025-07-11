let isUpdatingIssues = false;
let loadDataTimeout = null;
let retryCount = 0;
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000; // 5 seconds

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
 */
function showLoadingIndicator() {
    const groupedContainer = document.getElementById("groupedIssues");
    if (!groupedContainer) return;
    
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
    if (loadingIndicator) {
        loadingIndicator.remove();
    }
}

/**
 * Validates an array of issue data items to ensure they are properly formatted.
 *
 * This function checks if the input is an array and then iterates over a sample
 * of up to 5 items to validate their structure. Each item must be an object with
 * at least the 'title', 'url', and 'repository' fields present. If any validation
 * fails, it logs an error or warning message and returns false. If all checks pass,
 * it returns true.
 *
 * @param {Array} items - The array of issue data items to validate.
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
 * Calculates label colors based on a given hexadecimal color value.
 *
 * This function first validates the input color string and ensures it is in a proper hexadecimal format.
 * It then extracts the red, green, and blue components from the color.
 * Using these components, it calculates the luminance to determine whether a dark or light text color should be used for readability.
 * The function returns an object containing both the background color and the calculated text color.
 *
 * @param {string} color - The hexadecimal color value (with or without a leading '#').
 * @returns {{backgroundColor: string, textColor: string}} An object with properties for background and text colors.
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
 * Creates a label element with styling and attributes based on the provided label data.
 *
 * This function first checks if the label object and its name are valid. If not, it returns null.
 * It then calculates the colors for the label using the `calculateLabelColors` function.
 * If color calculation fails, it also returns null. Otherwise, it creates a span element,
 * applies necessary classes and styles, sets the title attribute with escaped HTML from
 * the label's description or name, and sets the text content to the escaped name of the label.
 *
 * @param {Object} label - The label object containing information about the label.
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
 * Formats a given date string into a human-readable relative or absolute date format.
 *
 * This function first checks if the input dateString is valid and returns 'Unknown date' if it's empty or invalid.
 * It then calculates the difference between the current date and the input date to determine the time elapsed.
 * Depending on the number of days, it formats the output as '1 day ago', '{days} days ago', '{weeks} weeks ago',
 * '{months} months ago', or an absolute date using `toLocaleDateString()`.
 *
 * @param dateString - A string representing a date.
 * @returns A formatted string indicating how long ago the input date was, or an error message if the date is invalid.
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
 * Groups a list of items by their owner and sorts each group by creation date in descending order.
 *
 * The function iterates over each item, assigns an 'Unknown' owner if none is provided,
 * groups the items by owner in an object, and then sorts each group based on the created_at field.
 * If an item lacks a created_at field, it defaults to 0 for sorting purposes.
 *
 * @param {Array} items - An array of objects, each representing an item with at least an 'owner' and 'created_at' property.
 */
function groupIssuesByOwner(items) {
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
            const dateA = new Date(a.created_at || 0);
            const dateB = new Date(b.created_at || 0);
            return dateB - dateA;
        });
    });
    
    return groupedData;
}

/**
 * Creates a list item element representing an issue.
 *
 * This function constructs an HTML list item (`<li>`) element with structured content
 * including a title, repository link, creation time, and labels. It handles cases where
 * optional properties of the issue might be missing or empty by providing default values.
 * The function ensures that all dynamically created elements are properly appended to their
 * respective parent elements.
 *
 * @param {Object} issue - An object containing details about the issue such as title, URL,
 *                         repository, full_name, labels, and created_at.
 * @returns {HTMLElement} A list item element (`<li>`) representing the issue.
 */
function createIssueListItem(issue) {
    const itemLi = document.createElement('li');
    itemLi.className = 'list-group-item';
    
    const container = document.createElement("div");
    container.className = "d-flex justify-content-between align-items-start";
    itemLi.appendChild(container);

    const leftSection = document.createElement("div");
    leftSection.className = "flex-grow-1";
    container.appendChild(leftSection);
    
    const titleLink = document.createElement('a');
    titleLink.href = escapeHtml(issue.url || '#');
    titleLink.target = '_blank';
    titleLink.className = 'text-decoration-none fw-bold';
    titleLink.textContent = issue.title || 'Untitled Issue';
    titleLink.setAttribute('rel', 'noopener noreferrer');
    
    const titleDiv = document.createElement('div');
    titleDiv.className = 'mb-2';
    titleDiv.appendChild(titleLink);
    leftSection.appendChild(titleDiv);
    
    const repoDiv = document.createElement('div');
    repoDiv.className = 'mb-2';
    
    const repoSpan = document.createElement('span');
    repoSpan.className = 'text-muted small';
    
    if (issue.repository && issue.full_name) {
        const repoLink = document.createElement('a');
        repoLink.href = `https://github.com/${escapeHtml(issue.full_name)}`;
        repoLink.target = '_blank';
        repoLink.className = 'text-muted text-decoration-none';
        repoLink.textContent = issue.repository;
        repoLink.setAttribute('rel', 'noopener noreferrer');
        repoSpan.appendChild(repoLink);
    } else {
        repoSpan.textContent = issue.repository || 'Unknown repository';
    }
    
    repoDiv.appendChild(repoSpan);
    leftSection.appendChild(repoDiv);
    
    const timeDiv = document.createElement('div');
    timeDiv.className = 'mb-2';
    
    const timeSpan = document.createElement('span');
    timeSpan.className = 'text-muted small';
    timeSpan.innerHTML = `<i class="fas fa-clock me-1"></i>${formatDate(issue.created_at)}`;
    timeDiv.appendChild(timeSpan);
    leftSection.appendChild(timeDiv);

    const containerLabels = document.createElement('div');
    containerLabels.className = 'mt-2';
    leftSection.appendChild(containerLabels);

    if (issue.labels && Array.isArray(issue.labels) && issue.labels.length > 0) {
        issue.labels.forEach(label => {
            const labelElement = createLabelElement(label);
            if (labelElement) {
                containerLabels.appendChild(labelElement);
            }
        });
    }
    
    return itemLi;
}

/**
 * Populates the issues grouped by owner in the UI.
 *
 * This function first checks if an update is already in progress and skips execution if true.
 * It then hides the loading indicator, validates the issue data, and checks for the presence of necessary DOM elements.
 * If any validation fails or required elements are missing, it throws an error.
 * For non-empty issues, it groups them by owner, sorts the owners alphabetically, and creates UI components for each group.
 * Each owner group includes a collapsible section displaying their issues.
 * If there are no issues, it displays a message indicating that no open issues were found.
 * In case of an error during execution, it logs the error, shows an alert, and updates the UI with an error message.
 *
 * @param items - An array of issue objects to be grouped and displayed.
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
        
        const counterContainer = document.getElementById("openIssuesCount");
        if (!counterContainer) {
            throw new Error('Counter container element not found');
        }
        
        const groupedContainer = document.getElementById("groupedIssues");
        if (!groupedContainer) {
            throw new Error('Grouped issues container element not found');
        }
        
        groupedContainer.innerHTML = '';

        if (items.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'list-group-item list-group-item-light text-center py-5';
            emptyDiv.innerHTML = `
                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                <h5 class="text-muted">No open issues found!</h5>
                <p class="text-muted">Great job keeping up with your issues.</p>
            `;
            groupedContainer.appendChild(emptyDiv);
            counterContainer.textContent = '0';
            return;
        }

        counterContainer.textContent = items.length;
        
        const groupedData = groupIssuesByOwner(items);
        const sortedOwners = Object.keys(groupedData).sort();

        sortedOwners.forEach(owner => {
            const issues = groupedData[owner];
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
            
            const issueCountBadge = document.createElement('span');
            issueCountBadge.className = 'badge bg-primary me-2';
            issueCountBadge.textContent = issues.length;
            badgeAndChevron.appendChild(issueCountBadge);
            
            const ownerChevron = document.createElement("i");
            ownerChevron.className = 'fas fa-chevron-down';
            badgeAndChevron.appendChild(ownerChevron);
            
            ownerButton.appendChild(badgeAndChevron);
            ownerHeader.appendChild(ownerButton);
            
            const issueList = document.createElement('ul');
            issueList.className = 'list-group list-group-flush collapse show';
            issueList.id = groupId;
            
            issues.forEach(issue => {
                const issueItem = createIssueListItem(issue);
                issueList.appendChild(issueItem);
            });

            ownerDiv.appendChild(issueList);
            groupedContainer.appendChild(ownerDiv);
        });
        
        retryCount = 0;
        
    } catch (error) {
        console.error('Error populating issues:', error);
        showErrorAlert(`Failed to display issues: ${error.message}`);
        
        const groupedContainer = document.getElementById("groupedIssues");
        if (groupedContainer) {
            groupedContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> Failed to load issues. Please refresh the page.
                </div>
            `;
        }
    } finally {
        isUpdatingIssues = false;
    }
}

/**
 * Loads data from the API and processes it to populate issues grouped by owner.
 *
 * This function handles loading logic, including scheduling retries in case of errors.
 * It fetches issue data, validates the response, populates issues, and schedules the next load.
 *
 * @returns {void}
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
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response format');
            }
            
            const issues = data.openIssues || data.issues || [];
            populateIssuesGroupedByOwner(issues);
            scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading data:', error);
            hideLoadingIndicator();
            
            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;
            
            showErrorAlert(
                `Failed to load issues: ${error.message}`,
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
 * Schedules or reschedules data loading if the document is not hidden.
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
 * Handles visibility change events, clearing the load timeout if the document is hidden and loading data otherwise.
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
 * Clears the loadDataTimeout to prevent data loading on page unload.
 */
function handlePageUnload() {
    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
        loadDataTimeout = null;
    }
}

/**
 * Sets up event listeners for visibility change and page unload events.
 */
function initializeEventListeners() {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('beforeunload', handlePageUnload);
    window.addEventListener('pagehide', handlePageUnload);
}

/**
 * Initializes the issues management script by checking required DOM elements and setting up event listeners and data loading.
 */
function initialize() {
    console.log('Initializing Issues Management Script...');
    
    const requiredElements = ['openIssuesCount', 'groupedIssues'];
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
