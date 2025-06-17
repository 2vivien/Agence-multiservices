document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication and role
    if (typeof checkAuthStatusAndRedirect !== 'function' || typeof logoutUser !== 'function' || typeof showGlobalNotification !== 'function' || typeof handleApiError !== 'function') {
        console.error('common.js is not loaded or essential functions are missing.');
        alert('Erreur critique: Fichiers de base manquants.');
        return;
    }
    checkAuthStatusAndRedirect();

    const userRole = localStorage.getItem('userRole');
    if (userRole !== 'gerant') {
        showGlobalNotification('Accès interdit. Cette page est réservée aux gérants.', 'error');
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
    const filterDateInput = document.getElementById('filter-date-gerant');
    const filterDateFromInput = document.getElementById('filter-date-from-gerant');
    const filterDateToInput = document.getElementById('filter-date-to-gerant');
    const applyFiltersButton = document.getElementById('apply-filters-btn-gerant');
    const resetFiltersButton = document.getElementById('reset-filters-btn-gerant');

    // DOM Elements - Display
    const summariesTableBody = document.getElementById('gerant-summaries-table-body');
    const tableLoadingIndicator = document.getElementById('gerant-table-loading');
    const summariesTableContainer = document.getElementById('gerant-summaries-table-container');

    // Pagination Elements
    const paginationInfoEl = document.getElementById('gerant-pagination-info');
    const prevPageButton = document.getElementById('gerant-prev-page-btn');
    const nextPageButton = document.getElementById('gerant-next-page-btn');
    const currentPageEl = document.getElementById('gerant-current-page');
    const totalPagesEl = document.getElementById('gerant-total-pages');

    /**
     * Fetch daily summaries for the current gérant from the API
     */
    async function fetchGerantSummaries(page = 1) {
        if(tableLoadingIndicator) tableLoadingIndicator.classList.remove('hidden');
        if(summariesTableContainer) summariesTableContainer.classList.add('hidden');

        currentPage = page;
        const filters = getGerantFilterValues();
        const queryParams = new URLSearchParams({
            ...filters,
            page: currentPage,
            limit: limit
        }).toString();

        try {
            const response = await fetch(`/api/gerant/daily-summaries?${queryParams}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                await handleApiError(response); // Common error handler for 401, 403, 500 etc.
                summaries = [];
                totalRecords = 0;
            } else {
                const result = await response.json();
                summaries = result.data || [];
                totalRecords = result.pagination?.total_records || 0;
                totalPages = result.pagination?.total_pages || 1;
            }
            renderGerantSummariesTable(summaries);
            renderGerantPagination();

        } catch (error) { // Network errors
            console.error('Error fetching gérant summaries:', error);
            showGlobalNotification('Erreur réseau lors du chargement de vos résumés.', 'error');
            renderGerantSummariesTable([]);
            renderGerantPagination();
        } finally {
            if(tableLoadingIndicator) tableLoadingIndicator.classList.add('hidden');
            if(summariesTableContainer) summariesTableContainer.classList.remove('hidden');
        }
    }

    function getGerantFilterValues() {
        const filters = {};
        if (filterDateInput && filterDateInput.value) filters.date = filterDateInput.value;
        if (filterDateFromInput && filterDateFromInput.value) filters.date_from = filterDateFromInput.value;
        if (filterDateToInput && filterDateToInput.value) filters.date_to = filterDateToInput.value;
        return filters;
    }

    function renderGerantSummariesTable(summariesArray) {
        if (!summariesTableBody) return;
        summariesTableBody.innerHTML = '';

        if (!summariesArray || summariesArray.length === 0) {
            summariesTableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-gray-500">Aucun résumé de journée trouvé pour les filtres sélectionnés.</td></tr>';
            return;
        }

        summariesArray.forEach(summary => {
            const row = summariesTableBody.insertRow();
            const differenceClass = summary.difference_amount < 0 ? 'text-red-600' : (summary.difference_amount > 0 ? 'text-green-600' : 'text-gray-700');
            row.innerHTML = `
                <td class="px-3 py-2 border-b text-sm">${formatDate(summary.balance_date)}</td>
                <td class="px-3 py-2 border-b text-sm text-right">${formatCurrency(summary.initial_amount)}</td>
                <td class="px-3 py-2 border-b text-sm text-right">${formatCurrency(summary.calculated_final_amount)}</td>
                <td class="px-3 py-2 border-b text-sm text-right">${formatCurrency(summary.actual_final_amount)}</td>
                <td class="px-3 py-2 border-b text-sm text-right font-semibold ${differenceClass}">${formatCurrency(summary.difference_amount)}</td>
                <td class="px-3 py-2 border-b text-sm">${summary.notes || '-'}</td>
                <td class="px-3 py-2 border-b text-sm">${formatDateTime(summary.closed_at) || (summary.is_closed ? 'Oui' : 'Non')}</td>
            `;
        });
    }

    function renderGerantPagination() {
        if (!paginationInfoEl || !prevPageButton || !nextPageButton || !currentPageEl || !totalPagesEl) {
            console.warn("Pagination elements not found in resume_jour.js");
            return;
        }

        totalPages = Math.ceil(totalRecords / limit) || 1;

        paginationInfoEl.textContent = `Page ${currentPage} sur ${totalPages}. Total: ${totalRecords} résumés.`;
        currentPageEl.textContent = currentPage;
        totalPagesEl.textContent = totalPages;

        prevPageButton.disabled = currentPage <= 1;
        nextPageButton.disabled = currentPage >= totalPages;
    }

    // Event Listeners
    if (applyFiltersButton) {
        applyFiltersButton.addEventListener('click', () => fetchGerantSummaries(1));
    }
    if (resetFiltersButton) {
        resetFiltersButton.addEventListener('click', () => {
            if(filterDateInput) filterDateInput.value = '';
            if(filterDateFromInput) filterDateFromInput.value = '';
            if(filterDateToInput) filterDateToInput.value = '';
            fetchGerantSummaries(1);
        });
    }

    if (prevPageButton) {
        prevPageButton.addEventListener('click', () => {
            if (currentPage > 1) fetchGerantSummaries(currentPage - 1);
        });
    }
    if (nextPageButton) {
        nextPageButton.addEventListener('click', () => {
            if (currentPage < totalPages) fetchGerantSummaries(currentPage + 1);
        });
    }

    // Initial Load
    fetchGerantSummaries(1);

    // Helper functions (could be moved to common.js if widely used)
    function formatCurrency(amount) {
        return (amount !== null && amount !== undefined) ? new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount) : 'N/A';
    }
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString); // Assumes dateString is YYYY-MM-DD from DB
            return new Date(date.getTime() + date.getTimezoneOffset() * 60000).toLocaleDateString('fr-FR', { year: 'numeric', month: '2-digit', day: '2-digit' });
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
