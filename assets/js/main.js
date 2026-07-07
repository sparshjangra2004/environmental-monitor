function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (alert) {
            alert.remove();
        });
    }, 4000);

    document.querySelectorAll('.alert-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.alert').remove();
        });
    });
});

function toggleNavbar() {
    document.querySelector('.navbar-nav-wrap').classList.toggle('open');
}

function toggleDropdown() {
    document.getElementById('userDropdownMenu').classList.toggle('open');
}

document.addEventListener('click', function (event) {
    const menu = document.getElementById('userDropdownMenu');
    if (!menu) return;
    const isClickInside = event.target.closest('.dropdown');
    if (!isClickInside) {
        menu.classList.remove('open');
    }
});

function openModal(modalId) {
    document.getElementById(modalId).classList.add('open');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
}
