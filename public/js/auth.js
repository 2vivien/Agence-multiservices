document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const emailField = document.getElementById('email'); // This is used as username
    const passwordField = document.getElementById('password');
    const togglePasswordButton = document.getElementById('togglePassword');
    const loginButton = document.getElementById('loginBtn');
    const errorMessageDiv = document.getElementById('errorMessage');

    // Toggle password visibility
    if (togglePasswordButton) {
        togglePasswordButton.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    }

    // Handle login form submission
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = emailField.value.trim(); // Field is 'email' in HTML, used as username
            const password = passwordField.value.trim();

            // Basic client-side validation
            if (!username || !password) {
                showError("Veuillez entrer votre nom d'utilisateur et votre mot de passe.");
                return;
            }

            // Set loading state
            setLoading(true);

            try {
                const response = await fetch('/api/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest' // For CSRF protection if implemented
                    },
                    body: JSON.stringify({ username: username, password: password })
                });

                const data = await response.json();

                if (response.ok) {
                    // Login successful
                    localStorage.setItem('isAuthenticated', 'true');
                    localStorage.setItem('userRole', data.user.role);
                    localStorage.setItem('userName', data.user.full_name);

                    // Clear any previous error messages
                    hideError();

                    // Show success message (optional, as redirecting)
                    // showSuccess(`Connexion réussie. Bienvenue ${data.user.full_name}! Redirection...`);


                    // Redirect based on role
                    if (data.user.role === 'admin') {
                        window.location.href = 'admin-dashboard.html';
                    } else if (data.user.role === 'gerant') {
                        window.location.href = 'gerant-dashboard.html';
                    } else {
                        // Fallback or error if role is unknown
                        showError("Rôle utilisateur non reconnu. Redirection vers la page d'accueil.");
                        setTimeout(() => window.location.href = 'index.html', 2000);
                    }
                } else {
                    // Login failed
                    showError(data.error || 'Erreur de connexion. Veuillez réessayer.');
                }
            } catch (error) {
                console.error('Login error:', error);
                showError('Une erreur technique est survenue. Veuillez réessayer plus tard.');
            } finally {
                // Reset loading state
                setLoading(false);
            }
        });
    }

    function showError(message) {
        errorMessageDiv.innerHTML = `<p>${message}</p>`; // Ensure message is wrapped in <p> or similar if HTML structure expects it
        errorMessageDiv.classList.remove('hidden');
        errorMessageDiv.classList.remove('bg-green-50', 'border-green-500', 'text-green-700');
        errorMessageDiv.classList.add('bg-red-50', 'border-red-500', 'text-red-700');
    }

    function hideError() {
        errorMessageDiv.classList.add('hidden');
    }

    function showSuccess(message) { // Optional, if needed before redirect
        errorMessageDiv.innerHTML = `<p>${message}</p>`;
        errorMessageDiv.classList.remove('hidden');
        errorMessageDiv.classList.remove('bg-red-50', 'border-red-500', 'text-red-700');
        errorMessageDiv.classList.add('bg-green-50', 'border-green-500', 'text-green-700');
    }


    function setLoading(isLoading) {
        if (isLoading) {
            loginButton.disabled = true;
            loginButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Connexion en cours...';
        } else {
            loginButton.disabled = false;
            loginButton.innerHTML = '<span>Connexion</span><i class="fas fa-arrow-right ml-2"></i>';
        }
    }

    // Remove or comment out demoLogin if it was part of this file
    // function demoLogin(role) { ... }
});
