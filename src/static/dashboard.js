let isDashboardUpdating = false;
let loadDataTimeout = null;
let retryCount = 0;
let animationFrameId = null;
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;
const UPDATE_INTERVAL = 60000;

/**
 * Escapes HTML special characters in a given string.
 */
function escapeHtml(unsafe) {
    if (unsafe === undefined || unsafe === null) return '';
    const div = document.createElement('div');
    div.textContent = String(unsafe);
    return div.innerHTML;
}

/**
 * Displays an error alert on the dashboard with the given message.
 */
function showErrorAlert(message, isRetriable = false) {
    console.error(message);
    
    let alertContainer = document.getElementById('dashboard-error-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'dashboard-error-container';
        alertContainer.className = 'position-fixed top-0 start-50 translate-middle-x';
        alertContainer.style.zIndex = '9999';
        alertContainer.style.marginTop = '20px';
        document.body.appendChild(alertContainer);
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-danger alert-dismissible fade show shadow`;
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>
                <strong>Dashboard Error:</strong> ${escapeHtml(message)}
                ${isRetriable ? '<br><small class="text-muted">Retrying automatically...</small>' : ''}
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alertDiv);

    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.classList.remove('show');
            setTimeout(() => alertDiv.remove(), 150);
        }
    }, 6000);
}

/**
 * Displays a loading indicator in the specified container element.
 *
 * @param {string} containerId - The ID of the container element where the loading indicator should be displayed.
 */
function showLoadingIndicator(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const loadingDiv = document.createElement('li');
    loadingDiv.className = 'list-group-item text-center p-3';
    loadingDiv.innerHTML = `
        <div class="d-flex align-items-center justify-content-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span class="text-muted">Loading data...</span>
        </div>
    `;
    
    container.innerHTML = '';
    container.appendChild(loadingDiv);
}

/**
 * Formats a given date string into a human-readable relative time format or locale-specific date string.
 *
 * The function first checks if the input is valid; if not, it returns 'Unknown date'.
 * It then attempts to parse the dateString using `Date`. If parsing fails, it returns 'Invalid date'.
 * The current date and parsed date are used to calculate the difference in minutes, hours, days, weeks, months, or years.
 * Depending on the difference, it returns a string representing how long ago the date was in terms of these units.
 *
 * @param dateString - A string representing the date to be formatted.
 * @returns A human-readable relative time string or locale-specific date string, or 'Invalid date' if parsing fails.
 */
function formatDate(dateString) {
    if (!dateString) return 'Unknown date';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid date';
        
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        const diffHours = Math.ceil(diffTime / (1000 * 60 * 60));
        const diffMinutes = Math.ceil(diffTime / (1000 * 60));
        
        if (diffMinutes < 60) return `${diffMinutes} minutes ago`;
        if (diffHours < 24) return `${diffHours} hours ago`;
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
 * Returns an HTML badge based on the CI state.
 */
function getStateBadge(state) {
    if (!state) return '';
    
    const badges = {
        'success': {
            class: 'bg-success',
            icon: 'fa-check-circle',
            text: 'Success',
            title: 'CI checks passed successfully'
        },
        'failure': {
            class: 'bg-danger',
            icon: 'fa-times-circle',
            text: 'Failure',
            title: 'CI checks failed'
        },
        'pending': {
            class: 'bg-warning text-dark',
            icon: 'fa-hourglass-half',
            text: 'Pending',
            title: 'CI checks are running'
        },
        'error': {
            class: 'bg-danger',
            icon: 'fa-exclamation-triangle',
            text: 'Error',
            title: 'CI checks encountered an error'
        },
        'skipped': {
            class: 'bg-dark',
            icon: 'fa-arrow-circle-right',
            text: 'Skipped',
            title: 'CI checks were skipped'
        }
    };
    
    const badge = badges[state] || {
        class: 'bg-secondary',
        icon: 'fa-question-circle',
        text: 'Unknown',
        title: 'CI status unknown'
    };
    
    return `<span class="badge ${badge.class} ms-2" title="${badge.title}">
        <i class="fas ${badge.icon} me-1"></i>${badge.text}
    </span>`;
}

/**
 * Validates an array of items to ensure they meet specific criteria.
 * The function checks if the input is an array and verifies that each item
 * in a sample subset (up to 3 items) is an object with required fields: 'title', 'url', and 'repository'.
 *
 * @param {Array} items - The array of items to validate.
 * @param {string} type - The type of items being validated, used for error/warning messages.
 * @returns {boolean} - Returns true if all checks pass, otherwise false.
 */
function validateItems(items, type) {
    if (!Array.isArray(items)) {
        console.error(`Invalid ${type} data format: expected array`);
        return false;
    }
    
    const sampleSize = Math.min(3, items.length);
    for (let i = 0; i < sampleSize; i++) {
        const item = items[i];
        if (!item || typeof item !== 'object') {
            console.error(`Invalid ${type} item at index ${i}:`, item);
            return false;
        }
        
        const requiredFields = ['title', 'url', 'repository'];
        for (const field of requiredFields) {
            if (!item[field]) {
                console.warn(`Missing required field '${field}' in ${type} item:`, item);
            }
        }
    }
    
    return true;
}

/**
 * Creates a list item element with interactive features and additional details.
 *
 * This function generates an HTML list item (`<li>`) that represents an item,
 * such as a pull request or issue, with various interactive elements including
 * hover effects and links to the item's URL and repository. It also includes
 * badges for pull requests based on their state. The function constructs the
 * list item by creating multiple nested divs and appending them to form a structured
 * layout.
 *
 * @param {Object} item - The data object containing details about the item.
 * @param {boolean} [isPullRequest=false] - Indicates if the item is a pull request.
 */
function createListItem(item, isPullRequest = false) {
    const itemLi = document.createElement('li');
    itemLi.className = 'list-group-item border-0 py-3';
    
    itemLi.addEventListener('mouseenter', () => {
        itemLi.style.backgroundColor = '#f8f9fa';
    });
    itemLi.addEventListener('mouseleave', () => {
        itemLi.style.backgroundColor = '';
    });
    
    const container = document.createElement('div');
    container.className = 'd-flex justify-content-between align-items-start';
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'flex-grow-1 pe-3';
    
    const titleDiv = document.createElement('div');
    titleDiv.className = 'mb-2';
    
    const titleLink = document.createElement('a');
    titleLink.href = escapeHtml(item.url || '#');
    titleLink.target = '_blank';
    titleLink.rel = 'noopener noreferrer';
    titleLink.className = 'text-decoration-none fw-bold text-dark';
    titleLink.textContent = item.title || 'Untitled';
    
    titleLink.addEventListener('mouseenter', () => {
        titleLink.style.color = '#0d6efd';
        titleLink.style.transition = 'color 0.2s ease';
    });
    titleLink.addEventListener('mouseleave', () => {
        titleLink.style.color = '';
    });
    
    titleDiv.appendChild(titleLink);
    contentDiv.appendChild(titleDiv);
    
    const repoDiv = document.createElement('div');
    repoDiv.className = 'mb-2';
    
    const repoLink = document.createElement('a');
    repoLink.href = `https://github.com/${escapeHtml(item.full_name || '')}`;
    repoLink.target = '_blank';
    repoLink.rel = 'noopener noreferrer';
    repoLink.className = 'text-muted text-decoration-none small';
    repoLink.innerHTML = `<i class="fab fa-github me-1"></i>${escapeHtml(item.repository || 'Unknown')}`;
    
    repoDiv.appendChild(repoLink);
    contentDiv.appendChild(repoDiv);
    
    const timeDiv = document.createElement('div');
    timeDiv.className = 'text-muted small';
    timeDiv.innerHTML = `<i class="fas fa-clock me-1"></i>${formatDate(item.created_at)}`;
    contentDiv.appendChild(timeDiv);
    
    container.appendChild(contentDiv);
    
    if (isPullRequest && item.state) {
        const badgeDiv = document.createElement('div');
        badgeDiv.className = 'd-flex align-items-center';
        badgeDiv.innerHTML = getStateBadge(item.state);
        container.appendChild(badgeDiv);
    }
    
    itemLi.appendChild(container);
    return itemLi;
}

/**
 * Populates a list of issues or pull requests based on the provided items and identifier.
 *
 * This function first determines if the id corresponds to pull requests or issues.
 * It validates the items against the type, then updates a counter element if available.
 * If the item list is empty, it displays a message indicating no open issues or pull requests.
 * Otherwise, it sorts the items by creation date and appends them to the list with animations.
 *
 * @param items - An array of issue or pull request objects.
 * @param id - A string identifier for the container element.
 */
function populateIssues(items, id) {
    try {
        const isPullRequest = id === "openPullRequests";
        const type = isPullRequest ? "pull requests" : "issues";
        
        if (!validateItems(items, type)) {
            throw new Error(`Invalid ${type} data format`);
        }
        
        const counterElement = document.getElementById(`${id}Count`);
        if (counterElement) {
            animateCounter(counterElement, parseInt(counterElement.textContent) || 0, items.length);
        }
        
        const list = document.getElementById(id);
        if (!list) {
            throw new Error(`Container element #${id} not found`);
        }
        
        list.innerHTML = '';
        
        if (items.length === 0) {
            const emptyItem = document.createElement('li');
            emptyItem.className = 'list-group-item list-group-item-light text-center py-4';
            
            const iconClass = isPullRequest ? 'fa-code-branch' : 'fa-check-circle';
            const message = isPullRequest ? 'No open pull requests' : 'No open issues';
            const subMessage = isPullRequest ? 'All caught up with your pull requests!' : 'Great job keeping up with your issues!';
            
            emptyItem.innerHTML = `
                <i class="fas ${iconClass} text-success fa-2x mb-2"></i>
                <h6 class="text-muted mb-1">${message}</h6>
                <small class="text-muted">${subMessage}</small>
            `;
            
            list.appendChild(emptyItem);
            return;
        }
        
        const sortedItems = [...items].sort((a, b) => {
            const dateA = new Date(a.created_at || 0);
            const dateB = new Date(b.created_at || 0);
            return dateB - dateA;
        });
        
        sortedItems.forEach((item, index) => {
            const listItem = createListItem(item, isPullRequest);
            
            listItem.style.opacity = '0';
            listItem.style.transform = 'translateY(20px)';
            listItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            
            list.appendChild(listItem);
            
            setTimeout(() => {
                listItem.style.opacity = '1';
                listItem.style.transform = 'translateY(0)';
            }, index * 50);
        });
        
    } catch (error) {
        console.error(`Error populating ${id}:`, error);
        
        const list = document.getElementById(id);
        if (list) {
            list.innerHTML = `
                <li class="list-group-item list-group-item-danger text-center py-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> Failed to load data
                </li>
            `;
        }
    }
}

/**
 * Animates a counter from a start value to an end value over a specified duration.
 * Uses `requestAnimationFrame` for smooth animation and cubic easing for effect.
 *
 * @param {HTMLElement} element - The DOM element where the counter will be displayed.
 * @param {number} startValue - The initial value of the counter.
 * @param {number} endValue - The target value to animate towards.
 * @param {number} duration - The total time in milliseconds for the animation to complete. Defaults to 1500ms.
 */
function animateCounter(element, startValue, endValue, duration = 1500) {
    if (startValue === endValue) {
        element.textContent = endValue;
        return;
    }
    
    const startTime = performance.now();
    const difference = endValue - startValue;
    
    /**
     * Updates the counter element with an eased progress value based on elapsed time.
     */
    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const easedProgress = 1 - Math.pow(1 - progress, 3);
        const currentValue = Math.round(startValue + (difference * easedProgress));
        
        element.textContent = currentValue;
        
        if (progress < 1) {
            animationFrameId = requestAnimationFrame(updateCounter);
        }
    }
    
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
    }
    
    requestAnimationFrame(updateCounter);
}

/**
 * Initiates the process of loading data for the dashboard.
 *
 * This function checks if a dashboard update is already in progress and skips further execution if so.
 * It then clears any existing load timeouts, checks if the document is hidden, and proceeds to show loading indicators.
 * The function fetches dashboard data from an API endpoint, processes the response, and updates the UI accordingly.
 * In case of an error, it retries the load with exponential backoff until a maximum retry limit is reached.
 *
 * @returns {void}
 */
function loadData() {
    if (isDashboardUpdating) {
        console.log('Dashboard update already in progress, skipping...');
        return;
    }
    
    isDashboardUpdating = true;

    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
        loadDataTimeout = null;
    }
    
    if (document.hidden) {
        isDashboardUpdating = false;
        scheduleNextLoad();
        return;
    }
    
    showLoadingIndicator('openPullRequests');
    showLoadingIndicator('openIssues');
    
    fetch('/api/v1/dashboard')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response format');
            }
            
            if (!data.openPullRequestsDashboard && !data.openIssuesDashboard) {
                console.warn('No dashboard data found in response');
            }
            
            const pullRequests = data.openPullRequestsDashboard || [];
            const issues = data.openIssuesDashboard || [];
            
            populateIssues(pullRequests, "openPullRequests");
            populateIssues(issues, "openIssues");
            
            retryCount = 0;
            
            updateLastRefreshTime();
            
            scheduleNextLoad();
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
            
            retryCount++;
            const isRetriable = retryCount <= MAX_RETRIES;
            
            showErrorAlert(
                `Failed to load dashboard: ${error.message}`,
                isRetriable
            );
            
            showErrorState('openPullRequests', 'pull requests');
            showErrorState('openIssues', 'issues');
            
            if (isRetriable) {
                const delay = RETRY_DELAY * Math.pow(2, retryCount - 1);
                loadDataTimeout = setTimeout(() => {
                    isDashboardUpdating = false;
                    loadData();
                }, delay);
            } else {
                retryCount = 0;
                scheduleNextLoad();
            }
        })
        .finally(() => {
            isDashboardUpdating = false;
        });
}

/**
 * Displays an error message within a specified container element.
 */
function showErrorState(containerId, type) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = `
        <li class="list-group-item list-group-item-danger text-center py-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Failed to load ${type}</strong>
            <br><small class="text-muted">Please check your connection and try again</small>
        </li>
    `;
}

/**
 * Updates the last refresh time displayed on the page.
 */
function updateLastRefreshTime() {
    const refreshElement = document.getElementById('lastRefresh');
    if (refreshElement) {
        const now = new Date();
        refreshElement.textContent = `Last updated: ${now.toLocaleTimeString()}`;
        refreshElement.className = 'text-muted small fade-in';
    }
}

/**
 * Schedules the next data load if the document is not hidden.
 */
function scheduleNextLoad() {
    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
    }
    
    if (!document.hidden) {
        loadDataTimeout = setTimeout(loadData, UPDATE_INTERVAL);
    }
}

/**
 * Handles visibility change of the document, clearing timeout or loading data as needed.
 */
function handleVisibilityChange() {
    if (document.hidden) {
        if (loadDataTimeout) {
            clearTimeout(loadDataTimeout);
            loadDataTimeout = null;
        }
    } else {
        if (!isDashboardUpdating) {
            loadData();
        }
    }
}

/**
 * Clears timers and animation frames on page unload to prevent memory leaks.
 */
function handlePageUnload() {
    if (loadDataTimeout) {
        clearTimeout(loadDataTimeout);
        loadDataTimeout = null;
    }
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
}

/**
 * Initializes counter animations on elements with 'card-text' class and data-target attribute.
 */
function initializeCounterAnimations() {
    const counters = document.querySelectorAll('.card-text[data-target]');
    
    counters.forEach((counter, index) => {
        const target = parseInt(counter.getAttribute('data-target')) || 0;
        
        setTimeout(() => {
            animateCounter(counter, 0, target, 2000);
        }, index * 200);
    });
}

/**
 * Adds a click event listener to the refresh button that updates the dashboard.
 */
function addRefreshButton() {
    const refreshBtn = document.getElementById('refreshDashboard');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', (e) => {
            e.preventDefault();
            
            if (isDashboardUpdating) return;
            
            const originalHtml = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Refreshing...';
            refreshBtn.disabled = true;
            
            retryCount = 0;
            loadData();
            
            setTimeout(() => {
                refreshBtn.innerHTML = originalHtml;
                refreshBtn.disabled = false;
            }, 2000);
        });
    }
}

/**
 * Initializes event listeners for various document and window events.
 *
 * This function sets up several event listeners to handle different user interactions
 * and browser events such as visibility changes, page unloads, and key presses.
 * It also adds a refresh button and prevents default behavior for certain key combinations
 * while checking if the dashboard is not updating.
 */
function initializeEventListeners() {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('beforeunload', handlePageUnload);
    window.addEventListener('pagehide', handlePageUnload);
    
    addRefreshButton();
    
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'r' && !isDashboardUpdating) {
            e.preventDefault();
            loadData();
        }
    });
}

/**
 * Initializes the Enhanced Dashboard by checking for required DOM elements, setting up event listeners,
 * animations, and loading data.
 */
function initialize() {
    console.log('Initializing Enhanced Dashboard...');
    
    const requiredElements = ['openPullRequests', 'openIssues'];
    const missingElements = requiredElements.filter(id => !document.getElementById(id));
    
    if (missingElements.length > 0) {
        console.error('Missing required DOM elements:', missingElements);
        showErrorAlert('Dashboard not properly initialized. Please refresh the page.');
        return;
    }
    
    initializeEventListeners();
    
    initializeCounterAnimations();
    
    loadData();
    
    console.log('Dashboard initialization complete');
}

/**
 * Executes a callback function when the DOM is fully loaded.
 */
function onDOMReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

onDOMReady(initialize);

window.DashboardManager = {
    refresh: loadData,
    isUpdating: () => isDashboardUpdating,
    getLastUpdateTime: () => new Date().toISOString()
};
