document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* =========================
       LOGIN VARIABLES
    ========================= */

    const loginForm = document.querySelector('form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.querySelector('.login-btn');

    /* =========================
       DRIVE VARIABLES
    ========================= */

    let currentDeleteId = null;
    let currentDeleteType = null;
    let currentAction = null;
    let currentRenameId = null;
    let currentRenameType = null;
    let currentRenameName = null;


    /* =========================
       FAB MENU
    ========================= */

    window.toggleFabMenu = function () {
        const fabMenu = document.getElementById('fabMenu');
        if (fabMenu) {
            fabMenu.classList.toggle('show');
        }
    };


    /* =========================
       MODALS
    ========================= */

    window.openFolderModal = function () {
        const modal = document.getElementById('folderModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };

    window.openUploadModal = function () {
        const modal = document.getElementById('uploadModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(function () {
                if (window.toggleMonthField) {
                    toggleMonthField();
                }
            }, 100);
        }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    };


    /* =========================
       RENAME MODAL FUNCTIONS
    ========================= */

    window.renameFolder = function (id, currentName) {
        currentRenameId = id;
        currentRenameType = 'folder';
        currentRenameName = currentName;
        
        const modal = document.getElementById('renameModal');
        const title = document.getElementById('renameModalTitle');
        const input = document.getElementById('renameInput');
        const nameDisplay = document.getElementById('currentNameDisplay');
        
        if (modal && title && input && nameDisplay) {
            title.textContent = 'Rename Folder';
            input.value = currentName;
            input.placeholder = 'Enter new folder name';
            nameDisplay.textContent = currentName;
            modal.style.display = 'flex';
        }
    };

    window.renameFile = function (id, currentName) {
        currentRenameId = id;
        currentRenameType = 'file';
        currentRenameName = currentName;
        
        // Extract name without extension for display
        const lastDotIndex = currentName.lastIndexOf('.');
        const nameWithoutExt = lastDotIndex > 0 ? currentName.substring(0, lastDotIndex) : currentName;
        const extension = lastDotIndex > 0 ? currentName.substring(lastDotIndex) : '';
        
        const modal = document.getElementById('renameModal');
        const title = document.getElementById('renameModalTitle');
        const input = document.getElementById('renameInput');
        const nameDisplay = document.getElementById('currentNameDisplay');
        const extDisplay = document.getElementById('fileExtensionDisplay');
        
        if (modal && title && input && nameDisplay && extDisplay) {
            title.textContent = 'Rename File';
            input.value = nameWithoutExt;
            input.placeholder = 'Enter new file name (without extension)';
            nameDisplay.textContent = currentName;
            extDisplay.textContent = extension ? `Extension: ${extension}` : '';
            extDisplay.style.display = extension ? 'block' : 'none';
            modal.style.display = 'flex';
        }
    };

    // Confirm rename button
    const confirmRenameBtn = document.getElementById('confirmRenameBtn');
    if (confirmRenameBtn) {
        confirmRenameBtn.addEventListener('click', function () {
            const input = document.getElementById('renameInput');
            const newName = input.value.trim();
            
            if (!newName) {
                alert('Please enter a name');
                return;
            }
            
            if (currentRenameId && currentRenameType) {
                // Show loading state
                const originalHtml = confirmRenameBtn.innerHTML;
                confirmRenameBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Renaming...';
                confirmRenameBtn.disabled = true;
                
                if (currentRenameType === 'folder') {
                    window.location.href = `rename_folder.php?id=${currentRenameId}&name=${encodeURIComponent(newName)}`;
                } else if (currentRenameType === 'file') {
                    // For files, get the extension from the original name
                    const lastDotIndex = currentRenameName.lastIndexOf('.');
                    const extension = lastDotIndex > 0 ? currentRenameName.substring(lastDotIndex) : '';
                    const fullNewName = newName + extension;
                    window.location.href = `rename_file.php?id=${currentRenameId}&name=${encodeURIComponent(fullNewName)}`;
                }
            }
        });
    }


    /* =========================
       FILE NAME AUTO UPDATE
    ========================= */

    window.updateFileName = function () {
        const fileInput = document.getElementById('fileInput');
        const customFilename = document.getElementById('customFilename');

        if (fileInput && fileInput.files.length > 0) {
            const fullName = fileInput.files[0].name;
            const nameWithoutExt = fullName.substring(0, fullName.lastIndexOf('.')) || fullName;

            if (customFilename && !customFilename.value) {
                customFilename.value = nameWithoutExt;
            }
        }
    };


    /* =========================
       MONTH / YEAR FIELD LOGIC
    ========================= */

    window.toggleMonthField = function () {
        const folderSelect = document.getElementById('folderSelect');
        const monthField = document.getElementById('monthField');
        const yearField = document.getElementById('yearField');
        const yearInput = document.getElementById('yearInput');

        if (!folderSelect) {
            if (monthField) monthField.style.display = 'block';
            if (yearField) yearField.style.display = 'none';
            if (yearInput) yearInput.removeAttribute('name');
            return;
        }

        if (folderSelect.value) {
            const selectedOption = folderSelect.options[folderSelect.selectedIndex];
            const hasYear = selectedOption.getAttribute('data-has-year') === '1';

            if (hasYear) {
                if (yearField) yearField.style.display = 'none';
                if (yearInput) yearInput.removeAttribute('name');
                if (monthField) monthField.style.display = 'block';
            } else {
                if (yearField) yearField.style.display = 'block';
                if (yearInput) yearInput.setAttribute('name', 'year');
                if (monthField) monthField.style.display = 'block';
            }
        } else {
            if (yearField) yearField.style.display = 'block';
            if (yearInput) yearInput.setAttribute('name', 'year');
            if (monthField) monthField.style.display = 'block';
        }
    };


    /* =========================
       VIEW DETAILS
    ========================= */

    window.showFolderDetails = function (folderId) {
        window.location.href = 'folder_details.php?id=' + folderId;
    };

    window.showFileDetails = function (fileId) {
        window.location.href = 'file_details.php?id=' + fileId;
    };


    /* =========================
       DELETE FUNCTIONS
    ========================= */

    window.deleteFolder = function (id) {
        currentDeleteId = id;
        currentDeleteType = 'folder';
        currentAction = 'trash';

        const message = document.getElementById('deleteMessage');
        const modal = document.getElementById('deleteModal');

        if (message) {
            message.innerHTML = 'Are you sure you want to move this folder and all its contents to trash?';
        }

        if (modal) {
            modal.style.display = 'flex';
        }
    };

    window.deleteFile = function (id) {
        currentDeleteId = id;
        currentDeleteType = 'file';
        currentAction = 'trash';

        const message = document.getElementById('deleteMessage');
        const modal = document.getElementById('deleteModal');

        if (message) {
            message.innerHTML = 'Are you sure you want to move this file to trash?';
        }

        if (modal) {
            modal.style.display = 'flex';
        }
    };


    /* =========================
       PERMANENT DELETE FUNCTIONS
    ========================= */

    window.permanentlyDeleteFolder = function (id) {
        currentDeleteId = id;
        currentDeleteType = 'folder';
        currentAction = 'permanent';
        
        const modal = document.getElementById('permanentDeleteModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };

    window.permanentlyDeleteFile = function (id) {
        currentDeleteId = id;
        currentDeleteType = 'file';
        currentAction = 'permanent';
        
        const modal = document.getElementById('permanentDeleteModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };


    /* =========================
       CONFIRM DELETE BUTTONS
    ========================= */

    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (currentDeleteId && currentDeleteType) {
                window.location.href = `delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}&action=${currentAction}`;
            }
        });
    }

    const confirmPermanentDeleteBtn = document.getElementById('confirmPermanentDeleteBtn');
    if (confirmPermanentDeleteBtn) {
        confirmPermanentDeleteBtn.addEventListener('click', function () {
            if (currentDeleteId && currentDeleteType) {
                window.location.href = `delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}&action=permanent`;
            }
        });
    }

    const confirmEmptyTrashBtn = document.getElementById('confirmEmptyTrashBtn');
    if (confirmEmptyTrashBtn) {
        confirmEmptyTrashBtn.addEventListener('click', function () {
            window.location.href = 'empty_trash.php';
        });
    }


    /* =========================
       RESTORE FUNCTIONS
    ========================= */

    window.restoreFolder = function (id) {
        if (confirm('Restore this folder and all its contents?')) {
            window.location.href = `restore_item.php?type=folder&id=${id}`;
        }
    };

    window.restoreFile = function (id) {
        if (confirm('Restore this file?')) {
            window.location.href = `restore_item.php?type=file&id=${id}`;
        }
    };


    /* =========================
       EMPTY TRASH
    ========================= */

    window.emptyTrash = function () {
        const modal = document.getElementById('emptyTrashModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };


    /* =========================
       MOBILE SIDEBAR TOGGLE
    ========================= */

    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        const mobileToggle = document.createElement('div');
        mobileToggle.className = 'mobile-menu-toggle';
        mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(mobileToggle);

        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function (event) {
            const isClickInside = sidebar.contains(event.target) || mobileToggle.contains(event.target);
            if (!isClickInside && window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
            }
        });
    }


    /* =========================
       CLOSE MODALS WHEN CLICKING OUTSIDE
    ========================= */

    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };


    /* =========================
       CLOSE FAB MENU WHEN CLICKING OUTSIDE
    ========================= */

    document.addEventListener('click', function(event) {
        const fabMenu = document.getElementById('fabMenu');
        const fab = document.querySelector('.fab');
        
        if (fabMenu && fab && !fab.contains(event.target) && !fabMenu.contains(event.target)) {
            fabMenu.classList.remove('show');
        }
    });


    /* =========================
       INPUT VALIDATION FOR LOGIN
    ========================= */

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

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }

    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            if (loginForm && loginForm.checkValidity()) {
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

    // Remember me functionality
    const savedEmail = localStorage.getItem('savedEmail');
    const rememberCheckbox = document.getElementById('remember');
    
    if (savedEmail && emailInput) {
        emailInput.value = savedEmail;
        if (rememberCheckbox) rememberCheckbox.checked = true;
    }

    if (loginForm && rememberCheckbox) {
        loginForm.addEventListener('submit', function() {
            if (rememberCheckbox.checked && emailInput) {
                localStorage.setItem('savedEmail', emailInput.value);
            } else {
                localStorage.removeItem('savedEmail');
            }
        });
    }


    /* =========================
       HELPER FUNCTIONS
    ========================= */

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

    function validatePassword(input) {
        const password = input.value;
        
        removeError(input);
        
        if (password && password.length < 6) {
            showError(input, 'Password must be at least 6 characters');
            return false;
        }
        return true;
    }

    function validateForm() {
        let isValid = true;
        
        if (!emailInput.value.trim()) {
            showError(emailInput, 'Email is required');
            isValid = false;
        } else if (!validateEmail(emailInput)) {
            isValid = false;
        }
        
        if (!passwordInput.value) {
            showError(passwordInput, 'Password is required');
            isValid = false;
        } else if (!validatePassword(passwordInput)) {
            isValid = false;
        }
        
        return isValid;
    }

    function showError(input, message) {
        removeError(input);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'input-error';
        errorDiv.style.color = '#ce1126';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '5px';
        errorDiv.style.marginLeft = '2px';
        errorDiv.textContent = message;
        
        input.style.borderColor = '#ce1126';
        input.parentNode.insertBefore(errorDiv, input.nextSibling);
    }

    function removeError(input) {
        input.style.borderColor = '#e0e0e0';
        
        const nextElement = input.nextElementSibling;
        if (nextElement && nextElement.className === 'input-error') {
            nextElement.remove();
        }
    }

});