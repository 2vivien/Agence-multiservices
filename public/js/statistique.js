document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication and role
    if (typeof checkAuthStatusAndRedirect !== 'function' || typeof logoutUser !== 'function') {
        console.error('common.js is not loaded or essential functions are missing.');
        alert('Erreur critique: Fichiers de base manquants.');
        return;
    }
    checkAuthStatusAndRedirect();

    const userRole = localStorage.getItem('userRole');
    const isAdmin = userRole === 'admin';
    const currentUserId = localStorage.getItem('userId'); // Assuming userId is stored from login

    // Chart instances store
    let charts = {};

    // DOM Elements for filters
    const dateFromInput = document.getElementById('filter-date-from');
    const dateToInput = document.getElementById('filter-date-to');
    const userIdSelect = document.getElementById('filter-user-id'); // Admin only
    const serviceIdSelect = document.getElementById('filter-service-id'); // For service-based stats
    const applyFiltersButton = document.getElementById('apply-filters-btn');

    // DOM Elements for displaying stats
    // Overall Stats (Admin only)
    const totalOperationsEl = document.getElementById('total-operations-count');
    const totalTurnoverEl = document.getElementById('total-turnover-amount');
    const activeGerantsEl = document.getElementById('active-gerants-count');
    const activeServicesEl = document.getElementById('active-services-count');
    const overallStatsSection = document.getElementById('overall-stats-section');

    // Service Stats
    const serviceStatsChartCanvas = document.getElementById('service-stats-chart');
    const serviceStatsSection = document.getElementById('service-stats-section');

    // Financial Summary
    const financialSummaryChartCanvas = document.getElementById('financial-summary-chart');
    const financialSummarySection = document.getElementById('financial-summary-section');

    // User Activity (Admin only)
    const userActivityChartCanvas = document.getElementById('user-activity-chart');
    const userActivitySection = document.getElementById('user-activity-section');

    // Loading indicators
    const overallLoading = document.getElementById('overall-stats-loading');
    const serviceLoading = document.getElementById('service-stats-loading');
    const financialLoading = document.getElementById('financial-summary-loading');
    const userActivityLoading = document.getElementById('user-activity-loading');


    /**
     * Initialize page: setup UI based on role, load initial data.
     */
    async function initializePage() {
        setupRoleBasedUI();
        await loadFilterPrerequisites(); // Load users and services for filter dropdowns
        setDefaultDates();

        if (applyFiltersButton) {
            applyFiltersButton.addEventListener('click', loadAllStats);
        }
        loadAllStats(); // Initial load
    }

    function setDefaultDates() {
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        if (dateFromInput) dateFromInput.valueAsDate = firstDayOfMonth;
        if (dateToInput) dateToInput.valueAsDate = today;
    }

    async function loadFilterPrerequisites() {
        // For admin, load list of gérants for user_id select
        if (isAdmin && userIdSelect) {
            try {
                // Assuming an endpoint like /api/users?role=gerant or similar
                // For now, this part is a placeholder as the endpoint isn't defined in this task set.
                // const response = await fetch('/api/users?role=gerant&active=true');
                // const gerants = await response.json();
                // populateSelect(userIdSelect, gerants, 'Tous les gérants', 'id', 'full_name');
                userIdSelect.innerHTML = '<option value="">Tous les gérants (Placeholder)</option>';
            } catch (e) { console.error("Failed to load users for filter", e); }
        }

        // Load services for service_id select
        if (serviceIdSelect) {
            try {
                const response = await fetch('/api/services');
                const services = await response.json();
                populateSelect(serviceIdSelect, services, 'Tous les services');
            } catch (e) { console.error("Failed to load services for filter", e); }
        }
    }


    function setupRoleBasedUI() {
        if (!isAdmin) {
            if (overallStatsSection) overallStatsSection.style.display = 'none';
            if (userActivitySection) userActivitySection.style.display = 'none';
            if (userIdSelect) userIdSelect.parentElement.style.display = 'none'; // Hide user filter for non-admins
        }
    }

    function getFilterValues() {
        const filters = {};
        if (dateFromInput && dateFromInput.value) filters.date_from = dateFromInput.value;
        if (dateToInput && dateToInput.value) filters.date_to = dateToInput.value;
        if (isAdmin && userIdSelect && userIdSelect.value) filters.user_id = userIdSelect.value;
        if (serviceIdSelect && serviceIdSelect.value) filters.service_id = serviceIdSelect.value;
        return filters;
    }

    async function loadAllStats() {
        const filters = getFilterValues();
        const queryParams = new URLSearchParams(filters).toString();

        if (isAdmin) {
            fetchOverallStats(queryParams);
            fetchUserActivityStats(queryParams);
        }
        fetchServiceStats(queryParams);
        fetchFinancialSummary(queryParams);
    }

    async function fetchOverallStats(queryParams) {
        if (!isAdmin || !overallStatsSection) return;
        if (overallLoading) overallLoading.style.display = 'block';
        try {
            const response = await fetch(`/api/stats/overall?${queryParams}`);
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            const result = await response.json();
            renderOverallStats(result.data);
        } catch (error) {
            console.error('Error fetching overall stats:', error);
            if (totalOperationsEl) totalOperationsEl.textContent = 'Erreur';
        } finally {
            if (overallLoading) overallLoading.style.display = 'none';
        }
    }

    function renderOverallStats(data) {
        if (!data) return;
        if (totalOperationsEl) totalOperationsEl.textContent = data.total_operations || '0';
        if (totalTurnoverEl) totalTurnoverEl.textContent = formatCurrency(data.total_turnover || 0);
        if (activeGerantsEl) activeGerantsEl.textContent = data.active_gerants || '0';
        if (activeServicesEl) activeServicesEl.textContent = data.active_services || '0';
    }

    async function fetchServiceStats(queryParams) {
        if (!serviceStatsSection) return;
        if (serviceLoading) serviceLoading.style.display = 'block';
        try {
            const response = await fetch(`/api/stats/services?${queryParams}`);
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            const result = await response.json();
            // Assuming result.data is an array like: [{service_name, operation_count, total_amount}]
            renderServiceChart(result.data);
        } catch (error) {
            console.error('Error fetching service stats:', error);
             if(serviceStatsChartCanvas) serviceStatsChartCanvas.parentElement.innerHTML = '<p class="text-red-500">Erreur de chargement.</p>';
        } finally {
            if (serviceLoading) serviceLoading.style.display = 'none';
        }
    }

    function renderServiceChart(data) {
        if (!serviceStatsChartCanvas || !data) return;
        const ctx = serviceStatsChartCanvas.getContext('2d');

        const labels = data.map(item => item.service_name);
        const operationCounts = data.map(item => item.operation_count);
        const totalAmounts = data.map(item => item.total_amount);

        if (charts.serviceStats) charts.serviceStats.destroy();
        charts.serviceStats = new Chart(ctx, {
            type: 'bar', // or 'pie' / 'doughnut'
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Nombre d\'Opérations',
                        data: operationCounts,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'yOps',
                    },
                    {
                        label: 'Montant Total (FCFA)',
                        data: totalAmounts,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        yAxisID: 'yAmount',
                        type: 'line', // Can mix chart types
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yOps: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Nombre d\'Opérations'}
                    },
                    yAmount: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Montant Total (FCFA)'},
                        grid: { drawOnChartArea: false } // only want the grid lines for one axis to show up
                    }
                }
            }
        });
    }

    async function fetchFinancialSummary(queryParams) {
        if (!financialSummarySection) return;
        if (financialLoading) financialLoading.style.display = 'block';
        try {
            const response = await fetch(`/api/stats/financial-summary?${queryParams}`);
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            const result = await response.json();
            // Assuming result.data is {total_deposits, total_withdrawals, total_commissions_earned}
            renderFinancialSummaryChart(result.data);
        } catch (error) {
            console.error('Error fetching financial summary:', error);
            if(financialSummaryChartCanvas) financialSummaryChartCanvas.parentElement.innerHTML = '<p class="text-red-500">Erreur de chargement.</p>';
        } finally {
            if (financialLoading) financialLoading.style.display = 'none';
        }
    }

    function renderFinancialSummaryChart(data) {
        if (!financialSummaryChartCanvas || !data) return;
        const ctx = financialSummaryChartCanvas.getContext('2d');

        if (charts.financialSummary) charts.financialSummary.destroy();
        charts.financialSummary = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Total Dépôts', 'Total Retraits', 'Total Commissions'],
                datasets: [{
                    label: 'Résumé Financier (FCFA)',
                    data: [data.total_deposits || 0, data.total_withdrawals || 0, data.total_commissions_earned || 0],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.7)', // Greenish
                        'rgba(255, 99, 132, 0.7)',  // Reddish
                        'rgba(255, 206, 86, 0.7)'   // Yellowish
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } }
            }
        });
    }

    async function fetchUserActivityStats(queryParams) {
        if (!isAdmin || !userActivitySection) return;
        if (userActivityLoading) userActivityLoading.style.display = 'block';
        try {
            const response = await fetch(`/api/stats/user-activity?${queryParams}`);
             if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            const result = await response.json();
            // Assuming result.data is an array like: [{user_full_name, total_ops, last_activity_date}]
            renderUserActivityChart(result.data);
        } catch (error) {
            console.error('Error fetching user activity stats:', error);
            if(userActivityChartCanvas) userActivityChartCanvas.parentElement.innerHTML = '<p class="text-red-500">Erreur de chargement.</p>';
        } finally {
            if (userActivityLoading) userActivityLoading.style.display = 'none';
        }
    }

    function renderUserActivityChart(data) {
        if (!userActivityChartCanvas || !data) return;
        const ctx = userActivityChartCanvas.getContext('2d');

        const labels = data.map(item => item.user_full_name); // Or username
        const operationCounts = data.map(item => item.total_ops);

        if (charts.userActivity) charts.userActivity.destroy();
        charts.userActivity = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Nombre d\'Opérations par Gérant',
                    data: operationCounts,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: {display: true, text: 'Nombre d\'opérations'} } }
            }
        });
    }

    // Helper to populate select options
    function populateSelect(selectElement, items, defaultOptionText, valueKey = 'id', textKey = 'name') {
        if (!selectElement) return;
        selectElement.innerHTML = `<option value="">${defaultOptionText}</option>`;
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = item[textKey];
            selectElement.appendChild(option);
        });
    }

    // Helper to format currency
    function formatCurrency(amount) {
        return amount !== null && amount !== undefined ? new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount) : 'N/A';
    }

    // Initialize
    initializePage();
});
