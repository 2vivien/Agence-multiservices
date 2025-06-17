document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication status
    if (typeof checkAuthStatusAndRedirect !== 'function') {
        console.error('common.js is not loaded or checkAuthStatusAndRedirect is not defined.');
        alert('Erreur critique: Impossible de vérifier l\'authentification.');
        return;
    }
    checkAuthStatusAndRedirect();

    // Global state variables
    let servicesList = [];
    let operationTypesList = [];
    let operationsList = [];
    let currentEditingOperationId = null;

    // DOM Elements
    const operationsTableBody = document.getElementById('operations-table-body');
    const operationForm = document.getElementById('operation-form');
    const serviceSelect = document.getElementById('form-service-id');
    const operationTypeSelect = document.getElementById('form-operation-type-id');
    const amountInput = document.getElementById('form-amount');
    const descriptionInput = document.getElementById('form-description');
    const operationTimeInput = document.getElementById('form-operation-time');
    const operationIdInput = document.getElementById('form-operation-id'); // Hidden field
    const formTitle = document.getElementById('form-title'); // Assuming a title for the form
    const submitButton = operationForm ? operationForm.querySelector('button[type="submit"]') : null;
    const resetButton = document.getElementById('reset-form-button');

    // Loading indicators
    const tableLoadingIndicator = document.getElementById('table-loading');
    const formLoadingIndicator = document.getElementById('form-loading'); // For when form is fetching data for edit

    /**
     * Load Services and Operation Types for form dropdowns
     */
    async function loadPrerequisites() {
        try {
            const [servicesResponse, operationTypesResponse] = await Promise.all([
                fetch('/api/services', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch('/api/operation-types', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            ]);

            if (!servicesResponse.ok || !operationTypesResponse.ok) {
                console.error('Failed to load prerequisites');
                if (!servicesResponse.ok) alert('Erreur de chargement des services.');
                if (!operationTypesResponse.ok) alert('Erreur de chargement des types d\'opération.');
                return;
            }

            servicesList = await servicesResponse.json();
            operationTypesList = await operationTypesResponse.json();

            populateSelect(serviceSelect, servicesList, 'Choisissez un service');
            populateSelect(operationTypeSelect, operationTypesList, 'Choisissez un type');

        } catch (error) {
            console.error('Error in loadPrerequisites:', error);
            alert('Une erreur réseau est survenue lors du chargement des prérequis.');
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

    /**
     * Fetch operations from the API
     */
    async function fetchOperations() {
        if (tableLoadingIndicator) tableLoadingIndicator.classList.remove('hidden');
        if (operationsTableBody) operationsTableBody.classList.add('hidden');

        try {
            const response = await fetch('/api/operations', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                // Handle error, maybe redirect if 401/403
                if (response.status === 401 || response.status === 403) {
                    logoutUser(); // Or redirect to login
                }
                console.error('Failed to fetch operations', response.status);
                alert('Erreur de chargement des opérations.');
                return;
            }
            operationsList = await response.json();
            // Assuming the backend returns an array directly, or an object with a data property.
            // For now, if it's like { message: "...", data_received: [] } from controller stubs, adapt this.
            // Let's assume OperationController@index will be fixed to return an array of operations.
            renderOperationsTable(operationsList.data || operationsList); // Adjust based on actual API response structure
        } catch (error) {
            console.error('Error in fetchOperations:', error);
            alert('Une erreur réseau est survenue lors du chargement des opérations.');
        } finally {
            if (tableLoadingIndicator) tableLoadingIndicator.classList.add('hidden');
            if (operationsTableBody) operationsTableBody.classList.remove('hidden');
        }
    }

    /**
     * Render operations in the HTML table
     * @param {Array} operations - Array of operation objects
     */
    function renderOperationsTable(operations) {
        if (!operationsTableBody) return;
        operationsTableBody.innerHTML = ''; // Clear existing rows

        if (!operations || operations.length === 0) {
            operationsTableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucune opération trouvée.</td></tr>';
            return;
        }

        operations.forEach(op => {
            const row = operationsTableBody.insertRow();
            row.innerHTML = `
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${formatDateTime(op.operation_time)}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${op.description || '-'}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${op.service_name || servicesList.find(s => s.id === op.service_id)?.name || op.service_id}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">${op.operation_type_name || operationTypesList.find(ot => ot.id === op.operation_type_id)?.name || op.operation_type_id}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${formatCurrency(op.amount)}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-right">
                    <button data-id="${op.id}" class="edit-btn text-indigo-600 hover:text-indigo-900 mr-2"><i class="fas fa-edit"></i> Modifier</button>
                    <button data-id="${op.id}" class="delete-btn text-red-600 hover:text-red-900"><i class="fas fa-trash"></i> Supprimer</button>
                </td>
            `;
        });

        // Add event listeners for new edit/delete buttons
        document.querySelectorAll('.edit-btn').forEach(button => button.addEventListener('click', () => editOperation(button.dataset.id)));
        document.querySelectorAll('.delete-btn').forEach(button => button.addEventListener('click', () => deleteOperation(button.dataset.id)));
    }

    /**
     * Handle form submission for creating or updating an operation
     * @param {Event} event
     */
    async function handleFormSubmit(event) {
        event.preventDefault();
        if (!operationForm) return;

        const formData = {
            service_id: serviceSelect.value,
            operation_type_id: operationTypeSelect.value,
            amount: parseFloat(amountInput.value),
            description: descriptionInput.value.trim(),
            operation_time: operationTimeInput.value ? new Date(operationTimeInput.value).toISOString().slice(0, 19).replace('T', ' ') : null,
            // user_id is set by backend for non-admin, admin can add user_id if needed (not implemented in this form for simplicity)
        };

        // Basic client-side validation
        if (!formData.service_id || !formData.operation_type_id || isNaN(formData.amount) || !formData.operation_time) {
            alert('Veuillez remplir tous les champs obligatoires (Service, Type, Montant, Date).');
            return;
        }
        if (formData.amount < 0) {
             alert('Le montant ne peut pas être négatif.');
             return;
        }

        const method = currentEditingOperationId ? 'PUT' : 'POST';
        const url = currentEditingOperationId ? `/api/operations/${currentEditingOperationId}` : '/api/operations';

        if (submitButton) submitButton.disabled = true;
        if (submitButton) submitButton.textContent = currentEditingOperationId ? 'Mise à jour...' : 'Ajout...';


        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            });

            const responseData = await response.json();

            if (response.ok) {
                alert(currentEditingOperationId ? 'Opération mise à jour avec succès!' : 'Opération ajoutée avec succès!');
                fetchOperations(); // Refresh table
                resetForm();
            } else if (response.status === 422) { // Validation errors
                let errorMessages = "Erreurs de validation:\n";
                for (const field in responseData.errors) {
                    errorMessages += `- ${responseData.errors[field]}\n`;
                }
                alert(errorMessages);
            } else {
                alert(`Erreur: ${responseData.error || response.statusText}`);
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            alert('Une erreur réseau est survenue.');
        } finally {
            if (submitButton) submitButton.disabled = false;
            if (submitButton) submitButton.textContent = currentEditingOperationId ? 'Mettre à jour Opération' : 'Ajouter Opération';
        }
    }

    /**
     * Populate form for editing an operation
     * @param {string} operationId
     */
    async function editOperation(operationId) {
        currentEditingOperationId = operationId;
        if (formLoadingIndicator) formLoadingIndicator.classList.remove('hidden');

        try {
            const response = await fetch(`/api/operations/${operationId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                alert('Impossible de charger les données de l\'opération pour modification.');
                resetForm(); // Clear ID if fetch fails
                return;
            }
            const op = await response.json();
            // Assuming op is the operation object, not { message: "...", data: op }
            // If OperationController@show returns { message: "...", data: op }, then use op.data

            if (formTitle) formTitle.textContent = "Modifier l'Opération";
            if (submitButton) submitButton.textContent = 'Mettre à jour Opération';

            serviceSelect.value = op.service_id;
            operationTypeSelect.value = op.operation_type_id;
            amountInput.value = op.amount;
            descriptionInput.value = op.description || '';
            // Format date for datetime-local input: YYYY-MM-DDThh:mm
            operationTimeInput.value = op.operation_time ? new Date(op.operation_time.replace(' ', 'T')).toISOString().slice(0, 16) : '';
            operationIdInput.value = op.id;

            window.scrollTo({ top: operationForm.offsetTop - 20, behavior: 'smooth' });

        } catch (error) {
            console.error('Error fetching operation for edit:', error);
            alert('Erreur lors du chargement de l\'opération.');
            resetForm();
        } finally {
            if (formLoadingIndicator) formLoadingIndicator.classList.add('hidden');
        }
    }

    /**
     * Delete an operation
     * @param {string} operationId
     */
    async function deleteOperation(operationId) {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer l'opération ID ${operationId} ?`)) {
            return;
        }

        try {
            const response = await fetch(`/api/operations/${operationId}`, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) { // Expect 204 No Content or 200 with message
                alert('Opération supprimée avec succès!');
                fetchOperations(); // Refresh table
            } else {
                const responseData = await response.json().catch(() => null);
                alert(`Erreur lors de la suppression: ${responseData?.error || response.statusText}`);
            }
        } catch (error) {
            console.error('Error deleting operation:', error);
            alert('Une erreur réseau est survenue lors de la suppression.');
        }
    }

    /**
     * Reset the operation form
     */
    function resetForm() {
        if (operationForm) operationForm.reset();
        currentEditingOperationId = null;
        if (operationIdInput) operationIdInput.value = '';
        if (formTitle) formTitle.textContent = "Ajouter une Nouvelle Opération";
        if (submitButton) submitButton.textContent = 'Ajouter Opération';
        if (serviceSelect) serviceSelect.value = "";
        if (operationTypeSelect) operationTypeSelect.value = "";
    }

    // Initial setup
    if (operationForm) {
        operationForm.addEventListener('submit', handleFormSubmit);
    }
    if (resetButton) {
        resetButton.addEventListener('click', resetForm);
    }

    loadPrerequisites();
    fetchOperations();

    // Helper to format currency
    function formatCurrency(amount) {
        return amount !== null && amount !== undefined ? new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount) : 'N/A';
    }

    // Helper to format date and time
    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        try {
            const date = new Date(dateTimeString);
            if (isNaN(date.getTime())) return 'Date invalide';
            return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
        } catch (e) {
            return dateTimeString; // return original if parsing fails
        }
    }
});
