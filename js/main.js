document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    /* =========================
       DRIVE VARIABLES
    ========================= */
    let currentDeleteId = null;
    let currentDeleteType = null;

    let currentRenameId = null;
    let currentRenameType = null;
    let currentRenameName = null;

    /* =========================
       MOBILE SIDEBAR
    ========================= */
    const sidebar = document.querySelector('.sidebar');

    function initMobileSidebar() {
        if (!sidebar) return;

        const existingToggle = document.querySelector('.mobile-menu-toggle');
        if (existingToggle) existingToggle.remove();

        const toggle = document.createElement('div');
        toggle.className = 'mobile-menu-toggle';
        toggle.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(toggle);

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            sidebar.classList.toggle('active');
            const icon = this.querySelector('i');
            icon.className = sidebar.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
        });

        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    const icon = toggle.querySelector('i');
                    if (icon) icon.className = 'fas fa-bars';
                }
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                const icon = toggle.querySelector('i');
                if (icon) icon.className = 'fas fa-bars';
            }
        });
    }
    initMobileSidebar();

    /* =========================
       FAB MENU
    ========================= */
    window.toggleFabMenu = function() {
        const fabMenu = document.getElementById('fabMenu');
        if (fabMenu) fabMenu.classList.toggle('show');
    };

    document.addEventListener('click', function(event) {
        const fab = document.querySelector('.fab');
        const fabMenu = document.getElementById('fabMenu');
        if (fabMenu && fab && !fab.contains(event.target) && !fabMenu.contains(event.target)) {
            fabMenu.classList.remove('show');
        }
    });

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
            document.body.style.overflow = 'hidden';
        }
    };

    window.openUploadModal = function() {
        const modal = document.getElementById('uploadModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal[style*="flex"]');
            modals.forEach(function(modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }
    });

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    /* =========================
       DELETE FUNCTIONS
    ========================= */
    window.deleteFolder = function(id) {
        currentDeleteId = id;
        currentDeleteType = 'folder';
        const modal = document.getElementById('deleteModal');
        const message = document.getElementById('deleteMessage');
        if (modal && message) {
            message.textContent = 'Are you sure you want to move this folder and all its contents to trash?';
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.deleteFile = function(id) {
        currentDeleteId = id;
        currentDeleteType = 'file';
        const modal = document.getElementById('deleteModal');
        const message = document.getElementById('deleteMessage');
        if (modal && message) {
            message.textContent = 'Are you sure you want to move this file to trash?';
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentDeleteId && currentDeleteType) {
                window.location.href = `Delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}`;
            }
        });
    }

    /* =========================
       TRASH FUNCTIONS
    ========================= */
    window.restoreFolder = function(id) {
        if (confirm('Restore this folder?')) {
            window.location.href = `restore_item.php?type=folder&id=${id}`;
        }
    };

    window.restoreFile = function(id) {
        if (confirm('Restore this file?')) {
            window.location.href = `restore_item.php?type=file&id=${id}`;
        }
    };

    window.permanentlyDeleteFolder = function(id) {
        currentDeleteId = id;
        currentDeleteType = 'folder';
        const modal = document.getElementById('permanentDeleteModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.permanentlyDeleteFile = function(id) {
        currentDeleteId = id;
        currentDeleteType = 'file';
        const modal = document.getElementById('permanentDeleteModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.emptyTrash = function() {
        const modal = document.getElementById('emptyTrashModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    const confirmPermanentDeleteBtn = document.getElementById('confirmPermanentDeleteBtn');
    if (confirmPermanentDeleteBtn) {
        confirmPermanentDeleteBtn.addEventListener('click', function() {
            if (currentDeleteId && currentDeleteType) {
                window.location.href = `Permanent_delete.php?type=${currentDeleteType}&id=${currentDeleteId}`;
            }
        });
    }

    const confirmEmptyTrashBtn = document.getElementById('confirmEmptyTrashBtn');
    if (confirmEmptyTrashBtn) {
        confirmEmptyTrashBtn.addEventListener('click', function() {
            window.location.href = 'empty_trash.php';
        });
    }

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
                window.location.href = `Rename_Folder.php?id=${currentRenameId}&name=${encodeURIComponent(newName)}`;
            }

            if (currentRenameType === "file") {
                const lastDot = currentRenameName.lastIndexOf('.');
                const ext = lastDot > 0 ? currentRenameName.substring(lastDot) : "";
                const finalName = newName + ext;
                window.location.href = `rename_file.php?id=${currentRenameId}&name=${encodeURIComponent(finalName)}`;
            }
        });
    }

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
       LOGIN VALIDATION
    ========================= */
    const loginForm = document.querySelector('form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

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
        if (emailInput && !validateEmail(emailInput)) valid = false;
        if (passwordInput && !validatePassword(passwordInput)) valid = false;
        return valid;
    }

    if (loginForm) {
        loginForm.addEventListener("submit", function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });

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
       TOUCH OPTIMIZATION
    ========================= */
    if ('ontouchstart' in window) {
        document.querySelectorAll('button,.action-btn,.filter-btn,.quick-action-btn,.fab,.fab-menu-item').forEach(el => {
            el.addEventListener('touchstart', function() {
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
       INITIALIZATION
    ========================= */
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.style.minHeight = window.innerHeight + 'px';
    }

    window.addEventListener('orientationchange', function() {
        setTimeout(function() {
            if (mainContent) {
                mainContent.style.minHeight = window.innerHeight + 'px';
            }
        }, 200);
    });

    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
        document.querySelectorAll('input,select,textarea').forEach(el => {
            el.addEventListener('focus', function() {
                this.style.fontSize = '16px';
            });
        });
    }

    console.log('PNP Archive: Mobile responsive initialized');
});