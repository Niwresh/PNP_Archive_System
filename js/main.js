document.addEventListener('DOMContentLoaded', function() {
'use strict';

/* =========================
   EXISTING LOGIN VARIABLES
========================= */

const loginForm = document.querySelector('form');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const loginBtn = document.querySelector('.login-btn');


/* ======================================================
   NEW VARIABLES FOR DRIVE / FILE SYSTEM FUNCTIONALITY
====================================================== */

let currentDeleteId = null;
let currentDeleteType = null;
let currentAction = null;


/* =========================
   FAB MENU
========================= */

window.toggleFabMenu = function() {
    document.getElementById('fabMenu')?.classList.toggle('show');
}


/* =========================
   MODALS
========================= */

window.openFolderModal = function() {
    document.getElementById('folderModal').style.display = 'flex';
}

window.openUploadModal = function() {
    document.getElementById('uploadModal').style.display = 'flex';
    setTimeout(toggleMonthField, 100);
}

window.closeModal = function(modalId) {
    document.getElementById(modalId).style.display = 'none';
}


/* =========================
   FILE NAME UPDATE
========================= */

window.updateFileName = function() {

    const fileInput = document.getElementById('fileInput');
    const customFilename = document.getElementById('customFilename');

    if (fileInput && fileInput.files.length > 0) {

        const fullName = fileInput.files[0].name;
        const nameWithoutExt = fullName.substring(0, fullName.lastIndexOf('.')) || fullName;

        if (!customFilename.value) {
            customFilename.value = nameWithoutExt;
        }

    }

}


/* =========================
   MONTH / YEAR LOGIC
========================= */

window.toggleMonthField = function() {

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

            yearField.style.display = 'none';
            yearInput.removeAttribute('name');
            monthField.style.display = 'block';

        } else {

            yearField.style.display = 'block';
            yearInput.setAttribute('name','year');
            monthField.style.display = 'block';

        }

    } else {

        yearField.style.display = 'block';
        yearInput.setAttribute('name','year');
        monthField.style.display = 'block';

    }

}


/* =========================
   OPEN DETAILS
========================= */

window.showFolderDetails = function(folderId){
    window.location.href = 'folder_details.php?id=' + folderId;
}

window.showFileDetails = function(fileId){
    window.location.href = 'file_details.php?id=' + fileId;
}


/* =========================
   DELETE FUNCTIONS
========================= */

window.deleteFolder = function(id){

    currentDeleteId = id;
    currentDeleteType = 'folder';
    currentAction = 'trash';

    document.getElementById('deleteMessage').innerHTML =
    'Are you sure you want to move this folder and all its contents to trash?';

    document.getElementById('deleteModal').style.display = 'flex';

}

window.deleteFile = function(id){

    currentDeleteId = id;
    currentDeleteType = 'file';
    currentAction = 'trash';

    document.getElementById('deleteMessage').innerHTML =
    'Are you sure you want to move this file to trash?';

    document.getElementById('deleteModal').style.display = 'flex';

}


/* =========================
   CONFIRM DELETE BUTTON
========================= */

const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

if(confirmDeleteBtn){

confirmDeleteBtn.addEventListener('click',function(){

if(currentDeleteId && currentDeleteType){

window.location.href =
`delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}&action=${currentAction}`;

}

});

}


/* =========================
   RESTORE
========================= */

window.restoreFolder = function(id){

if(confirm('Restore this folder and all its contents?')){

window.location.href = `restore_item.php?type=folder&id=${id}`;

}

}

window.restoreFile = function(id){

if(confirm('Restore this file?')){

window.location.href = `restore_item.php?type=file&id=${id}`;

}

}


/* =========================
   MOBILE SIDEBAR TOGGLE
========================= */

const mobileToggle = document.createElement('div');

mobileToggle.className = 'mobile-menu-toggle';
mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';

document.body.appendChild(mobileToggle);

mobileToggle.addEventListener('click',function(){

document.querySelector('.sidebar').classList.toggle('active');

});


/* =========================
   CLICK OUTSIDE SIDEBAR
========================= */

document.addEventListener('click',function(event){

const sidebar = document.querySelector('.sidebar');

const isClickInside =
sidebar.contains(event.target) ||
mobileToggle.contains(event.target);

if(!isClickInside && window.innerWidth <= 768){

sidebar.classList.remove('active');

}

});


/* =========================
   CLOSE MODALS
========================= */

window.onclick = function(event){

if(event.target.classList.contains('modal')){

event.target.style.display = 'none';

}

}

});