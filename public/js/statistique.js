document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication and role
    if (typeof checkAuthStatusAndRedirect !== 'function' || typeof logoutUser !== 'function' || typeof showGlobalNotification !== 'function' || typeof handleApiError !== 'function') {
        console.error('common.js is not loaded or essential functions are missing.');
        alert('Erreur critique: Fichiers de base manquants.');
        return;
    }
    checkAuthStatusAndRedirect();

    const userRole = localStorage.getItem('userRole');
    const isAdmin = userRole === 'admin';
    // const currentUserId = localStorage.getItem('userId'); // Not directly used in this version of stats for filtering client-side

    // Chart instances store
    let charts = {};

    // DOM Elements for filters
    const dateFromInput = document.getElementById('filter-date-from');
    const dateToInput = document.getElementById('filter-date-to');
    const userIdSelect = document.getElementById('filter-user-id');
    const serviceIdSelect = document.getElementById('filter-service-id');
    const applyFiltersButton = document.getElementById('apply-filters-btn');

    // DOM Elements for displaying stats & export buttons
    const totalOperationsEl = document.getElementById('total-operations-count');
    const totalTurnoverEl = document.getElementById('total-turnover-amount');
    const activeGerantsEl = document.getElementById('active-gerants-count');
    const activeServicesEl = document.getElementById('active-services-count');
    const overallStatsSection = document.getElementById('overall-stats-section');

    const serviceStatsChartCanvas = document.getElementById('service-stats-chart');
    const serviceStatsSection = document.getElementById('service-stats-section');
    const exportServiceStatsPdfBtn = document.getElementById('export-stats-service-pdf-btn');
    const exportServiceStatsExcelBtn = document.getElementById('export-stats-service-excel-btn');

    const financialSummaryChartCanvas = document.getElementById('financial-summary-chart');
    const financialSummarySection = document.getElementById('financial-summary-section');
    const exportFinancialPdfBtn = document.getElementById('export-stats-financial-pdf-btn');
    const exportFinancialExcelBtn = document.getElementById('export-stats-financial-excel-btn');

    const userActivityChartCanvas = document.getElementById('user-activity-chart');
    const userActivitySection = document.getElementById('user-activity-section');
    const exportUserActivityPdfBtn = document.getElementById('export-stats-user-activity-pdf-btn');
    const exportUserActivityExcelBtn = document.getElementById('export-stats-user-activity-excel-btn');

    // Loading indicators
    const overallLoading = document.getElementById('overall-stats-loading');
    const serviceLoading = document.getElementById('service-stats-loading');
    const financialLoading = document.getElementById('financial-summary-loading');
    const userActivityLoading = document.getElementById('user-activity-loading');

    async function initializePage() {
        setupRoleBasedUI();
        await loadFilterPrerequisites();
        setDefaultDates();

        if (applyFiltersButton) {
            applyFiltersButton.addEventListener('click', loadAllStats);
        }
        // Attach export button listeners
        if (exportServiceStatsPdfBtn) exportServiceStatsPdfBtn.addEventListener('click', () => handleGenericExport('services', 'pdf'));
        if (exportServiceStatsExcelBtn) exportServiceStatsExcelBtn.addEventListener('click', () => handleGenericExport('services', 'excel'));
        if (exportFinancialPdfBtn) exportFinancialPdfBtn.addEventListener('click', () => handleGenericExport('financial-summary', 'pdf'));
        if (exportFinancialExcelBtn) exportFinancialExcelBtn.addEventListener('click', () => handleGenericExport('financial-summary', 'excel'));
        if (isAdmin) {
            if (exportUserActivityPdfBtn) exportUserActivityPdfBtn.addEventListener('click', () => handleGenericExport('user-activity', 'pdf'));
            if (exportUserActivityExcelBtn) exportUserActivityExcelBtn.addEventListener('click', () => handleGenericExport('user-activity', 'excel'));
        }

        loadAllStats();
    }

    function setDefaultDates() {
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        if (dateFromInput) dateFromInput.valueAsDate = firstDayOfMonth;
        if (dateToInput) dateToInput.valueAsDate = today;
    }

    async function loadFilterPrerequisites() {
        if (isAdmin && userIdSelect) {
            try {
                 const usersResponse = await fetch('/api/admin/users?role=gerant&limit=1000', { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                 if(usersResponse.ok){
                    const usersResult = await usersResponse.json();
                    const gerants = (usersResult.data || usersResult).filter(u => u.role === 'gerant');
                    populateSelect(userIdSelect, gerants, 'Tous les gérants', 'id', 'full_name');
                 } else { console.error("Failed to load users for filter", usersResponse.status); }
            } catch (e) { console.error("Error fetching users for filter", e); }
        }

        if (serviceIdSelect) {
            try {
                const response = await fetch('/api/services', { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                if(response.ok) {
                    const services = await response.json();
                    populateSelect(serviceIdSelect, services, 'Tous les services');
                } else { console.error("Failed to load services for filter", response.status); }
            } catch (e) { console.error("Error fetching services for filter", e); }
        }
    }

    function setupRoleBasedUI() {
        if (!isAdmin) {
            if (overallStatsSection) overallStatsSection.style.display = 'none';
            if (userActivitySection) userActivitySection.style.display = 'none';
            if (userIdSelect && userIdSelect.parentElement) userIdSelect.parentElement.style.display = 'none';
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
            const response = await fetch(`/api/stats/overall?${queryParams}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (!response.ok) {
                await handleApiError(response);
                renderOverallStats(null);
            } else {
                const result = await response.json();
                renderOverallStats(result.data);
            }
        } catch (error) {
            console.error('Error fetching overall stats:', error);
            showGlobalNotification('Erreur réseau: statistiques générales.', 'error');
            renderOverallStats(null);
        } finally {
            if (overallLoading) overallLoading.style.display = 'none';
        }
    }

    function renderOverallStats(data) {
        const na = 'N/A';
        if (totalOperationsEl) totalOperationsEl.textContent = data?.total_operations !== undefined ? data.total_operations : na;
        if (totalTurnoverEl) totalTurnoverEl.textContent = data?.total_turnover !== undefined ? formatCurrency(data.total_turnover) : na;
        if (activeGerantsEl) activeGerantsEl.textContent = data?.active_gerants !== undefined ? data.active_gerants : na;
        if (activeServicesEl) activeServicesEl.textContent = data?.active_services !== undefined ? data.active_services : na;
    }

    async function fetchServiceStats(queryParams) {
        if (!serviceStatsSection) return;
        if (serviceLoading) serviceLoading.style.display = 'block';
        try {
            const response = await fetch(`/api/stats/services?${queryParams}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (!response.ok) {
                await handleApiError(response);
                if(serviceStatsChartCanvas) serviceStatsChartCanvas.parentElement.innerHTML = '<p class="text-red-500 text-center py-4">Erreur de chargement des données.</p><canvas id="service-stats-chart"></canvas>';
            } else {
                const result = await response.json();
                renderServiceChart(result.data);
            }
        } catch (error) {
            console.error('Error fetching service stats:', error);
            showGlobalNotification('Erreur réseau: statistiques par service.', 'error');
            if(serviceStatsChartCanvas) serviceStatsChartCanvas.parentElement.innerHTML = '<p class="text-red-500 text-center py-4">Erreur de chargement des données.</p><canvas id="service-stats-chart"></canvas>';
        } finally {
            if (serviceLoading) serviceLoading.style.display = 'none';
        }
    }

    function renderServiceChart(data) {
        if (!serviceStatsChartCanvas) return;
        const ctx = serviceStatsChartCanvas.getContext('2d');
        if (!data || data.length === 0) {
            if (charts.serviceStats) charts.serviceStats.destroy();
            ctx.clearRect(0, 0, serviceStatsChartCanvas.width, serviceStatsChartCanvas.height);
             // Ensure canvas is not removed, just content
            const parent = serviceStatsChartCanvas.parentElement;
            if (parent.querySelector('p.empty-message')) parent.querySelector('p.empty-message').remove();
            const p = document.createElement('p');
            p.className = 'text-gray-500 text-center py-4 empty-message';
            p.textContent = 'Aucune donnée disponible pour les filtres sélectionnés.';
            parent.insertBefore(p, serviceStatsChartCanvas);
            return;
        } else {
            const parent = serviceStatsChartCanvas.parentElement;
            if (parent.querySelector('p.empty-message')) parent.querySelector('p.empty-message').remove();
        }

        const labels = data.map(item => item.service_name);
        const operationCounts = data.map(item => item.operation_count);
        const totalAmounts = data.map(item => item.total_amount);

        if (charts.serviceStats) charts.serviceStats.destroy();
        charts.serviceStats = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: [
                    { label: 'Nombre d\'Opérations', data: operationCounts, backgroundColor: 'rgba(54, 162, 235, 0.6)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1, yAxisID: 'yOps'},
                    { label: 'Montant Total (FCFA)', data: totalAmounts, backgroundColor: 'rgba(75, 192, 192, 0.6)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1, yAxisID: 'yAmount', type: 'line', tension: 0.1 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: {
                    yOps: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Nombre d\'Opérations'} },
                    yAmount: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Montant Total (FCFA)'}, grid: { drawOnChartArea: false } }
                }
            }
        });
    }

    async function fetchFinancialSummary(queryParams) {
        if (!financialSummarySection) return;
        if (financialLoading) financialLoading.style.display = 'block';
        try {
            const response = await fetch(`/api/stats/financial-summary?${queryParams}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (!response.ok) {
                await handleApiError(response);
                if(financialSummaryChartCanvas) financialSummaryChartCanvas.parentElement.innerHTML = '<p class="text-red-500 text-center py-4">Erreur de chargement des données.</p><canvas id="financial-summary-chart"></canvas>';
            } else {
                const result = await response.json();
                renderFinancialSummaryChart(result.data);
            }
        } catch (error) {
            console.error('Error fetching financial summary:', error);
            showGlobalNotification('Erreur réseau: résumé financier.', 'error');
            if(financialSummaryChartCanvas) financialSummaryChartCanvas.parentElement.innerHTML = '<p class="text-red-500 text-center py-4">Erreur de chargement des données.</p><canvas id="financial-summary-chart"></canvas>';
        } finally {
            if (financialLoading) financialLoading.style.display = 'none';
        }
    }

    function renderFinancialSummaryChart(data) {
        if (!financialSummaryChartCanvas) return;
        const ctx = financialSummaryChartCanvas.getContext('2d');
         if (!data || ( (data.total_deposits === 0 || data.total_deposits === undefined) && (data.total_withdrawals === 0 || data.total_withdrawals === undefined) && (data.total_commissions_earned === 0 || data.total_commissions_earned === undefined) ) ) {
            if (charts.financialSummary) charts.financialSummary.destroy();
            ctx.clearRect(0, 0, financialSummaryChartCanvas.width, financialSummaryChartCanvas.height);
            const parent = financialSummaryChartCanvas.parentElement;
            if (parent.querySelector('p.empty-message')) parent.querySelector('p.empty-message').remove();
            const p = document.createElement('p');
            p.className = 'text-gray-500 text-center py-4 empty-message';
            p.textContent = 'Aucune donnée disponible pour le résumé financier.';
            parent.insertBefore(p, financialSummaryChartCanvas);
            return;
        } else {
            const parent = financialSummaryChartCanvas.parentElement;
            if (parent.querySelector('p.empty-message')) parent.querySelector('p.empty-message').remove();
        }


        if (charts.financialSummary) charts.financialSummary.destroy();
        charts.financialSummary = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['Total Dépôts', 'Total Retraits', 'Total Commissions'], datasets: [{
                    label: 'Résumé Financier (FCFA)',
                    data: [data.total_deposits || 0, data.total_withdrawals || 0, data.total_commissions_earned || 0],
                    backgroundColor: ['rgba(75, 192, 192, 0.7)', 'rgba(255, 99, 132, 0.7)', 'rgba(255, 206, 86, 0.7)'],
                    borderColor: ['rgba(75, 192, 192, 1)','rgba(255, 99, 132, 1)','rgba(255, 206, 86, 1)'],
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } }
        });
    }

    async function fetchUserActivityStats(queryParams) {
        if (!isAdmin || !userActivitySection) return;
        if (userActivityLoading) userActivityLoading.style.display = 'block';
        try {
            const response = await fetch(`/api/stats/user-activity?${queryParams}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (!response.ok) {
                await handleApiError(response);
                if(userActivityChartCanvas) userActivityChartCanvas.parentElement.innerHTML = '<p class="text-red-500 text-center py-4">Erreur de chargement des données.</p><canvas id="user-activity-chart"></canvas>';
            } else {
                const result = await response.json();
                renderUserActivityChart(result.data);
            }
        } catch (error) {
            console.error('Error fetching user activity stats:', error);
            showGlobalNotification('Erreur réseau: activité des gérants.', 'error');
            if(userActivityChartCanvas) userActivityChartCanvas.parentElement.innerHTML = '<p class="text-red-500 text-center py-4">Erreur de chargement des données.</p><canvas id="user-activity-chart"></canvas>';
        } finally {
            if (userActivityLoading) userActivityLoading.style.display = 'none';
        }
    }

    function renderUserActivityChart(data) {
        if (!userActivityChartCanvas) return;
        const ctx = userActivityChartCanvas.getContext('2d');
        if (!data || data.length === 0) {
            if (charts.userActivity) charts.userActivity.destroy();
             ctx.clearRect(0, 0, userActivityChartCanvas.width, userActivityChartCanvas.height);
            const parent = userActivityChartCanvas.parentElement;
            if (parent.querySelector('p.empty-message')) parent.querySelector('p.empty-message').remove();
            const p = document.createElement('p');
            p.className = 'text-gray-500 text-center py-4 empty-message';
            p.textContent = 'Aucune donnée d\'activité utilisateur.';
            parent.insertBefore(p, userActivityChartCanvas);
            return;
        } else {
            const parent = userActivityChartCanvas.parentElement;
            if (parent.querySelector('p.empty-message')) parent.querySelector('p.empty-message').remove();
        }


        const labels = data.map(item => item.user_full_name);
        const operationCounts = data.map(item => item.total_ops);

        if (charts.userActivity) charts.userActivity.destroy();
        charts.userActivity = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: [{
                    label: 'Nombre d\'Opérations par Gérant', data: operationCounts,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)', borderColor: 'rgba(153, 102, 255, 1)', borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, title: {display: true, text: 'Nombre d\'opérations'} } } }
        });
    }

    function populateSelect(selectElement, items, defaultOptionText, valueKey = 'id', textKey = 'name') {
        if (!selectElement) return;
        selectElement.innerHTML = `<option value="">${defaultOptionText}</option>`;
        if(items && Array.isArray(items)){
            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item[valueKey];
                option.textContent = item[textKey];
                selectElement.appendChild(option);
            });
        }
    }

    function formatCurrency(amount) {
        return amount !== null && amount !== undefined ? new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount) : 'N/A';
    }

    function handleGenericExport(statsName, exportType) {
        showGlobalNotification(`Préparation de l'export ${exportType.toUpperCase()} pour ${statsName}...`, 'info');
        const filters = getFilterValues();
        // For user-specific stats like financial summary, if user is not admin, their ID is implicitly used by backend.
        // If admin is viewing a specific user's stats via filter, that user_id should be passed.
        if (!isAdmin && filters.user_id) delete filters.user_id;

        const queryParams = new URLSearchParams(filters).toString();
        const exportUrl = `/api/stats/${statsName}/export/${exportType}?${queryParams}`;
        window.open(exportUrl, '_blank');
    }

    initializePage();
});
