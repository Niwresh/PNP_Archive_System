document.addEventListener('DOMContentLoaded', function() {
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
       MOBILE SIDEBAR
    ========================= */
    const sidebar = document.querySelector('.sidebar');
    
    function initMobileSidebar() {
        if (!sidebar) return;
        
        // Remove existing toggle if any
        const existingToggle = document.querySelector('.mobile-menu-toggle');
        if (existingToggle) existingToggle.remove();
        
        // Create new toggle button
        const toggle = document.createElement('div');
        toggle.className = 'mobile-menu-toggle';
        toggle.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(toggle);
        
        // Toggle sidebar on click
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            sidebar.classList.toggle('active');
            
            // Change icon based on state
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    const icon = toggle.querySelector('i');
                    if (icon) icon.className = 'fas fa-bars';
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                const icon = toggle.querySelector('i');
                if (icon) icon.className = 'fas fa-bars';
            }
        });
        
        // Handle touch events for mobile
        if ('ontouchstart' in window) {
            let touchStartX = 0;
            let touchEndX = 0;
            
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, false);
            
            document.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, false);
            
            function handleSwipe() {
                const swipeThreshold = 100;
                if (touchEndX < touchStartX - swipeThreshold && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    const icon = toggle.querySelector('i');
                    if (icon) icon.className = 'fas fa-bars';
                } else if (touchEndX > touchStartX + swipeThreshold && !sidebar.classList.contains('active') && window.innerWidth <= 768) {
                    sidebar.classList.add('active');
                    const icon = toggle.querySelector('i');
                    if (icon) icon.className = 'fas fa-times';
                }
            }
        }
    }
    
    initMobileSidebar();

    /* =========================
       FAB MENU
    ========================= */
    window.toggleFabMenu = function() {
        const fabMenu = document.getElementById('fabMenu');
        if (fabMenu) {
            fabMenu.classList.toggle('show');
        }
    };

    // Close FAB menu when clicking outside
    document.addEventListener('click', function(event) {
        const fab = document.querySelector('.fab');
        const fabMenu = document.getElementById('fabMenu');
        
        if (fabMenu && fab && !fab.contains(event.target) && !fabMenu.contains(event.target)) {
            fabMenu.classList.remove('show');
        }
    });

    // Close FAB menu on scroll
    window.addEventListener('scroll', function() {
        const fabMenu = document.getElementById('fabMenu');
        if (fabMenu && fabMenu.classList.contains('show')) {
            fabMenu.classList.remove('show');
        }
    });

    /* =========================
       MODALS
    ========================= */
    window.openFolderModal = function() {
        const modal = document.getElementById('folderModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
    };

    window.openUploadModal = function() {
        const modal = document.getElementById('uploadModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            setTimeout(function() {
                if (window.toggleMonthField) toggleMonthField();
            }, 100);
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }
    };

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal[style*="flex"]');
            modals.forEach(modal => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    /* =========================
       RENAME FUNCTIONS
    ========================= */
    window.renameFolder = function(id, name) {
        currentRenameId = id;
        currentRenameType = 'folder';
        currentRenameName = name;

        const modal = document.getElementById('renameModal');
        const title = document.getElementById('renameModalTitle');
        const input = document.getElementById('renameInput');
        const display = document.getElementById('currentNameDisplay');
        const extDisplay = document.getElementById('fileExtensionDisplay');

        if (modal) {
            title.textContent = "Rename Folder";
            input.value = name;
            display.textContent = name;
            if (extDisplay) extDisplay.style.display = "none";
            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
            
            // Auto-select text in input
            setTimeout(() => {
                input.focus();
                input.select();
            }, 200);
        }
    };

    window.renameFile = function(id, name) {
        currentRenameId = id;
        currentRenameType = 'file';
        currentRenameName = name;

        const lastDot = name.lastIndexOf('.');
        const filename = lastDot > 0 ? name.substring(0, lastDot) : name;
        const extension = lastDot > 0 ? name.substring(lastDot) : '';

        const modal = document.getElementById('renameModal');
        const title = document.getElementById('renameModalTitle');
        const input = document.getElementById('renameInput');
        const display = document.getElementById('currentNameDisplay');
        const extDisplay = document.getElementById('fileExtensionDisplay');

        if (modal) {
            title.textContent = "Rename File";
            input.value = filename;
            display.textContent = name;

            if (extension) {
                extDisplay.textContent = "Extension: " + extension;
                extDisplay.style.display = "block";
            } else {
                extDisplay.style.display = "none";
            }

            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
            
            // Auto-select text in input
            setTimeout(() => {
                input.focus();
                input.select();
            }, 200);
        }
    };

    const confirmRenameBtn = document.getElementById("confirmRenameBtn");
    if (confirmRenameBtn) {
        confirmRenameBtn.addEventListener("click", function() {
            const input = document.getElementById("renameInput");
            const newName = input.value.trim();

            if (!newName) {
                alert("Please enter a name");
                return;
            }

            if (currentRenameType === "folder") {
                window.location.href = `rename_folder.php?id=${currentRenameId}&name=${encodeURIComponent(newName)}`;
            }

            if (currentRenameType === "file") {
                const lastDot = currentRenameName.lastIndexOf('.');
                const ext = lastDot > 0 ? currentRenameName.substring(lastDot) : "";
                const finalName = newName + ext;
                window.location.href = `rename_file.php?id=${currentRenameId}&name=${encodeURIComponent(finalName)}`;
            }
        });
    }

    // Allow Enter key in rename input
    const renameInput = document.getElementById('renameInput');
    if (renameInput) {
        renameInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmRenameBtn.click();
            }
        });
    }

    /* =========================
       FILE NAME AUTO UPDATE
    ========================= */
    window.updateFileName = function() {
        const fileInput = document.getElementById("fileInput");
        const customName = document.getElementById("customFilename");

        if (fileInput && fileInput.files.length > 0) {
            const full = fileInput.files[0].name;
            const name = full.substring(0, full.lastIndexOf(".")) || full;

            if (customName && !customName.value) {
                customName.value = name;
            }
        }
    };

    /* =========================
       MONTH / YEAR FIELD
    ========================= */
    window.toggleMonthField = function() {
        const folder = document.getElementById("folderSelect");
        const month = document.getElementById("monthField");
        const year = document.getElementById("yearField");
        const yearInput = document.getElementById("yearInput");

        if (!folder) {
            if (month) month.style.display = "block";
            if (year) year.style.display = "none";
            return;
        }

        if (folder.value) {
            const option = folder.options[folder.selectedIndex];
            const hasYear = option.getAttribute("data-has-year") === "1";

            if (hasYear) {
                year.style.display = "none";
                yearInput.removeAttribute("name");
            } else {
                year.style.display = "block";
                yearInput.setAttribute("name", "year");
            }
        }
    };

    /* =========================
       DELETE FUNCTIONS
    ========================= */
    window.deleteFolder = function(id) {
        currentDeleteId = id;
        currentDeleteType = "folder";
        currentAction = "trash";

        const modal = document.getElementById("deleteModal");
        const message = document.getElementById("deleteMessage");
        if (modal) {
            if (message) message.textContent = "Are you sure you want to move this folder to trash?";
            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
        }
    };

    window.deleteFile = function(id) {
        currentDeleteId = id;
        currentDeleteType = "file";
        currentAction = "trash";

        const modal = document.getElementById("deleteModal");
        const message = document.getElementById("deleteMessage");
        if (modal) {
            if (message) message.textContent = "Are you sure you want to move this file to trash?";
            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
        }
    };

    /* =========================
       CONFIRM DELETE
    ========================= */
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function() {
            if (currentDeleteId) {
                window.location.href = `delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}&action=${currentAction}`;
            }
        });
    }

    /* =========================
       PERMANENT DELETE FUNCTIONS
    ========================= */
    window.permanentlyDeleteFolder = function(id) {
        currentDeleteId = id;
        currentDeleteType = "folder";
        currentAction = "permanent";

        const modal = document.getElementById("permanentDeleteModal");
        if (modal) {
            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
        }
    };

    window.permanentlyDeleteFile = function(id) {
        currentDeleteId = id;
        currentDeleteType = "file";
        currentAction = "permanent";

        const modal = document.getElementById("permanentDeleteModal");
        if (modal) {
            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
        }
    };

    const confirmPermanentDeleteBtn = document.getElementById("confirmPermanentDeleteBtn");
    if (confirmPermanentDeleteBtn) {
        confirmPermanentDeleteBtn.addEventListener("click", function() {
            if (currentDeleteId) {
                window.location.href = `delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}&action=${currentAction}`;
            }
        });
    }

    /* =========================
       RESTORE FUNCTIONS
    ========================= */
    window.restoreFolder = function(id) {
        if (confirm("Restore this folder?")) {
            window.location.href = `restore_item.php?type=folder&id=${id}`;
        }
    };

    window.restoreFile = function(id) {
        if (confirm("Restore this file?")) {
            window.location.href = `restore_item.php?type=file&id=${id}`;
        }
    };

    /* =========================
       EMPTY TRASH
    ========================= */
    window.emptyTrash = function() {
        const modal = document.getElementById("emptyTrashModal");
        if (modal) {
            modal.style.display = "flex";
            document.body.style.overflow = 'hidden';
        }
    };

    const confirmEmptyTrashBtn = document.getElementById("confirmEmptyTrashBtn");
    if (confirmEmptyTrashBtn) {
        confirmEmptyTrashBtn.addEventListener("click", function() {
            window.location.href = "empty_trash.php";
        });
    }

    /* =========================
       DETAILS FUNCTIONS
    ========================= */
    window.showFolderDetails = function(id) {
        // Implement folder details functionality
        alert("Folder details feature coming soon!");
    };

    window.showFileDetails = function(id) {
        // Implement file details functionality
        alert("File details feature coming soon!");
    };

    /* =========================
       LOGIN VALIDATION
    ========================= */
    function validateEmail(input) {
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!pattern.test(input.value.trim())) {
            showError(input, "Invalid email");
            return false;
        }
        removeError(input);
        return true;
    }

    function validatePassword(input) {
        if (input.value.length < 6) {
            showError(input, "Minimum 6 characters");
            return false;
        }
        removeError(input);
        return true;
    }

    function validateForm() {
        let valid = true;
        if (!validateEmail(emailInput)) valid = false;
        if (!validatePassword(passwordInput)) valid = false;
        return valid;
    }

    if (loginForm) {
        loginForm.addEventListener("submit", function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });

        // Real-time validation
        if (emailInput) {
            emailInput.addEventListener("blur", function() {
                validateEmail(this);
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener("blur", function() {
                validatePassword(this);
            });
        }
    }

    /* =========================
       ERROR DISPLAY
    ========================= */
    function showError(input, message) {
        removeError(input);
        const div = document.createElement("div");
        div.className = "input-error";
        div.style.color = "#ce1126";
        div.style.fontSize = "12px";
        div.style.marginTop = "5px";
        div.textContent = message;
        input.parentNode.appendChild(div);
        input.style.borderColor = "#ce1126";
    }

    function removeError(input) {
        input.style.borderColor = "#e0e0e0";
        const err = input.parentNode.querySelector(".input-error");
        if (err) err.remove();
    }

    /* =========================
       TOUCH DEVICE OPTIMIZATIONS
    ========================= */
    if ('ontouchstart' in window) {
        document.querySelectorAll('button, .action-btn, .filter-btn, .quick-action-btn, .fab, .fab-menu-item').forEach(el => {
            el.addEventListener('touchstart', function() {
                // Prevent double-tap zoom on buttons
                this.style.transform = 'scale(0.95)';
            });
            
            el.addEventListener('touchend', function() {
                this.style.transform = '';
            });
            
            el.addEventListener('touchcancel', function() {
                this.style.transform = '';
            });
        });
    }

    /* =========================
       INITIALIZE ON PAGE LOAD
    ========================= */
    // Set minimum height for main content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.style.minHeight = window.innerHeight + 'px';
    }

    // Handle orientation change
    window.addEventListener('orientationchange', function() {
        setTimeout(function() {
            if (mainContent) {
                mainContent.style.minHeight = window.innerHeight + 'px';
            }
        }, 200);
    });

    // Prevent zoom on input focus for iOS
    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
        document.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('focus', function() {
                this.style.fontSize = '16px';
            });
        });
    }

    console.log('PNP Archive: Mobile responsive initialized');
});