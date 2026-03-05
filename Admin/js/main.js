/**
 * Admin Login Page - Main JavaScript
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Get DOM elements
    const loginForm = document.querySelector('form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.querySelector('.login-btn');

    // Add input event listeners for real-time validation
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            validateEmail(this);
        });
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            validatePassword(this);
        });
    }

    // Form submission handler
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }

    // Email validation
    function validateEmail(input) {
        const email = input.value.trim();
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        removeError(input);
        
        if (email && !emailPattern.test(email)) {
            showError(input, 'Please enter a valid email address');
            return false;
        }
        
        return true;
    }

    // Password validation
    function validatePassword(input) {
        const password = input.value;
        
        removeError(input);
        
        if (password && password.length < 6) {
            showError(input, 'Password must be at least 6 characters');
            return false;
        }
        
        return true;
    }

    // Form validation
    function validateForm() {
        let isValid = true;
        
        // Validate email
        if (!emailInput.value.trim()) {
            showError(emailInput, 'Email is required');
            isValid = false;
        } else if (!validateEmail(emailInput)) {
            isValid = false;
        }
        
        // Validate password
        if (!passwordInput.value) {
            showError(passwordInput, 'Password is required');
            isValid = false;
        } else if (!validatePassword(passwordInput)) {
            isValid = false;
        }
        
        return isValid;
    }

    // Show error message
    function showError(input, message) {
        // Remove any existing error
        removeError(input);
        
        // Create error element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'input-error';
        errorDiv.style.color = '#ce1126';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '5px';
        errorDiv.style.marginLeft = '2px';
        errorDiv.textContent = message;
        
        // Add error class to input
        input.style.borderColor = '#ce1126';
        
        // Insert error after input
        input.parentNode.insertBefore(errorDiv, input.nextSibling);
    }

    // Remove error message
    function removeError(input) {
        // Reset input border
        input.style.borderColor = '#e0e0e0';
        
        // Remove error message if exists
        const nextElement = input.nextElementSibling;
        if (nextElement && nextElement.className === 'input-error') {
            nextElement.remove();
        }
    }

    // Add loading state to button on form submit
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            if (loginForm.checkValidity()) {
                this.classList.add('loading');
                this.textContent = 'LOGGING IN...';
            }
        });
    }

    // Auto-hide error messages after 5 seconds
    const errorMessage = document.querySelector('.error-message');
    if (errorMessage) {
        setTimeout(function() {
            errorMessage.style.transition = 'opacity 0.5s';
            errorMessage.style.opacity = '0';
            setTimeout(function() {
                if (errorMessage.parentNode) {
                    errorMessage.remove();
                }
            }, 500);
        }, 5000);
    }

    // Add focus effect to input groups
    const inputGroups = document.querySelectorAll('.input-group');
    inputGroups.forEach(group => {
        const input = group.querySelector('input');
        const label = group.querySelector('label');
        
        if (input && label) {
            // Check if input has value on page load
            if (input.value) {
                label.style.color = '#0038a8';
            }
            
            input.addEventListener('focus', function() {
                label.style.color = '#0038a8';
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    label.style.color = '#333';
                }
            });
        }
    });

    // Add ripple effect to login button
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.className = 'ripple-effect';
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    }

    // Add keyboard shortcut (Ctrl+Enter) to submit form
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            if (loginForm) {
                loginForm.submit();
            }
        }
    });

    // Remember me functionality (optional)
    const rememberMe = document.createElement('div');
    rememberMe.className = 'remember-me';
    rememberMe.style.marginBottom = '15px';
    rememberMe.style.display = 'flex';
    rememberMe.style.alignItems = 'center';
    rememberMe.style.gap = '8px';
    
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = 'remember';
    checkbox.style.width = '16px';
    checkbox.style.height = '16px';
    checkbox.style.cursor = 'pointer';
    
    const label = document.createElement('label');
    label.htmlFor = 'remember';
    label.textContent = 'Remember me';
    label.style.fontSize = '13px';
    label.style.color = '#666';
    label.style.cursor = 'pointer';
    
    rememberMe.appendChild(checkbox);
    rememberMe.appendChild(label);
    
    // Insert remember me before register link
    const registerLink = document.querySelector('.register-link');
    if (registerLink) {
        registerLink.parentNode.insertBefore(rememberMe, registerLink);
    }

    // Load saved email if "Remember me" was checked
    const savedEmail = localStorage.getItem('savedEmail');
    if (savedEmail && emailInput) {
        emailInput.value = savedEmail;
        checkbox.checked = true;
    }

    // Save email when form is submitted
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            if (checkbox.checked && emailInput) {
                localStorage.setItem('savedEmail', emailInput.value);
            } else {
                localStorage.removeItem('savedEmail');
            }
        });
    }
});
// Floating Action Button functions
function toggleFabMenu() {
    const fabOptions = document.getElementById('fabOptions');
    const fabMain = document.getElementById('fabMain');
    const fabIcon = document.getElementById('fabIcon');
    
    fabOptions.classList.toggle('active');
    fabMain.classList.toggle('active');
    
    // Change icon based on state
    if (fabMain.classList.contains('active')) {
        fabIcon.classList.remove('fa-plus');
        fabIcon.classList.add('fa-times');
    } else {
        fabIcon.classList.remove('fa-times');
        fabIcon.classList.add('fa-plus');
    }
}

// Close FAB menu when clicking outside
document.addEventListener('click', function(event) {
    const fabContainer = document.getElementById('fabContainer');
    const fabOptions = document.getElementById('fabOptions');
    const fabMain = document.getElementById('fabMain');
    const fabIcon = document.getElementById('fabIcon');
    
    if (!fabContainer.contains(event.target) && fabOptions.classList.contains('active')) {
        fabOptions.classList.remove('active');
        fabMain.classList.remove('active');
        fabIcon.classList.remove('fa-times');
        fabIcon.classList.add('fa-plus');
    }
});

// Close FAB menu when ESC key is pressed
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const fabOptions = document.getElementById('fabOptions');
        const fabMain = document.getElementById('fabMain');
        const fabIcon = document.getElementById('fabIcon');
        
        if (fabOptions.classList.contains('active')) {
            fabOptions.classList.remove('active');
            fabMain.classList.remove('active');
            fabIcon.classList.remove('fa-times');
            fabIcon.classList.add('fa-plus');
        }
    }
});

// Update your existing modal functions to close FAB when opening modals
function openCreateFolderModal() {
    document.getElementById('createFolderModal').classList.add('active');
    // Close FAB menu
    const fabOptions = document.getElementById('fabOptions');
    const fabMain = document.getElementById('fabMain');
    const fabIcon = document.getElementById('fabIcon');
    
    fabOptions.classList.remove('active');
    fabMain.classList.remove('active');
    fabIcon.classList.remove('fa-times');
    fabIcon.classList.add('fa-plus');
}

function openUploadFileModal() {
    document.getElementById('uploadFileModal').classList.add('active');
    // Close FAB menu
    const fabOptions = document.getElementById('fabOptions');
    const fabMain = document.getElementById('fabMain');
    const fabIcon = document.getElementById('fabIcon');
    
    fabOptions.classList.remove('active');
    fabMain.classList.remove('active');
    fabIcon.classList.remove('fa-times');
    fabIcon.classList.add('fa-plus');
}

// Optional: Add touch support for mobile
let touchStartX = 0;
let touchStartY = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
    touchStartY = e.changedTouches[0].screenY;
}, false);

document.addEventListener('touchend', function(e) {
    const touchEndX = e.changedTouches[0].screenX;
    const touchEndY = e.changedTouches[0].screenY;
    const fabContainer = document.getElementById('fabContainer');
    
    if (!fabContainer.contains(e.target)) {
        const fabOptions = document.getElementById('fabOptions');
        const fabMain = document.getElementById('fabMain');
        const fabIcon = document.getElementById('fabIcon');
        
        if (fabOptions.classList.contains('active')) {
            fabOptions.classList.remove('active');
            fabMain.classList.remove('active');
            fabIcon.classList.remove('fa-times');
            fabIcon.classList.add('fa-plus');
        }
    }
}, false);

// Add CSS for loading state and ripple effect
const style = document.createElement('style');
style.textContent = `
    .login-btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .ripple-effect {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.4);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .remember-me {
        margin-bottom: 15px;
        padding: 0 5px;
    }
    
    .remember-me input[type="checkbox"] {
        accent-color: #0038a8;
    }
    
    .remember-me label:hover {
        color: #0038a8;
    }
        
`;

document.head.appendChild(style);