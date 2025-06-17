document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication and role
    if (typeof checkAuthStatusAndRedirect !== 'function' || typeof logoutUser !== 'function') {
        console.error('common.js is not loaded or essential functions are missing.');
        alert('Erreur critique: Fichiers de base manquants. Veuillez contacter le support.');
        // Redirect to login if possible, though common.js should handle this
        // window.location.href = 'index.html';
        return;
    }
    checkAuthStatusAndRedirect(); // Ensures authenticated

    // Specific role check for 'gerant'
    const userRole = localStorage.getItem('userRole');
    if (userRole !== 'gerant') {
        alert('Accès interdit. Cette page est réservée aux gérants.');
        // Redirect to a relevant page or show an error message
        // Depending on application flow, could redirect to login or a generic dashboard
        window.location.href = 'index.html'; // Or gerant-dashboard if that's the main entry
        return;
    }

    // State variables
    let initialBalanceToday = 0;
    let unclosedOperations = [];
    let theoreticalBalance = 0;

    // DOM Elements
    const initialBalanceEl = document.getElementById('cloture-initial-balance');
    const operationsTableBodyEl = document.getElementById('cloture-operations-table-body');
    const theoreticalBalanceEl = document.getElementById('cloture-theoretical-balance');
    const actualFinalAmountInput = document.getElementById('form-actual-final-amount');
    const differenceEl = document.getElementById('cloture-difference');
    const clotureNotesInput = document.getElementById('form-cloture-notes');
    const clotureForm = document.getElementById('cloture-form');
    const submitButton = clotureForm ? clotureForm.querySelector('button[type="submit"]') : null;

    const loadingIndicator = document.getElementById('cloture-loading');
    const clotureDataSection = document.getElementById('cloture-data-section');
    const formSection = document.getElementById('cloture-form-section');
    const successMessageEl = document.getElementById('cloture-success-message');
    const errorMessageEl = document.getElementById('cloture-error-message');


    /**
     * Fetch initial data for the cloture page
     */
    async function fetchClotureData() {
        if (loadingIndicator) loadingIndicator.classList.remove('hidden');
        if (clotureDataSection) clotureDataSection.classList.add('hidden');
        if (formSection) formSection.classList.add('hidden');
        if (successMessageEl) successMessageEl.classList.add('hidden');
        if (errorMessageEl) errorMessageEl.classList.add('hidden');

        try {
            const response = await fetch('/api/cloture/today', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: 'Erreur inconnue' }));
                if (response.status === 401 || response.status === 403) logoutUser();
                showError(errorData.error || `Erreur ${response.status} lors de la récupération des données de clôture.`);
                return;
            }

            const data = await response.json();

            initialBalanceToday = data.initial_amount_today;
            unclosedOperations = data.unclosed_operations || [];
            theoreticalBalance = data.calculated_theoretical_amount;

            displayInitialBalance(initialBalanceToday);
            renderUnclosedOperations(unclosedOperations);
            displayTheoreticalBalance(theoreticalBalance);

            if (clotureDataSection) clotureDataSection.classList.remove('hidden');
            if (formSection) formSection.classList.remove('hidden');

        } catch (error) {
            console.error('Error fetching cloture data:', error);
            showError('Une erreur réseau est survenue. Impossible de charger les données de clôture.');
        } finally {
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
        }
    }

    function displayInitialBalance(amount) {
        if (initialBalanceEl) initialBalanceEl.textContent = formatCurrency(amount);
    }

    function renderUnclosedOperations(operations) {
        if (!operationsTableBodyEl) return;
        operationsTableBodyEl.innerHTML = ''; // Clear

        if (operations.length === 0) {
            operationsTableBodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-gray-500">Aucune opération non clôturée pour aujourd\'hui.</td></tr>';
            return;
        }

        operations.forEach(op => {
            const row = operationsTableBodyEl.insertRow();
            // Assuming op structure from Operation model (id, operation_time, description, amount, service_id, operation_type_id)
            // And that service/operation_type names might be resolved by backend or need lookup here if IDs are sent
            row.innerHTML = `
                <td class="p-2 border-b text-sm">${formatDateTime(op.operation_time)}</td>
                <td class="p-2 border-b text-sm">${op.description || '-'}</td>
                <td class="p-2 border-b text-sm text-right">${formatCurrency(op.amount)}</td>
                <td class="p-2 border-b text-sm text-right">${formatCurrency(op.commission_applied)}</td>
            `;
        });
    }

    function displayTheoreticalBalance(amount) {
        if (theoreticalBalanceEl) theoreticalBalanceEl.textContent = formatCurrency(amount);
        // Update difference when theoretical balance is known
        updateDifference();
    }

    function updateDifference() {
        if (!actualFinalAmountInput || !differenceEl) return;
        const actualAmount = parseFloat(actualFinalAmountInput.value);
        if (!isNaN(actualAmount)) {
            const diff = actualAmount - theoreticalBalance;
            differenceEl.textContent = formatCurrency(diff);
            differenceEl.className = diff < 0 ? 'text-red-600 font-semibold' : (diff > 0 ? 'text-green-600 font-semibold' : 'text-gray-700 font-semibold');
        } else {
            differenceEl.textContent = 'N/A';
            differenceEl.className = 'text-gray-700 font-semibold';
        }
    }

    /**
     * Handle the submission of the cloture form
     */
    async function handleClotureSubmit(event) {
        event.preventDefault();
        if (!actualFinalAmountInput || !clotureForm) return;

        const actualAmount = parseFloat(actualFinalAmountInput.value);
        const notes = clotureNotesInput ? clotureNotesInput.value.trim() : null;

        if (isNaN(actualAmount)) {
            showError('Le solde réel final doit être un montant numérique valide.');
            actualFinalAmountInput.focus();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Soumission...';
        }
        hideMessages();

        const payload = {
            actual_final_amount: actualAmount,
            notes: notes,
            // Optional: send calculated_theoretical_amount for backend verification if desired
            // calculated_theoretical_amount_frontend: theoreticalBalance
        };

        try {
            const response = await fetch('/api/cloture/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            const responseData = await response.json();

            if (response.ok) {
                showSuccess(`Clôture réussie! Écart: ${formatCurrency(responseData.balance.difference_amount)}. La page va se rafraîchir.`);
                // Disable form, maybe refresh page or redirect after a delay
                if (clotureForm) clotureForm.reset();
                if (actualFinalAmountInput) actualFinalAmountInput.disabled = true;
                if (clotureNotesInput) clotureNotesInput.disabled = true;
                if (submitButton) submitButton.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Clôturée!';
                setTimeout(() => window.location.reload(), 5000); // Refresh after 5s
            } else {
                showError(responseData.error || responseData.errors?.actual_final_amount || 'Erreur lors de la soumission de la clôture.');
                 if (submitButton) submitButton.disabled = false;
                 if (submitButton) submitButton.innerHTML = 'Clôturer la Journée';
            }

        } catch (error) {
            console.error('Error submitting cloture:', error);
            showError('Une erreur réseau est survenue lors de la soumission.');
            if (submitButton) submitButton.disabled = false;
            if (submitButton) submitButton.innerHTML = 'Clôturer la Journée';
        }
    }

    function hideMessages() {
        if (successMessageEl) successMessageEl.classList.add('hidden');
        if (errorMessageEl) errorMessageEl.classList.add('hidden');
    }

    function showSuccess(message) {
        hideMessages();
        if (successMessageEl) {
            successMessageEl.textContent = message;
            successMessageEl.classList.remove('hidden');
        } else {
            alert(message); // Fallback
        }
    }
    function showError(message) {
        hideMessages();
        if (errorMessageEl) {
            errorMessageEl.textContent = message;
            errorMessageEl.classList.remove('hidden');
        } else {
            alert(message); // Fallback
        }
    }


    // Event Listeners
    if (actualFinalAmountInput) {
        actualFinalAmountInput.addEventListener('input', updateDifference);
    }
    if (clotureForm) {
        clotureForm.addEventListener('submit', handleClotureSubmit);
    }

    // Initial data load
    fetchClotureData();

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
