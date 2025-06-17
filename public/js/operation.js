document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication status
    if (typeof checkAuthStatusAndRedirect !== 'function' || typeof logoutUser !== 'function' || typeof showGlobalNotification !== 'function' || typeof handleApiError !== 'function') {
        console.error('common.js is not loaded or essential functions are missing.');
        alert('Erreur critique: Fichiers de base manquants.');
        return;
    }
    checkAuthStatusAndRedirect();

    // Global state variables
    let servicesList = [];
    let operationTypesList = [];
    let operationsList = [];
    let currentEditingOperationId = null;
    let currentFilters = {}; // To store any active filters if they are added later

    // DOM Elements
    const operationsTableBody = document.getElementById('operations-table-body');
    const operationForm = document.getElementById('operation-form');
    const serviceSelect = document.getElementById('form-service-id');
    const operationTypeSelect = document.getElementById('form-operation-type-id');
    const amountInput = document.getElementById('form-amount');
    const descriptionInput = document.getElementById('form-description');
    const operationTimeInput = document.getElementById('form-operation-time');
    const operationIdInput = document.getElementById('form-operation-id');
    const formTitle = document.getElementById('form-title');
    const submitButton = operationForm ? operationForm.querySelector('button[type="submit"]') : null;
    const submitButtonText = document.getElementById('form-submit-button-text');
    const resetButton = document.getElementById('reset-form-button');

    const tableLoadingIndicator = document.getElementById('table-loading');
    const formLoadingIndicator = document.getElementById('form-loading');
    const operationsTable = document.getElementById('operations-table');

    // Export buttons
    const exportPdfBtn = document.getElementById('export-ops-pdf-btn');
    const exportExcelBtn = document.getElementById('export-ops-excel-btn');

    async function loadPrerequisites() {
        try {
            const [servicesResponse, operationTypesResponse] = await Promise.all([
                fetch('/api/services', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch('/api/operation-types', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            ]);

            if (!servicesResponse.ok) await handleApiError(servicesResponse);
            if (!operationTypesResponse.ok) await handleApiError(operationTypesResponse);

            if (servicesResponse.ok) servicesList = await servicesResponse.json();
            if (operationTypesResponse.ok) operationTypesList = await operationTypesResponse.json();

            populateSelect(serviceSelect, servicesList, 'Choisissez un service');
            populateSelect(operationTypeSelect, operationTypesList, 'Choisissez un type');

        } catch (error) {
            console.error('Error in loadPrerequisites:', error);
            showGlobalNotification('Une erreur réseau est survenue lors du chargement des prérequis.', 'error');
        }
    }

    function populateSelect(selectElement, items, defaultOptionText) {
        if (!selectElement) return;
        selectElement.innerHTML = `<option value="">${defaultOptionText}</option>`;
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            selectElement.appendChild(option);
        });
    }

    // Function to get current filters (if any were added to the page)
    // For now, it's empty, but can be expanded if filter inputs are added to operation.html
    function getCurrentOperationFilters() {
        const filters = {};
        // Example: if a date filter was added with id 'filter-op-date'
        // const dateFilter = document.getElementById('filter-op-date');
        // if (dateFilter && dateFilter.value) {
        //     filters.date = dateFilter.value;
        // }
        // currentFilters = filters; // Update global currentFilters if needed elsewhere
        return filters;
    }


    async function fetchOperations() {
        if (tableLoadingIndicator) tableLoadingIndicator.classList.remove('hidden');
        if (operationsTable) operationsTable.classList.add('hidden');

        currentFilters = getCurrentOperationFilters(); // Update filters before fetching
        const queryParams = new URLSearchParams(currentFilters).toString();


        try {
            const response = await fetch(`/api/operations?${queryParams}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                await handleApiError(response);
                operationsList = [];
            } else {
                const result = await response.json();
                // The API /api/operations might return an array directly or an object with a 'data' property for pagination.
                // The current backend OperationController@index returns array directly without pagination structure.
                operationsList = result.data || result;
            }
            renderOperationsTable(operationsList);
        } catch (error) {
            console.error('Error in fetchOperations:', error);
            showGlobalNotification('Erreur réseau lors du chargement des opérations.', 'error');
            renderOperationsTable([]);
        } finally {
            if (tableLoadingIndicator) tableLoadingIndicator.classList.add('hidden');
            if (operationsTable) operationsTable.classList.remove('hidden');
        }
    }

    function renderOperationsTable(operations) {
        if (!operationsTableBody) return;
        operationsTableBody.innerHTML = '';

        if (!operations || operations.length === 0) {
            operationsTableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucune opération trouvée.</td></tr>';
            return;
        }

        operations.forEach(op => {
            const row = operationsTableBody.insertRow();
            row.innerHTML = `
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${formatDateTime(op.operation_time)}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${op.description || '-'}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${op.service_name || servicesList.find(s => s.id == op.service_id)?.name || op.service_id}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${op.operation_type_name || operationTypesList.find(ot => ot.id == op.operation_type_id)?.name || op.operation_type_id}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${formatCurrency(op.amount)}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-right">
                    <button data-id="${op.id}" class="edit-btn text-indigo-600 hover:text-indigo-800 mr-2" title="Modifier"><i class="fas fa-edit"></i> Modifier</button>
                    <button data-id="${op.id}" class="delete-btn text-red-600 hover:text-red-900" title="Supprimer"><i class="fas fa-trash"></i> Supprimer</button>
                </td>
            `;
        });

        document.querySelectorAll('.edit-btn').forEach(button => button.addEventListener('click', (e) => editOperation(e.currentTarget.dataset.id)));
        document.querySelectorAll('.delete-btn').forEach(button => button.addEventListener('click', (e) => deleteOperation(e.currentTarget.dataset.id)));
    }

    async function handleFormSubmit(event) {
        event.preventDefault();
        if (!operationForm) return;

        const originalButtonText = submitButtonText ? submitButtonText.textContent : (currentEditingOperationId ? 'Mettre à jour Opération' : 'Ajouter Opération');
        if (submitButton) submitButton.disabled = true;
        if (submitButtonText) submitButtonText.textContent = currentEditingOperationId ? 'Mise à jour...' : 'Ajout...';

        const formData = {
            service_id: serviceSelect.value,
            operation_type_id: operationTypeSelect.value,
            amount: parseFloat(amountInput.value),
            description: descriptionInput.value.trim(),
            operation_time: operationTimeInput.value ? new Date(operationTimeInput.value).toISOString().slice(0, 19).replace('T', ' ') : null,
        };

        if (!formData.service_id || !formData.operation_type_id || isNaN(formData.amount) || !formData.operation_time) {
            showGlobalNotification('Veuillez remplir tous les champs obligatoires (Service, Type, Montant, Date).', 'error');
            if (submitButton) submitButton.disabled = false;
            if (submitButtonText) submitButtonText.textContent = originalButtonText;
            return;
        }
        if (formData.amount < 0) {
             showGlobalNotification('Le montant ne peut pas être négatif.', 'error');
             if (submitButton) submitButton.disabled = false;
             if (submitButtonText) submitButtonText.textContent = originalButtonText;
             return;
        }

        const method = currentEditingOperationId ? 'PUT' : 'POST';
        const url = currentEditingOperationId ? `/api/operations/${currentEditingOperationId}` : '/api/operations';

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(formData)
            });
            const responseData = await response.json();

            if (response.ok) {
                showGlobalNotification(currentEditingOperationId ? 'Opération mise à jour avec succès!' : 'Opération ajoutée avec succès!', 'success');
                fetchOperations();
                resetForm();
                document.dispatchEvent(new CustomEvent('operationsUpdated'));
            } else if (response.status === 422 && responseData.errors) {
                const errors = Object.entries(responseData.errors).map(([field, msg]) => `${field}: ${msg}`).join('; ');
                showGlobalNotification(`Erreurs de validation: ${errors}`, 'error');
            } else if (!await handleApiError(response)){
                 const errorMsg = responseData.error || responseData.message || `Erreur ${response.status} lors de la soumission.`;
                 showGlobalNotification(errorMsg, 'error');
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            showGlobalNotification('Une erreur réseau est survenue lors de la soumission.', 'error');
        } finally {
            if (submitButton) submitButton.disabled = false;
            if (submitButtonText) submitButtonText.textContent = originalButtonText;
        }
    }

    async function editOperation(operationId) {
        currentEditingOperationId = operationId;
        if (formLoadingIndicator) formLoadingIndicator.classList.remove('hidden');
        if (operationForm) operationForm.classList.add('hidden');

        try {
            const response = await fetch(`/api/operations/${operationId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                await handleApiError(response);
                resetForm();
                return;
            }
            const op = await response.json();

            if (formTitle) formTitle.textContent = "Modifier l'Opération";
            if (submitButtonText) submitButtonText.textContent = 'Mettre à jour Opération';

            serviceSelect.value = op.service_id;
            operationTypeSelect.value = op.operation_type_id;
            amountInput.value = op.amount;
            descriptionInput.value = op.description || '';
            operationTimeInput.value = op.operation_time ? new Date(op.operation_time.replace(' ', 'T')).toISOString().slice(0, 16) : '';
            if(operationIdInput) operationIdInput.value = op.id;

            window.scrollTo({ top: operationForm.offsetTop - 20, behavior: 'smooth' });
        } catch (error) {
            console.error('Error fetching operation for edit:', error);
            showGlobalNotification('Erreur réseau lors du chargement de l\'opération pour modification.', 'error');
            resetForm();
        } finally {
            if (formLoadingIndicator) formLoadingIndicator.classList.add('hidden');
            if (operationForm) operationForm.classList.remove('hidden');
        }
    }

    async function deleteOperation(operationId) {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer l'opération ID ${operationId} ?`)) {
            return;
        }
        try {
            const response = await fetch(`/api/operations/${operationId}`, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok || response.status === 204) {
                showGlobalNotification('Opération supprimée avec succès!', 'success');
                fetchOperations();
                document.dispatchEvent(new CustomEvent('operationsUpdated'));
            } else {
                await handleApiError(response);
            }
        } catch (error) {
            console.error('Error deleting operation:', error);
            showGlobalNotification('Une erreur réseau est survenue lors de la suppression.', 'error');
        }
    }

    function resetForm() {
        if (operationForm) operationForm.reset();
        currentEditingOperationId = null;
        if (operationIdInput) operationIdInput.value = '';
        if (formTitle) formTitle.textContent = "Ajouter une Nouvelle Opération";
        if (submitButtonText) submitButtonText.textContent = 'Ajouter Opération';
        if (serviceSelect) serviceSelect.value = "";
        if (operationTypeSelect) operationTypeSelect.value = "";
    }

    // Export handlers
    function handleExport(exportType) {
        showGlobalNotification('Préparation de l\'export...', 'info');
        const filters = getCurrentOperationFilters(); // Use current filters for export
        const queryParams = new URLSearchParams(filters).toString();
        const exportUrl = `/api/operations/export/${exportType}?${queryParams}`;
        window.open(exportUrl, '_blank');
    }

    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', () => handleExport('pdf'));
    }
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', () => handleExport('excel'));
    }

    // Initial setup
    if (operationForm) operationForm.addEventListener('submit', handleFormSubmit);
    if (resetButton) resetButton.addEventListener('click', resetForm);

    loadPrerequisites();
    fetchOperations();

    function formatCurrency(amount) {
        return amount !== null && amount !== undefined ? new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount) : 'N/A';
    }

    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        try {
            const date = new Date(dateTimeString);
            if (isNaN(date.getTime())) return 'Date invalide';
            return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
        } catch (e) {
            return dateTimeString;
        }
    }
});
