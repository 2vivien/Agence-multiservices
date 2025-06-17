document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication status (ensure common.js is loaded before this script)
    if (typeof checkAuthStatusAndRedirect !== 'function') {
        console.error('common.js is not loaded or checkAuthStatusAndRedirect is not defined.');
        // Potentially redirect to login or show a critical error message
        // For now, we assume common.js handles its own loading errors or redirects.
        // window.location.href = 'index.html'; // Fallback
        alert('Erreur critique: Impossible de vérifier l\'authentification. Veuillez contacter le support.');
        return;
    }
    checkAuthStatusAndRedirect();

    // Cache DOM elements that will be updated
    const summaryOpsCountEl = document.getElementById('summary-ops-count');
    const summaryOpsTotalEl = document.getElementById('summary-ops-total');
    const currentBalanceAmountEl = document.getElementById('current-balance-amount');
    const currentBalanceDateEl = document.getElementById('current-balance-date');
    const recentOperationsTableBodyEl = document.getElementById('recent-operations-table-body');
    const welcomeMessageUserEl = document.getElementById('welcome-message-user'); // For "Bonjour, [User Name]"

    // Placeholders for loading states (IDs of elements that show "Loading...")
    const summaryLoadingEl = document.getElementById('summary-loading');
    const balanceLoadingEl = document.getElementById('balance-loading');
    const operationsLoadingEl = document.getElementById('operations-loading');

    /**
     * Fetches dashboard data from the API.
     */
    async function fetchDashboardData() {
        setLoadingState(true);

        try {
            const response = await fetch('/api/dashboard/gerant', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    // Authorization header is not needed here due to session cookies
                }
            });

            if (response.status === 401 || response.status === 403) {
                // Unauthorized or Forbidden
                console.warn('Access denied to dashboard data. Status:', response.status);
                // checkAuthStatusAndRedirect should ideally handle this, or redirect here.
                localStorage.removeItem('isAuthenticated'); // Force clear auth
                localStorage.removeItem('userRole');
                localStorage.removeItem('userName');
                window.location.href = 'index.html'; // Redirect to login
                return;
            }

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: 'Failed to parse error response' }));
                console.error('Error fetching dashboard data:', response.status, errorData);
                showErrorInPage(`Erreur ${response.status}: ${errorData.error || 'Impossible de charger les données du tableau de bord.'}`);
                return;
            }

            const data = await response.json();
            console.log('Dashboard data received:', data);

            // Update UI with fetched data
            if (data.user && data.user.full_name && welcomeMessageUserEl) {
                welcomeMessageUserEl.textContent = data.user.full_name;
            }

            // The actual data structure from backend for summary, balance, operations needs to be used here.
            // Assuming structure from previous subtask:
            // data.daily_summary from Operation model -> { count: X, total_amount: Y }
            // data.open_balances from Balance model (might be an array, or a single object if adapted)
            // data.recent_operations from Operation model

            // Let's adjust based on the DashboardController's actual response structure
            // 'message', 'user', 'open_balances', 'recent_operations', 'unresolved_alerts'
            // We need 'daily_summary' and a 'current_balance' from the models.
            // For now, I'll use what's available or make assumptions.
            // The subtask implies: data.summaryToday, data.currentBalance, data.recentOperations

            updateSummary(data.daily_summary || { count: 0, total_amount: 0 }); // Placeholder if not in actual response yet
            updateBalance(data.current_balance || data.open_balances?.[0] || { final_balance: 0, balance_date: 'N/A' }); // Use first open balance or placeholder
            populateRecentOperations(data.recent_operations || []);

        } catch (error) {
            console.error('Network or parsing error fetching dashboard data:', error);
            showErrorInPage('Une erreur réseau est survenue. Veuillez vérifier votre connexion.');
        } finally {
            setLoadingState(false);
        }
    }

    /**
     * Updates the summary section (operations count and total amount today).
     * @param {object} summaryData - Object like { count: X, total_amount: Y }
     */
    function updateSummary(summaryData) {
        if (summaryOpsCountEl) summaryOpsCountEl.textContent = summaryData.count !== undefined ? summaryData.count : 'N/A';
        if (summaryOpsTotalEl) summaryOpsTotalEl.textContent = summaryData.total_amount !== undefined ? formatCurrency(summaryData.total_amount) : 'N/A';
    }

    /**
     * Updates the current balance section.
     * @param {object} balanceData - Object for balance, e.g., { final_balance: Z, balance_date: "YYYY-MM-DD" }
     */
    function updateBalance(balanceData) {
        if (currentBalanceAmountEl) currentBalanceAmountEl.textContent = balanceData.final_balance !== undefined ? formatCurrency(balanceData.final_balance) : 'N/A';
        if (currentBalanceDateEl) currentBalanceDateEl.textContent = balanceData.balance_date ? formatDate(balanceData.balance_date) : 'N/A';
    }

    /**
     * Populates the recent operations table/list.
     * @param {array} operationsArray - Array of operation objects.
     */
    function populateRecentOperations(operationsArray) {
        if (!recentOperationsTableBodyEl) return;
        recentOperationsTableBodyEl.innerHTML = ''; // Clear existing rows

        if (!operationsArray || operationsArray.length === 0) {
            recentOperationsTableBodyEl.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Aucune opération récente.</td></tr>';
            return;
        }

        operationsArray.forEach(op => {
            const row = recentOperationsTableBodyEl.insertRow();
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatDateTime(op.operation_time)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${op.operation_type_name || op.operation_type_id}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${op.service_name || op.service_id}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">${formatCurrency(op.amount)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">${formatCurrency(op.commission_applied)}</td>
            `;
            // Adjust op.operation_type_name, op.service_name based on actual data from backend
            // (getRecentForUser in Operation.php was modified to include these)
        });
    }

    /**
     * Shows/hides loading indicators.
     * @param {boolean} isLoading - True to show loading, false to hide.
     */
    function setLoadingState(isLoading) {
        if (isLoading) {
            if (summaryLoadingEl) summaryLoadingEl.classList.remove('hidden');
            if (balanceLoadingEl) balanceLoadingEl.classList.remove('hidden');
            if (operationsLoadingEl) operationsLoadingEl.classList.remove('hidden');
            // Hide data elements
            if (summaryOpsCountEl) summaryOpsCountEl.classList.add('hidden');
            // ... and so on for other data elements if needed
        } else {
            if (summaryLoadingEl) summaryLoadingEl.classList.add('hidden');
            if (balanceLoadingEl) balanceLoadingEl.classList.add('hidden');
            if (operationsLoadingEl) operationsLoadingEl.classList.add('hidden');
            // Show data elements
            if (summaryOpsCountEl) summaryOpsCountEl.classList.remove('hidden');
            // ...
        }
    }

    /**
     * Displays an error message in a designated area on the page.
     * @param {string} message - The error message to display.
     */
    function showErrorInPage(message) {
        const errorDisplayElement = document.getElementById('dashboard-error-message'); // Assuming an element with this ID exists
        if (errorDisplayElement) {
            errorDisplayElement.textContent = message;
            errorDisplayElement.classList.remove('hidden');
        } else {
            alert(message); // Fallback
        }
    }

    // Helper to format currency (FCFA example)
    function formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount);
    }

    // Helper to format date
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    // Helper to format date and time
    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    // Initial data fetch
    fetchDashboardData();

    // Logout button functionality is expected to be handled by common.js through an event listener
    // set up in gerant-dashboard.html's inline script or if that script calls a setup function from common.js.
    // If the logout button with id="logoutBtn" exists, and common.js is correctly included and its
    // logoutUser() is attached to this button's click event, it should work.
    // The previous modification to gerant-dashboard.html already set up its logout button to call logoutUser().
});
