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

    // Global state
    let currentEditingUserId = null;
    let currentEditingServiceId = null;
    let usersList = [];
    let servicesList = [];

    // Pagination state - Users
    let usersCurrentPage = 1;
    let usersTotalPages = 1;
    let usersTotalRecords = 0;
    const usersLimit = 10;

    // Pagination state - Services
    let servicesCurrentPage = 1;
    let servicesTotalPages = 1;
    let servicesTotalRecords = 0;
    const servicesLimit = 10;

    // DOM Elements - Users
    const usersTableBody = document.getElementById('users-table-body');
    const userForm = document.getElementById('user-form');
    const userFormTitle = document.getElementById('user-form-title');
    const userIdInput = document.getElementById('form-user-id');
    const usernameInput = document.getElementById('form-username');
    const userFullNameInput = document.getElementById('form-user-fullname');
    const userEmailInput = document.getElementById('form-user-email');
    const userPasswordInput = document.getElementById('form-user-password');
    const userConfirmPasswordInput = document.getElementById('form-user-confirm-password');
    const userRoleSelect = document.getElementById('form-user-role');
    const userIsActiveCheckbox = document.getElementById('form-user-isactive');
    const userSubmitButton = userForm ? userForm.querySelector('button[type="submit"]') : null;
    const userSubmitButtonText = document.getElementById('user-form-submit-text');
    const userCancelButton = document.getElementById('user-form-cancel');
    const addUserButton = document.getElementById('add-user-button');
    const userFormSection = document.getElementById('user-form-section');
    const usersTableLoading = document.getElementById('users-table-loading');
    const usersTableContainer = document.getElementById('users-table-container');
    const usersPaginationInfoEl = document.getElementById('users-pagination-info');
    const usersPrevPageButton = document.getElementById('users-prev-page-btn');
    const usersNextPageButton = document.getElementById('users-next-page-btn');


    // DOM Elements - Services
    const servicesTableBody = document.getElementById('services-table-body');
    const serviceForm = document.getElementById('service-form');
    const serviceFormTitle = document.getElementById('service-form-title');
    const serviceIdInput = document.getElementById('form-service-id-hidden');
    const serviceNameInput = document.getElementById('form-service-name');
    const serviceDescriptionInput = document.getElementById('form-service-description');
    const serviceCommissionInput = document.getElementById('form-service-commission');
    const serviceIsActiveCheckbox = document.getElementById('form-service-isactive');
    const serviceSubmitButton = serviceForm ? serviceForm.querySelector('button[type="submit"]') : null;
    const serviceSubmitButtonText = document.getElementById('service-form-submit-text');
    const serviceCancelButton = document.getElementById('service-form-cancel');
    const addServiceButton = document.getElementById('add-service-button');
    const serviceFormSection = document.getElementById('service-form-section');
    const servicesTableLoading = document.getElementById('services-table-loading');
    const servicesTableContainer = document.getElementById('services-table-container');
    const servicesPaginationInfoEl = document.getElementById('services-pagination-info');
    const servicesPrevPageButton = document.getElementById('services-prev-page-btn');
    const servicesNextPageButton = document.getElementById('services-next-page-btn');

    // Tab handling
    if(addUserButton && userFormSection) addUserButton.addEventListener('click', () => { userFormSection.classList.remove('hidden'); if(serviceFormSection) serviceFormSection.classList.add('hidden'); resetUserForm(); });
    if(userCancelButton && userFormSection) userCancelButton.addEventListener('click', () => { userFormSection.classList.add('hidden'); });

    if(addServiceButton && serviceFormSection) addServiceButton.addEventListener('click', () => { serviceFormSection.classList.remove('hidden'); if(userFormSection) userFormSection.classList.add('hidden'); resetServiceForm(); });
    if(serviceCancelButton && serviceFormSection) serviceCancelButton.addEventListener('click', () => { serviceFormSection.classList.add('hidden'); });

    // --- User Management ---
    async function fetchUsers(page = 1) {
        if(usersTableLoading) usersTableLoading.classList.remove('hidden');
        if(usersTableContainer) usersTableContainer.classList.add('hidden');
        usersCurrentPage = page;
        const queryParams = new URLSearchParams({ page: usersCurrentPage, limit: usersLimit }).toString();

        try {
            const response = await fetch(`/api/admin/users?${queryParams}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                await handleApiError(response);
                usersList = [];
                usersTotalRecords = 0;
            } else {
                const result = await response.json();
                // Backend needs to return { data: [...], pagination: { total_records: X, ...} }
                usersList = result.data || result; // Adapt if not wrapped
                usersTotalRecords = result.pagination?.total_records || (Array.isArray(result) ? result.length : 0);
                usersCurrentPage = result.pagination?.current_page || page;
                usersTotalPages = result.pagination?.total_pages || Math.ceil(usersTotalRecords / usersLimit) || 1;

            }
            renderUsersTable(usersList);
            renderUsersPagination();
        } catch (error) {
            console.error('Error fetching users:', error);
            showGlobalNotification('Erreur réseau lors du chargement des utilisateurs.', 'error');
            renderUsersTable([]);
            renderUsersPagination();
        } finally {
            if(usersTableLoading) usersTableLoading.classList.add('hidden');
            if(usersTableContainer) usersTableContainer.classList.remove('hidden');
        }
    }

    function renderUsersTable(users) {
        if (!usersTableBody) return;
        usersTableBody.innerHTML = '';
        if (!users || users.length === 0) {
            usersTableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucun utilisateur trouvé.</td></tr>';
            return;
        }
        users.forEach(user => {
            const row = usersTableBody.insertRow();
            row.innerHTML = `
                <td class="px-4 py-2 border-b text-sm">${user.username}</td>
                <td class="px-4 py-2 border-b text-sm">${user.full_name}</td>
                <td class="px-4 py-2 border-b text-sm">${user.email || '-'}</td>
                <td class="px-4 py-2 border-b text-sm">${user.role}</td>
                <td class="px-4 py-2 border-b text-sm">${user.is_active ? '<span class="text-green-600 font-semibold">Actif</span>' : '<span class="text-red-500 font-semibold">Inactif</span>'}</td>
                <td class="px-4 py-2 border-b text-sm text-right">
                    <button data-id="${user.id}" class="edit-user-btn text-indigo-600 hover:text-indigo-800 mr-2" title="Modifier"><i class="fas fa-edit"></i></button>
                    <button data-id="${user.id}" class="delete-user-btn text-red-600 hover:text-red-800" title="Supprimer"><i class="fas fa-trash"></i></button>
                </td>
            `;
        });
        document.querySelectorAll('.edit-user-btn').forEach(btn => btn.addEventListener('click', (e) => editUser(e.currentTarget.dataset.id)));
        document.querySelectorAll('.delete-user-btn').forEach(btn => btn.addEventListener('click', (e) => deleteUser(e.currentTarget.dataset.id)));
    }

    function renderUsersPagination() {
        if (!usersPaginationInfoEl || !usersPrevPageButton || !usersNextPageButton) return;
        usersTotalPages = Math.ceil(usersTotalRecords / usersLimit) || 1;
        usersPaginationInfoEl.textContent = `Page ${usersCurrentPage} sur ${usersTotalPages}. Total: ${usersTotalRecords} utilisateurs.`;
        usersPrevPageButton.disabled = usersCurrentPage <= 1;
        usersNextPageButton.disabled = usersCurrentPage >= usersTotalPages;
    }


    if (userForm) userForm.addEventListener('submit', handleUserFormSubmit);
    async function handleUserFormSubmit(event) {
        event.preventDefault();
        const originalButtonText = userSubmitButtonText ? userSubmitButtonText.textContent : 'Soumettre';
        if (userSubmitButton) userSubmitButton.disabled = true;
        if (userSubmitButtonText) userSubmitButtonText.textContent = currentEditingUserId ? 'Sauvegarde...' : 'Création...';

        const formData = {
            username: usernameInput.value,
            full_name: userFullNameInput.value,
            email: userEmailInput.value,
            role: userRoleSelect.value,
            is_active: userIsActiveCheckbox.checked
        };

        if (!currentEditingUserId && !userPasswordInput.value) {
             showGlobalNotification("Le mot de passe est requis pour la création d'un utilisateur.", "error");
             if (userSubmitButton) userSubmitButton.disabled = false;
             if (userSubmitButtonText) userSubmitButtonText.textContent = originalButtonText;
             return;
        }
        if (userPasswordInput.value) {
            if (userPasswordInput.value !== userConfirmPasswordInput.value) {
                showGlobalNotification("Les mots de passe ne correspondent pas.", "error");
                if (userSubmitButton) userSubmitButton.disabled = false;
                if (userSubmitButtonText) userSubmitButtonText.textContent = originalButtonText;
                return;
            }
            formData.password = userPasswordInput.value;
        }

        const method = currentEditingUserId ? 'PUT' : 'POST';
        const url = currentEditingUserId ? `/api/admin/users/${currentEditingUserId}` : '/api/admin/users';

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(formData)
            });
            const responseData = await response.json();
            if (response.ok) {
                showGlobalNotification(currentEditingUserId ? 'Utilisateur mis à jour avec succès!' : 'Utilisateur créé avec succès!', 'success');
                fetchUsers(currentEditingUserId ? usersCurrentPage : 1); // Refresh current page or go to first
                resetUserForm();
                if(userFormSection) userFormSection.classList.add('hidden');
            } else {
                if (response.status === 422 && responseData.errors) {
                    const errors = Object.entries(responseData.errors).map(([field, msg]) => `${field}: ${msg}`).join('; ');
                    showGlobalNotification(`Erreurs de validation: ${errors}`, 'error');
                } else if (!await handleApiError(response)) {
                     const errorMsg = responseData.error || responseData.message || "Erreur lors de la soumission du formulaire utilisateur.";
                     showGlobalNotification(`Erreur: ${errorMsg}`, 'error');
                }
            }
        } catch (error) {
            console.error('Error submitting user form:', error);
            showGlobalNotification('Erreur réseau lors de la soumission.', 'error');
        } finally {
            if (userSubmitButton) userSubmitButton.disabled = false;
            if (userSubmitButtonText) userSubmitButtonText.textContent = originalButtonText;
        }
    }

    async function editUser(userId) {
        try {
            const response = await fetch(`/api/admin/users/${userId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                await handleApiError(response);
                throw new Error('Utilisateur non trouvé ou accès refusé');
            }
            const user = await response.json();

            if(userFormTitle) userFormTitle.textContent = "Modifier l'Utilisateur";
            if(userSubmitButtonText) userSubmitButtonText.textContent = 'Mettre à jour';
            currentEditingUserId = user.id;
            if(userIdInput) userIdInput.value = user.id;
            usernameInput.value = user.username;
            userFullNameInput.value = user.full_name;
            userEmailInput.value = user.email || '';
            userRoleSelect.value = user.role;
            userIsActiveCheckbox.checked = user.is_active;
            userPasswordInput.value = '';
            userConfirmPasswordInput.value = '';
            userPasswordInput.placeholder = "Laisser vide pour ne pas changer";
            userConfirmPasswordInput.placeholder = "Laisser vide pour ne pas changer";
            if(serviceFormSection) serviceFormSection.classList.add('hidden');
            if(userFormSection) userFormSection.classList.remove('hidden');
        } catch (error) {
            showGlobalNotification(`Erreur lors du chargement de l'utilisateur: ${error.message}`, 'error');
        }
    }

    async function deleteUser(userId) {
        if (!confirm(`Êtes-vous sûr de vouloir désactiver l'utilisateur ID ${userId}?`)) return; // Changed to "désactiver"
        try {
            // UserController@destroy now deactivates user (soft delete)
            const response = await fetch(`/api/admin/users/${userId}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            // Expect 200 with user data, or 204 if no content returned by backend on deactivate
            if (response.ok) {
                showGlobalNotification('Utilisateur désactivé avec succès!', 'success');
                fetchUsers(usersCurrentPage);
                 if (currentEditingUserId == userId) { resetUserForm(); if(userFormSection) userFormSection.classList.add('hidden');}
            } else {
                await handleApiError(response);
            }
        } catch (error) {
            showGlobalNotification('Erreur réseau lors de la désactivation de l\'utilisateur.', 'error');
        }
    }

    function resetUserForm() {
        if(userForm) userForm.reset();
        currentEditingUserId = null;
        if(userIdInput) userIdInput.value = '';
        if(userFormTitle) userFormTitle.textContent = "Ajouter un Nouvel Utilisateur";
        if(userSubmitButtonText) userSubmitButtonText.textContent = 'Ajouter';
        if(userPasswordInput) userPasswordInput.placeholder = "Mot de passe";
        if(userConfirmPasswordInput) userConfirmPasswordInput.placeholder = "Confirmer mot de passe";
    }

    // --- Service Management ---
    async function fetchServices(page = 1) {
        if(servicesTableLoading) servicesTableLoading.classList.remove('hidden');
        if(servicesTableContainer) servicesTableContainer.classList.add('hidden');
        servicesCurrentPage = page;
        const queryParams = new URLSearchParams({ page: servicesCurrentPage, limit: servicesLimit }).toString();

         try {
            const response = await fetch(`/api/admin/services?${queryParams}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                await handleApiError(response);
                servicesList = [];
                servicesTotalRecords = 0;
            } else {
                const result = await response.json();
                servicesList = result.data || result;
                servicesTotalRecords = result.pagination?.total_records || (Array.isArray(result) ? result.length : 0);
                servicesCurrentPage = result.pagination?.current_page || page;
                servicesTotalPages = result.pagination?.total_pages || Math.ceil(servicesTotalRecords / servicesLimit) || 1;
            }
            renderServicesTable(servicesList);
            renderServicesPagination();
        } catch (error) {
            console.error('Error fetching services:', error);
            showGlobalNotification('Erreur réseau lors du chargement des services.', 'error');
            renderServicesTable([]);
            renderServicesPagination();
        } finally {
            if(servicesTableLoading) servicesTableLoading.classList.add('hidden');
            if(servicesTableContainer) servicesTableContainer.classList.remove('hidden');
        }
    }

    function renderServicesTable(services) {
        if (!servicesTableBody) return;
        servicesTableBody.innerHTML = '';
        if (!services || services.length === 0) {
            servicesTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Aucun service trouvé.</td></tr>';
            return;
        }
        services.forEach(service => {
            const row = servicesTableBody.insertRow();
            row.innerHTML = `
                <td class="px-4 py-2 border-b text-sm">${service.name}</td>
                <td class="px-4 py-2 border-b text-sm">${service.description || '-'}</td>
                <td class="px-4 py-2 border-b text-sm">${service.default_commission_rate !== null ? service.default_commission_rate + '%' : '-'}</td>
                <td class="px-4 py-2 border-b text-sm">${service.is_active ? '<span class="text-green-600 font-semibold">Actif</span>' : '<span class="text-red-500 font-semibold">Inactif</span>'}</td>
                <td class="px-4 py-2 border-b text-sm text-right">
                    <button data-id="${service.id}" class="edit-service-btn text-indigo-600 hover:text-indigo-800 mr-2" title="Modifier"><i class="fas fa-edit"></i></button>
                    <button data-id="${service.id}" class="delete-service-btn text-red-600 hover:text-red-800" title="Supprimer"><i class="fas fa-trash"></i></button>
                </td>
            `;
        });
        document.querySelectorAll('.edit-service-btn').forEach(btn => btn.addEventListener('click', (e) => editService(e.currentTarget.dataset.id)));
        document.querySelectorAll('.delete-service-btn').forEach(btn => btn.addEventListener('click', (e) => deleteService(e.currentTarget.dataset.id)));
    }

    function renderServicesPagination() {
        if (!servicesPaginationInfoEl || !servicesPrevPageButton || !servicesNextPageButton) return;
        servicesTotalPages = Math.ceil(servicesTotalRecords / servicesLimit) || 1;
        servicesPaginationInfoEl.textContent = `Page ${servicesCurrentPage} sur ${servicesTotalPages}. Total: ${servicesTotalRecords} services.`;
        servicesPrevPageButton.disabled = servicesCurrentPage <= 1;
        servicesNextPageButton.disabled = servicesCurrentPage >= servicesTotalPages;
    }


    if(serviceForm) serviceForm.addEventListener('submit', handleServiceFormSubmit);
    async function handleServiceFormSubmit(event) {
        event.preventDefault();
        const originalButtonText = serviceSubmitButtonText ? serviceSubmitButtonText.textContent : 'Soumettre';
        if(serviceSubmitButton) serviceSubmitButton.disabled = true;
        if(serviceSubmitButtonText) serviceSubmitButtonText.textContent = currentEditingServiceId ? 'Sauvegarde...' : 'Création...';

        const formData = {
            name: serviceNameInput.value,
            description: serviceDescriptionInput.value,
            default_commission_rate: serviceCommissionInput.value ? parseFloat(serviceCommissionInput.value) : null,
            is_active: serviceIsActiveCheckbox.checked
        };

        const method = currentEditingServiceId ? 'PUT' : 'POST';
        const url = currentEditingServiceId ? `/api/admin/services/${currentEditingServiceId}` : '/api/admin/services';

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(formData)
            });
            const responseData = await response.json();
            if (response.ok) {
                showGlobalNotification(currentEditingServiceId ? 'Service mis à jour avec succès!' : 'Service créé avec succès!', 'success');
                fetchServices(currentEditingServiceId ? servicesCurrentPage : 1);
                resetServiceForm();
                if(serviceFormSection) serviceFormSection.classList.add('hidden');
            } else {
                 if (response.status === 422 && responseData.errors) {
                    const errors = Object.entries(responseData.errors).map(([field, msg]) => `${field}: ${msg}`).join('; ');
                    showGlobalNotification(`Erreurs de validation: ${errors}`, 'error');
                } else if (!await handleApiError(response)){
                    const errorMsg = responseData.error || responseData.message || "Erreur lors de la soumission du formulaire service.";
                    showGlobalNotification(`Erreur: ${errorMsg}`, 'error');
                }
            }
        } catch (error) {
            showGlobalNotification('Erreur réseau lors de la soumission.', 'error');
        } finally {
            if(serviceSubmitButton) serviceSubmitButton.disabled = false;
            if(serviceSubmitButtonText) serviceSubmitButtonText.textContent = originalButtonText;
        }
    }

    async function editService(serviceId) {
         try {
            const response = await fetch(`/api/admin/services/${serviceId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                await handleApiError(response);
                throw new Error('Service non trouvé ou accès refusé');
            }
            const service = await response.json();

            if(serviceFormTitle) serviceFormTitle.textContent = "Modifier le Service";
            if(serviceSubmitButtonText) serviceSubmitButtonText.textContent = 'Mettre à jour';
            currentEditingServiceId = service.id;
            if(serviceIdInput) serviceIdInput.value = service.id;
            serviceNameInput.value = service.name;
            serviceDescriptionInput.value = service.description || '';
            serviceCommissionInput.value = service.default_commission_rate !== null ? service.default_commission_rate : '';
            serviceIsActiveCheckbox.checked = service.is_active;
            if(userFormSection) userFormSection.classList.add('hidden');
            if(serviceFormSection) serviceFormSection.classList.remove('hidden');
        } catch (error) {
            showGlobalNotification(`Erreur lors du chargement du service: ${error.message}`, 'error');
        }
    }

    async function deleteService(serviceId) {
        if (!confirm(`Êtes-vous sûr de vouloir désactiver le service ID ${serviceId}?`)) return; // Changed to "désactiver"
        try {
            const response = await fetch(`/api/admin/services/${serviceId}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.ok) { // Backend ServiceController@destroy now deactivates and returns 200 with service data
                showGlobalNotification('Service désactivé avec succès!', 'success');
                fetchServices(servicesCurrentPage);
                if (currentEditingServiceId == serviceId) { resetServiceForm(); if(serviceFormSection) serviceFormSection.classList.add('hidden');}
            } else {
                await handleApiError(response);
            }
        } catch (error) {
            showGlobalNotification('Erreur réseau lors de la désactivation du service.', 'error');
        }
    }

    function resetServiceForm() {
        if(serviceForm) serviceForm.reset();
        currentEditingServiceId = null;
        if(serviceIdInput) serviceIdInput.value = '';
        if(serviceFormTitle) serviceFormTitle.textContent = "Ajouter un Nouveau Service";
        if(serviceSubmitButtonText) serviceSubmitButtonText.textContent = 'Ajouter';
    }

    // User Pagination Event Listeners
    if(usersPrevPageButton) usersPrevPageButton.addEventListener('click', () => {
        if (usersCurrentPage > 1) fetchUsers(usersCurrentPage - 1);
    });
    if(usersNextPageButton) usersNextPageButton.addEventListener('click', () => {
        if (usersCurrentPage < usersTotalPages) fetchUsers(usersCurrentPage + 1);
    });

    // Service Pagination Event Listeners
    if(servicesPrevPageButton) servicesPrevPageButton.addEventListener('click', () => {
        if (servicesCurrentPage > 1) fetchServices(servicesCurrentPage - 1);
    });
    if(servicesNextPageButton) servicesNextPageButton.addEventListener('click', () => {
        if (servicesCurrentPage < servicesTotalPages) fetchServices(servicesCurrentPage + 1);
    });

    // Initial data load
    fetchUsers(1);
    fetchServices(1);
});
