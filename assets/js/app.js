/* SMART E-INVOICING — app.js */
(function () {
    'use strict';

    /* Sidebar (mobile) */
    var toggle = document.querySelector('.menu-toggle');
    var sidebar = document.querySelector('.sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', function (ev) {
            if (sidebar.classList.contains('open') &&
                !sidebar.contains(ev.target) && ev.target !== toggle) {
                sidebar.classList.remove('open');
            }
        });
    }

    /* Dropdowns */
    document.querySelectorAll('[data-dropdown]').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var menu = document.querySelector(btn.getAttribute('data-dropdown'));
            if (menu) {
                var wasOpen = menu.classList.contains('open');
                document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { m.classList.remove('open'); });
                if (!wasOpen) menu.classList.add('open');
            }
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { m.classList.remove('open'); });
    });

    /* Client-side table search */
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase();
            var table = document.querySelector(input.getAttribute('data-table-search'));
            if (!table) return;
            table.querySelectorAll('tbody tr').forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(q) > -1 ? '' : 'none';
            });
        });
    });

    /* Confirm modal */
    var backdrop = document.querySelector('.modal-backdrop');

    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            ev.preventDefault();
            var target = el.getAttribute('data-confirm'); /* url or form id */
            var msg = el.getAttribute('data-msg') || 'Are you sure you want to proceed?';
            showConfirm(msg, target);
        });
    });

    function showConfirm(msg, target) {
        if (!backdrop) {
            if (confirm(msg)) { window.location = target; }
            return;
        }
        backdrop.querySelector('.modal p').textContent = msg;
        backdrop.classList.add('open');
        var okBtn = backdrop.querySelector('[data-confirm-ok]');
        var old = okBtn.onclick;
        okBtn.onclick = function () {
            var form = document.getElementById(target);
            if (form && form instanceof HTMLFormElement) {
                form.submit();
            } else {
                window.location = target;
            }
        };
        backdrop.querySelector('[data-confirm-cancel]').onclick = function () {
            backdrop.classList.remove('open');
            okBtn.onclick = old;
        };
    }

    /* Toasts auto-dismiss */
    document.querySelectorAll('.toast').forEach(function (t) {
        setTimeout(function () {
            t.style.opacity = '0';
            t.style.transition = 'opacity .4s';
            setTimeout(function () { t.remove(); }, 450);
        }, 3800);
    });

    /* Modal close on backdrop click */
    if (backdrop) {
        backdrop.addEventListener('click', function (ev) {
            if (ev.target === backdrop) backdrop.classList.remove('open');
        });
    }
})();