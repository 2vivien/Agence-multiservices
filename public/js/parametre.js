document.addEventListener('DOMContentLoaded', () => {
    // 1. Check authentication and role
    if (typeof checkAuthStatusAndRedirect !== 'function' || typeof logoutUser !== 'function') {
        console.error('common.js is not loaded or essential functions are missing.');
        alert('Erreur critique: Fichiers de base manquants. Veuillez contacter le support.');
        return;
    }
    checkAuthStatusAndRedirect();

    const userRole = localStorage.getItem('userRole');
    if (userRole !== 'admin') {
        alert('Accès interdit. Cette page est réservée aux administrateurs.');
        window.location.href = 'index.html'; // Or a relevant redirect for non-admins
        return;
    }

    // Global state
    let currentEditingUserId = null;
    let currentEditingServiceId = null;
    let usersList = [];
    let servicesList = [];

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
    const userCancelButton = document.getElementById('user-form-cancel');
    const addUserButton = document.getElementById('add-user-button');
    const userFormSection = document.getElementById('user-form-section'); // To show/hide form

    // DOM Elements - Services
    const servicesTableBody = document.getElementById('services-table-body');
    const serviceForm = document.getElementById('service-form');
    const serviceFormTitle = document.getElementById('service-form-title');
    const serviceIdInput = document.getElementById('form-service-id-hidden'); // Hidden field for ID
    const serviceNameInput = document.getElementById('form-service-name');
    const serviceDescriptionInput = document.getElementById('form-service-description');
    const serviceCommissionInput = document.getElementById('form-service-commission');
    const serviceIsActiveCheckbox = document.getElementById('form-service-isactive');
    const serviceSubmitButton = serviceForm ? serviceForm.querySelector('button[type="submit"]') : null;
    const serviceCancelButton = document.getElementById('service-form-cancel');
    const addServiceButton = document.getElementById('add-service-button');
    const serviceFormSection = document.getElementById('service-form-section'); // To show/hide form

    // Notifications
    const globalNotification = document.getElementById('global-notification');

    // Tab handling (if any, simple for now: show/hide form sections)
    if(addUserButton && userFormSection) addUserButton.addEventListener('click', () => { userFormSection.classList.remove('hidden'); resetUserForm(); });
    if(userCancelButton && userFormSection) userCancelButton.addEventListener('click', () => { userFormSection.classList.add('hidden'); });

    if(addServiceButton && serviceFormSection) addServiceButton.addEventListener('click', () => { serviceFormSection.classList.remove('hidden'); resetServiceForm(); });
    if(serviceCancelButton && serviceFormSection) serviceCancelButton.addEventListener('click', () => { serviceFormSection.classList.add('hidden'); });


    // --- User Management ---
    async function fetchUsers() {
        try {
            const response = await fetch('/api/admin/users', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            usersList = await response.json(); // Assuming API directly returns array or needs .data
             renderUsersTable(usersList.data || usersList);
        } catch (error) {
            console.error('Error fetching users:', error);
            showNotification('Erreur de chargement des utilisateurs.', 'error');
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
                <td class="px-4 py-2 border-b">${user.username}</td>
                <td class="px-4 py-2 border-b">${user.full_name}</td>
                <td class="px-4 py-2 border-b">${user.email || '-'}</td>
                <td class="px-4 py-2 border-b">${user.role}</td>
                <td class="px-4 py-2 border-b">${user.is_active ? '<span class="text-green-500">Actif</span>' : '<span class="text-red-500">Inactif</span>'}</td>
                <td class="px-4 py-2 border-b text-right">
                    <button data-id="${user.id}" class="edit-user-btn text-indigo-600 hover:text-indigo-900 mr-2"><i class="fas fa-edit"></i></button>
                    <button data-id="${user.id}" class="delete-user-btn text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                </td>
            `;
        });
        document.querySelectorAll('.edit-user-btn').forEach(btn => btn.addEventListener('click', (e) => editUser(e.currentTarget.dataset.id)));
        document.querySelectorAll('.delete-user-btn').forEach(btn => btn.addEventListener('click', (e) => deleteUser(e.currentTarget.dataset.id)));
    }

    if (userForm) userForm.addEventListener('submit', handleUserFormSubmit);
    async function handleUserFormSubmit(event) {
        event.preventDefault();
        const formData = {
            username: usernameInput.value,
            full_name: userFullNameInput.value,
            email: userEmailInput.value,
            role: userRoleSelect.value,
            is_active: userIsActiveCheckbox.checked
        };
        if (!currentEditingUserId && userPasswordInput.value) { // Password only for create or if explicitly changing
            formData.password = userPasswordInput.value;
            if (userPasswordInput.value !== userConfirmPasswordInput.value) {
                showNotification("Les mots de passe ne correspondent pas.", "error");
                return;
            }
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
                showNotification(currentEditingUserId ? 'Utilisateur mis à jour!' : 'Utilisateur créé!', 'success');
                fetchUsers();
                resetUserForm();
                if(userFormSection) userFormSection.classList.add('hidden');
            } else {
                const errors = responseData.errors ? Object.values(responseData.errors).join(', ') : (responseData.error || "Erreur inconnue");
                showNotification(`Erreur: ${errors}`, 'error');
            }
        } catch (error) {
            console.error('Error submitting user form:', error);
            showNotification('Erreur réseau.', 'error');
        }
    }

    async function editUser(userId) {
        try {
            const response = await fetch(`/api/admin/users/${userId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Utilisateur non trouvé');
            const user = await response.json();

            if(userFormTitle) userFormTitle.textContent = "Modifier l'Utilisateur";
            if(userSubmitButton) userSubmitButton.innerHTML = '<i class="fas fa-save mr-1"></i> Mettre à jour Utilisateur';
            currentEditingUserId = user.id;
            userIdInput.value = user.id;
            usernameInput.value = user.username;
            userFullNameInput.value = user.full_name;
            userEmailInput.value = user.email || '';
            userRoleSelect.value = user.role;
            userIsActiveCheckbox.checked = user.is_active;
            userPasswordInput.placeholder = "Laisser vide pour ne pas changer";
            userConfirmPasswordInput.placeholder = "Laisser vide pour ne pas changer";
            if(userFormSection) userFormSection.classList.remove('hidden');
        } catch (error) {
            showNotification(`Erreur: ${error.message}`, 'error');
        }
    }

    async function deleteUser(userId) {
        if (!confirm(`Supprimer l'utilisateur ID ${userId}? Cette action est irréversible.`)) return;
        try {
            const response = await fetch(`/api/admin/users/${userId}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.ok) { // 204 No Content
                showNotification('Utilisateur supprimé!', 'success');
                fetchUsers();
            } else {
                const responseData = await response.json().catch(()=>null);
                showNotification(`Erreur: ${responseData?.error || response.statusText}`, 'error');
            }
        } catch (error) {
            showNotification('Erreur réseau.', 'error');
        }
    }

    function resetUserForm() {
        if(userForm) userForm.reset();
        currentEditingUserId = null;
        if(userIdInput) userIdInput.value = '';
        if(userFormTitle) userFormTitle.textContent = "Ajouter un Nouvel Utilisateur";
        if(userSubmitButton) userSubmitButton.innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Ajouter Utilisateur';
        if(userPasswordInput) userPasswordInput.placeholder = "Mot de passe";
        if(userConfirmPasswordInput) userConfirmPasswordInput.placeholder = "Confirmer mot de passe";
    }

    // --- Service Management ---
    async function fetchServices() {
         try {
            const response = await fetch('/api/admin/services', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            servicesList = await response.json();
            renderServicesTable(servicesList.data || servicesList);
        } catch (error) {
            console.error('Error fetching services:', error);
            showNotification('Erreur de chargement des services.', 'error');
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
                <td class="px-4 py-2 border-b">${service.name}</td>
                <td class="px-4 py-2 border-b">${service.description || '-'}</td>
                <td class="px-4 py-2 border-b">${service.default_commission_rate !== null ? service.default_commission_rate + '%' : '-'}</td>
                <td class="px-4 py-2 border-b">${service.is_active ? '<span class="text-green-500">Actif</span>' : '<span class="text-red-500">Inactif</span>'}</td>
                <td class="px-4 py-2 border-b text-right">
                    <button data-id="${service.id}" class="edit-service-btn text-indigo-600 hover:text-indigo-900 mr-2"><i class="fas fa-edit"></i></button>
                    <button data-id="${service.id}" class="delete-service-btn text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                </td>
            `;
        });
        document.querySelectorAll('.edit-service-btn').forEach(btn => btn.addEventListener('click', (e) => editService(e.currentTarget.dataset.id)));
        document.querySelectorAll('.delete-service-btn').forEach(btn => btn.addEventListener('click', (e) => deleteService(e.currentTarget.dataset.id)));
    }

    if(serviceForm) serviceForm.addEventListener('submit', handleServiceFormSubmit);
    async function handleServiceFormSubmit(event) {
        event.preventDefault();
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
                showNotification(currentEditingServiceId ? 'Service mis à jour!' : 'Service créé!', 'success');
                fetchServices();
                resetServiceForm();
                if(serviceFormSection) serviceFormSection.classList.add('hidden');
            } else {
                const errors = responseData.errors ? Object.values(responseData.errors).join(', ') : (responseData.error || "Erreur inconnue");
                showNotification(`Erreur: ${errors}`, 'error');
            }
        } catch (error) {
            showNotification('Erreur réseau.', 'error');
        }
    }

    async function editService(serviceId) {
         try {
            const response = await fetch(`/api/admin/services/${serviceId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Service non trouvé');
            const service = await response.json();

            if(serviceFormTitle) serviceFormTitle.textContent = "Modifier le Service";
            if(serviceSubmitButton) serviceSubmitButton.innerHTML = '<i class="fas fa-save mr-1"></i> Mettre à jour Service';
            currentEditingServiceId = service.id;
            serviceIdInput.value = service.id;
            serviceNameInput.value = service.name;
            serviceDescriptionInput.value = service.description || '';
            serviceCommissionInput.value = service.default_commission_rate !== null ? service.default_commission_rate : '';
            serviceIsActiveCheckbox.checked = service.is_active;
            if(serviceFormSection) serviceFormSection.classList.remove('hidden');
        } catch (error) {
            showNotification(`Erreur: ${error.message}`, 'error');
        }
    }

    async function deleteService(serviceId) {
        if (!confirm(`Supprimer le service ID ${serviceId}? Cela pourrait affecter les opérations existantes.`)) return;
        try {
            const response = await fetch(`/api/admin/services/${serviceId}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.ok) {
                showNotification('Service supprimé!', 'success'); // Or "désactivé" if soft delete
                fetchServices();
            } else {
                 const responseData = await response.json().catch(()=>null);
                showNotification(`Erreur: ${responseData?.error || response.statusText}`, 'error');
            }
        } catch (error) {
            showNotification('Erreur réseau.', 'error');
        }
    }

    function resetServiceForm() {
        if(serviceForm) serviceForm.reset();
        currentEditingServiceId = null;
        if(serviceIdInput) serviceIdInput.value = '';
        if(serviceFormTitle) serviceFormTitle.textContent = "Ajouter un Nouveau Service";
        if(serviceSubmitButton) serviceSubmitButton.innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Ajouter Service';
    }

    // --- General Utilities ---
    function showNotification(message, type = 'info') { // type = 'info' | 'success' | 'error'
        if (!globalNotification) return;
        globalNotification.textContent = message;
        globalNotification.className = 'mb-4 p-4 rounded-md text-white'; // Reset classes
        if (type === 'success') {
            globalNotification.classList.add('bg-green-500');
        } else if (type === 'error') {
            globalNotification.classList.add('bg-red-500');
        } else {
            globalNotification.classList.add('bg-blue-500');
        }
        globalNotification.classList.remove('hidden');
        setTimeout(() => {
            globalNotification.classList.add('hidden');
        }, 5000);
    }

    // Initial data load
    fetchUsers();
    fetchServices();
});
