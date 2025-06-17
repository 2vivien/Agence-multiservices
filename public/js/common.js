/**
 * Checks if the user is authenticated by looking at localStorage.
 * If not authenticated and not on the login page, redirects to index.html.
 */
function checkAuthStatusAndRedirect() {
    const isAuthenticated = localStorage.getItem('isAuthenticated');
    const onLoginPage = window.location.pathname.endsWith('index.html') || window.location.pathname === '/';

    if (!isAuthenticated && !onLoginPage) {
        console.log('User not authenticated. Redirecting to login page.');
        window.location.href = 'index.html'; // Adjust if your login page has a different name or path
    } else if (isAuthenticated && onLoginPage) {
        // Optional: If user is authenticated and somehow lands on login page, redirect to their dashboard
        const userRole = localStorage.getItem('userRole');
        console.log('User authenticated and on login page. Redirecting to dashboard.');
        if (userRole === 'admin') {
            window.location.href = 'admin-dashboard.html';
        } else if (userRole === 'gerant') {
            window.location.href = 'gerant-dashboard.html';
        } else {
            // Fallback if role is not set or unknown, perhaps stay or redirect to a generic page
            // For now, let them stay on index.html or redirect to a default dashboard if one exists
        }
    }
}

/**
 * Logs out the current user.
 * This involves calling the /api/logout endpoint, clearing localStorage,
 * and redirecting to the login page.
 */
async function logoutUser() {
    console.log('Logging out user...');
    try {
        const response = await fetch('/api/logout', {
            method: 'POST', // Or 'GET', depending on your backend route definition
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Important for backend to recognize AJAX if needed
            }
        });

        // We assume the backend will attempt to clear the session regardless of response status here.
        // If the call fails, we still want to clear client-side authentication.
        if (!response.ok) {
            console.warn('Logout API call failed or returned an error status:', response.status);
            // Optionally, display a message to the user, but still proceed with client-side logout
        }

        const data = await response.json(); // Try to parse JSON even if not ok, backend might send error details
        console.log('Logout API response:', data);

    } catch (error) {
        console.error('Error during logout API call:', error);
        // Still proceed with client-side logout
    } finally {
        // Clear client-side authentication details
        localStorage.removeItem('isAuthenticated');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userName');
        // Potentially remove other user-related items from localStorage

        console.log('Client-side authentication cleared. Redirecting to login page.');
        // Redirect to login page
        window.location.href = 'index.html'; // Adjust if your login page is different
    }
}

// Example of how to attach logout to a button (this would typically be in dashboard-specific JS)
// document.addEventListener('DOMContentLoaded', () => {
//     const logoutButton = document.getElementById('logoutButton'); // Assuming a button with this ID exists
//     if (logoutButton) {
//         logoutButton.addEventListener('click', (e) => {
//             e.preventDefault();
//             logoutUser();
//         });
//     }
// });

// Global check (optional - can be called specifically in protected pages)
// checkAuthStatusAndRedirect();
// Note: Calling checkAuthStatusAndRedirect() globally might not be suitable for all pages (e.g. index.html itself).
// It's better to call it explicitly at the start of scripts for protected pages.


/**
 * Displays a global notification message.
 * @param {string} message The message to display.
 * @param {string} type 'success', 'error', 'info', or 'warning'.
 */
function showGlobalNotification(message, type = 'info') {
    const container = document.getElementById('global-notification-container') || createNotificationContainer();

    const notification = document.createElement('div');
    notification.className = `p-4 mb-4 rounded-md text-sm`;

    let bgColor, textColor, borderColor, iconClass;

    switch (type) {
        case 'success':
            bgColor = 'bg-green-100';
            textColor = 'text-green-700';
            borderColor = 'border-green-500';
            iconClass = 'fas fa-check-circle';
            break;
        case 'error':
            bgColor = 'bg-red-100';
            textColor = 'text-red-700';
            borderColor = 'border-red-500';
            iconClass = 'fas fa-exclamation-circle';
            break;
        case 'warning':
            bgColor = 'bg-yellow-100';
            textColor = 'text-yellow-700';
            borderColor = 'border-yellow-500';
            iconClass = 'fas fa-exclamation-triangle';
            break;
        case 'info':
        default:
            bgColor = 'bg-blue-100';
            textColor = 'text-blue-700';
            borderColor = 'border-blue-500';
            iconClass = 'fas fa-info-circle';
            break;
    }

    notification.classList.add(bgColor, textColor, borderColor, 'border-l-4');
    notification.innerHTML = `<i class="${iconClass} mr-2"></i> ${message}`;

    container.appendChild(notification);

    // Auto-dismiss
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s ease';
        setTimeout(() => notification.remove(), 500);
    }, 5000); // Dismiss after 5 seconds

    // Allow manual dismiss
    notification.addEventListener('click', () => {
        notification.remove();
    });
}

function createNotificationContainer() {
    let container = document.getElementById('global-notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'global-notification-container';
        container.className = 'fixed top-5 right-5 z-50 w-full max-w-sm';
        document.body.appendChild(container);
    }
    return container;
}


/**
 * Handles common API error responses.
 * @param {Response} response The Fetch API response object.
 * @param {string} [targetErrorElementId] Optional ID of an element to display specific 500/400 errors.
 * @returns {Promise<boolean>} True if error was handled (e.g. redirect), false otherwise (caller might do more).
 */
async function handleApiError(response, targetErrorElementId = null) {
    if (response.status === 401) {
        showGlobalNotification('Session expirée ou non authentifié. Redirection...', 'error');
        // Delay logoutUser slightly to allow notification to be seen if possible
        setTimeout(() => logoutUser(), 1500);
        return true;
    }
    if (response.status === 403) {
        // It's better to have the calling page redirect to acces.html if it's a page-level access issue.
        // For API calls within an authorized page, a global notification might be better than redirect.
        // For now, redirect as per original plan.
        showGlobalNotification('Accès refusé à cette ressource ou action.', 'error');
        // Consider if acces.html should take a message. For now, generic.
        setTimeout(() => window.location.href = 'acces.html', 1500);
        return true;
    }

    // For other errors (400, 404, 422 not handled by caller, 500)
    // 422 should ideally be handled by caller to display field-specific errors.
    if (!response.ok) {
        let errorMsg = `Erreur ${response.status}: ${response.statusText}`;
        try {
            const errorData = await response.json();
            if (errorData && errorData.error) {
                errorMsg = errorData.error;
            } else if (errorData && errorData.errors && typeof errorData.errors === 'object') {
                // For 422 errors, join messages if not handled by caller
                errorMsg = Object.values(errorData.errors).flat().join('; ');
            } else if (errorData && errorData.message) {
                 errorMsg = errorData.message;
            }
        } catch (e) {
            // Failed to parse JSON, use statusText
        }

        if (targetErrorElementId) {
            const errorEl = document.getElementById(targetErrorElementId);
            if (errorEl) {
                errorEl.textContent = errorMsg;
                errorEl.classList.remove('hidden'); // Assuming it's hidden by default
            } else {
                 showGlobalNotification(errorMsg, 'error');
            }
        } else {
            showGlobalNotification(errorMsg, 'error');
        }
        return true; // Error was displayed
    }
    return false; // No error handled by this function
}

/**
 * Formats a number as currency (FCFA example).
 * @param {number} amount The amount to format.
 * @returns {string} The formatted currency string or 'N/A'.
 */
function formatCurrency(amount) {
    if (amount === null || amount === undefined || isNaN(parseFloat(amount))) {
        return 'N/A';
    }
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount);
}

/**
 * Validates an email address.
 * @param {string} email The email to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function isValidEmail(email) {
    if (!email || typeof email !== 'string') {
        return false;
    }
    // Basic regex for email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}
