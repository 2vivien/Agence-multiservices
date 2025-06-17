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
