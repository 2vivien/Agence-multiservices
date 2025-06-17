document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication and role
    if (typeof checkAuthStatusAndRedirect !== 'function' || typeof logoutUser !== 'function' || typeof showGlobalNotification !== 'function' || typeof handleApiError !== 'function') {
        console.error('common.js is not loaded or essential functions are missing.');
        alert('Erreur critique: Fichiers de base manquants.');
        return;
    }
    checkAuthStatusAndRedirect();

    const userRole = localStorage.getItem('userRole');
    if (userRole !== 'admin') {
        showGlobalNotification('Accès interdit. Cette page est réservée aux administrateurs.', 'error');
        setTimeout(() => window.location.href = 'index.html', 2000);
        return;
    }

    // State variables
    let summaries = [];
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    const limit = 15; // Records per page

    // DOM Elements - Filters
    const filterDateInput = document.getElementById('filter-date');
    const filterDateFromInput = document.getElementById('filter-date-from');
    const filterDateToInput = document.getElementById('filter-date-to');
    const filterUserIdSelect = document.getElementById('filter-user-id');
    const applyFiltersButton = document.getElementById('apply-filters-btn');
    const resetFiltersButton = document.getElementById('reset-filters-btn');


    // DOM Elements - Display
    const summariesTableBody = document.getElementById('summaries-table-body');
    const tableLoadingIndicator = document.getElementById('table-loading');
    const summariesTableContainer = document.getElementById('summaries-table-container'); // To hide/show table

    // Pagination Elements
    const paginationInfoEl = document.getElementById('pagination-info');
    const prevPageButton = document.getElementById('prev-page-btn');
    const nextPageButton = document.getElementById('next-page-btn');
    const currentPageEl = document.getElementById('current-page');
    const totalPagesEl = document.getElementById('total-pages');

    /**
     * Load prerequisites for filters (list of gérants)
     */
    async function loadPrerequisites() {
        try {
            const response = await fetch('/api/admin/users?role=gerant&limit=1000', { // Assuming a high limit to get all gerants
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                await handleApiError(response);
                return;
            }
            const usersData = await response.json();
            const gerants = usersData.data || usersData; // Adapt if API wraps in 'data'

            if (filterUserIdSelect) {
                filterUserIdSelect.innerHTML = '<option value="">Tous les Gérants</option>';
                gerants.forEach(gerant => {
                    const option = document.createElement('option');
                    option.value = gerant.id;
                    option.textContent = `${gerant.full_name} (${gerant.username})`;
                    filterUserIdSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading prerequisites:', error);
            showGlobalNotification('Erreur de chargement des filtres.', 'error');
        }
    }

    /**
     * Fetch daily summaries from the API
     */
    async function fetchSummaries(page = 1) {
        if(tableLoadingIndicator) tableLoadingIndicator.classList.remove('hidden');
        if(summariesTableContainer) summariesTableContainer.classList.add('hidden');

        currentPage = page;
        const filters = getFilterValues();
        const queryParams = new URLSearchParams({
            ...filters,
            page: currentPage,
            limit: limit
        }).toString();

        try {
            const response = await fetch(`/api/admin/daily-summaries?${queryParams}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                await handleApiError(response);
                summaries = [];
                totalRecords = 0;
            } else {
                const result = await response.json();
                summaries = result.data || [];
                totalRecords = result.pagination?.total_records || 0;
                totalPages = result.pagination?.total_pages || 1;
            }
            renderSummariesTable(summaries);
            renderPagination();

        } catch (error) {
            console.error('Error fetching summaries:', error);
            showGlobalNotification('Erreur réseau lors du chargement des résumés.', 'error');
            renderSummariesTable([]);
            renderPagination(); // Still render pagination to show 0 results
        } finally {
            if(tableLoadingIndicator) tableLoadingIndicator.classList.add('hidden');
            if(summariesTableContainer) summariesTableContainer.classList.remove('hidden');
        }
    }

    function getFilterValues() {
        const filters = {};
        if (filterDateInput && filterDateInput.value) filters.date = filterDateInput.value;
        if (filterDateFromInput && filterDateFromInput.value) filters.date_from = filterDateFromInput.value;
        if (filterDateToInput && filterDateToInput.value) filters.date_to = filterDateToInput.value;
        if (filterUserIdSelect && filterUserIdSelect.value) filters.user_id = filterUserIdSelect.value;
        return filters;
    }

    function renderSummariesTable(summariesArray) {
        if (!summariesTableBody) return;
        summariesTableBody.innerHTML = '';

        if (!summariesArray || summariesArray.length === 0) {
            summariesTableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-gray-500">Aucun résumé de journée trouvé pour les filtres sélectionnés.</td></tr>';
            return;
        }

        summariesArray.forEach(summary => {
            const row = summariesTableBody.insertRow();
            const differenceClass = summary.difference_amount < 0 ? 'text-red-600' : (summary.difference_amount > 0 ? 'text-green-600' : 'text-gray-700');
            row.innerHTML = `
                <td class="px-3 py-2 border-b text-sm">${formatDate(summary.balance_date)}</td>
                <td class="px-3 py-2 border-b text-sm">${summary.gerant_fullname || summary.gerant_username || 'N/A'}</td>
                <td class="px-3 py-2 border-b text-sm text-right">${formatCurrency(summary.initial_amount)}</td>
                <td class="px-3 py-2 border-b text-sm text-right">${formatCurrency(summary.calculated_final_amount)}</td>
                <td class="px-3 py-2 border-b text-sm text-right">${formatCurrency(summary.actual_final_amount)}</td>
                <td class="px-3 py-2 border-b text-sm text-right font-semibold ${differenceClass}">${formatCurrency(summary.difference_amount)}</td>
                <td class="px-3 py-2 border-b text-sm">${summary.notes || '-'}</td>
                <td class="px-3 py-2 border-b text-sm">${formatDateTime(summary.closed_at) || (summary.is_closed ? 'Oui' : 'Non')}</td>
            `;
        });
    }

    function renderPagination() {
        if (!paginationInfoEl || !prevPageButton || !nextPageButton || !currentPageEl || !totalPagesEl) return;

        totalPages = Math.ceil(totalRecords / limit) || 1;

        paginationInfoEl.textContent = `Page ${currentPage} sur ${totalPages}. Total: ${totalRecords} résumés.`;
        currentPageEl.textContent = currentPage;
        totalPagesEl.textContent = totalPages;

        prevPageButton.disabled = currentPage <= 1;
        nextPageButton.disabled = currentPage >= totalPages;
    }

    // Event Listeners
    if (applyFiltersButton) {
        applyFiltersButton.addEventListener('click', () => fetchSummaries(1)); // Fetch first page with new filters
    }
    if (resetFiltersButton) {
        resetFiltersButton.addEventListener('click', () => {
            if(filterDateInput) filterDateInput.value = '';
            if(filterDateFromInput) filterDateFromInput.value = '';
            if(filterDateToInput) filterDateToInput.value = '';
            if(filterUserIdSelect) filterUserIdSelect.value = '';
            fetchSummaries(1); // Fetch with cleared filters
        });
    }

    if (prevPageButton) {
        prevPageButton.addEventListener('click', () => {
            if (currentPage > 1) fetchSummaries(currentPage - 1);
        });
    }
    if (nextPageButton) {
        nextPageButton.addEventListener('click', () => {
            if (currentPage < totalPages) fetchSummaries(currentPage + 1);
        });
    }

    // Initial Load
    loadPrerequisites().then(() => {
        fetchSummaries(1); // Load initial data
    });

    // Helper functions
    function formatCurrency(amount) {
        return (amount !== null && amount !== undefined) ? new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount) : 'N/A';
    }
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', { year: 'numeric', month: '2-digit', day: '2-digit' });
        } catch (e) { return dateString; }
    }
    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        try {
            const date = new Date(dateTimeString);
            return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
        } catch (e) { return dateTimeString; }
    }
});
