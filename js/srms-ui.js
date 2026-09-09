/* Shared presentation enhancements. Forms, routes, table IDs and plugin data stay intact. */
(function () {
    'use strict';

    function enhanceTables(root) {
        root.querySelectorAll('table.table').forEach(function (table) {
            if (!table.closest('.table-responsive, .srms-table-scroll, .dataTables_scrollBody')) {
                var region = document.createElement('div');
                region.className = 'srms-table-scroll';
                table.parentNode.insertBefore(region, table);
                region.appendChild(table);
            }
            var scroll = table.closest('.table-responsive, .srms-table-scroll');
            if (scroll) {
                scroll.tabIndex = 0;
                scroll.setAttribute('role', 'region');
                var panel = table.closest('.panel');
                var heading = panel && panel.querySelector('.panel-heading');
                scroll.setAttribute('aria-label', ((heading && heading.textContent.trim()) || 'Records') + ' table, scroll horizontally for more columns');
            }
            var headers = Array.from(table.querySelectorAll('thead tr:first-child th'));
            headers.forEach(function (header) { header.setAttribute('scope', 'col'); });
            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.from(row.cells).forEach(function (cell, index) {
                    var label = headers[index] ? headers[index].textContent.trim().toLowerCase() : '';
                    if (/^(status|account status|delivery status|state)$/.test(label) && !cell.children.length) {
                        var state = cell.textContent.trim().toLowerCase().replace(/[_ ]+/g, '-');
                        if (/^(active|inactive|pending|approved|completed|cancelled|processing|failed|published|review|submitted|open|marks-entry|archived|draft|locked)$/.test(state)) {
                            var badge = document.createElement('span');
                            badge.className = 'status-badge status-' + state;
                            badge.textContent = cell.textContent;
                            cell.replaceChildren(badge);
                        }
                    }
                    if (/^(marks|score|total|count|average|mean|amount|balance|expected|recorded)$/.test(label) && /^[\d,.% /-]+$/.test(cell.textContent.trim())) {
                        cell.classList.add('srms-numeric');
                        headers[index].classList.add('srms-numeric');
                    }
                    if (/^actions?$/.test(label)) cell.classList.add('srms-actions-cell');
                });
            });
            table.querySelectorAll('a, button').forEach(function (control) {
                if (control.textContent.trim() || !control.querySelector('.fa')) return;
                var icon = control.querySelector('.fa');
                var label = control.getAttribute('title') || control.getAttribute('aria-label');
                if (!label) {
                    if (/pencil|edit/.test(icon.className)) label = 'Edit';
                    else if (/trash|remove|times/.test(icon.className)) label = 'Delete';
                    else if (/eye/.test(icon.className)) label = 'View';
                    else if (/check/.test(icon.className)) label = 'Activate';
                }
                if (label) {
                    control.setAttribute('title', label);
                    control.setAttribute('aria-label', label);
                    control.classList.add('srms-icon-action');
                    icon.setAttribute('aria-hidden', 'true');
                }
            });
        });
    }

    function init() {
        enhanceTables(document);
        if (window.jQuery) {
            window.jQuery(document).on('draw.dt', function () { enhanceTables(document); });
        }
        var sidebar = document.querySelector('.left-sidebar');
        if (!sidebar) return;
        var path = location.pathname.split('/').pop();
        var routeParents = {
            'edit-teacher.php': 'manage-teachers.php', 'view-teacher.php': 'manage-teachers.php',
            'edit-student.php': 'manage-students.php', 'edit-subject.php': 'manage-subjects.php',
            'edit-class.php': 'manage-classes.php', 'edit-result.php': 'manage-results.php'
        };
        sidebar.querySelectorAll('.side-nav a[href]').forEach(function (link) {
            var href = link.getAttribute('href');
            if (href === '#') {
                link.setAttribute('aria-expanded', link.parentElement.classList.contains('open'));
                return;
            }
            link.title = link.textContent.trim();
            if (href.split('?')[0] === (routeParents[path] || path)) {
                link.setAttribute('aria-current', 'page');
                link.parentElement.classList.add('active');
                var group = link.closest('.has-children');
                if (group) {
                    group.classList.add('open');
                    group.querySelector('a').setAttribute('aria-expanded', 'true');
                    if (window.jQuery) window.jQuery(group.querySelector('.child-nav')).stop(true, true).show();
                }
            }
        });

        var toggle = document.querySelector('.mobile-nav-toggle');
        function closeSidebar(returnFocus) {
            var wasOpen = document.body.classList.contains('srms-nav-open');
            document.body.classList.remove('srms-nav-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
                if (returnFocus && wasOpen) toggle.focus();
            }
        }
        var backdrop = document.querySelector('.srms-nav-backdrop');
        if (backdrop) backdrop.addEventListener('click', function () { closeSidebar(true); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeSidebar(true);
            if (event.key === 'Tab' && document.body.classList.contains('srms-nav-open')) {
                var links = Array.from(sidebar.querySelectorAll('a[href], button')).filter(function (el) { return el.getClientRects().length; });
                var first = links[0], last = links[links.length - 1];
                if (first && (!sidebar.contains(document.activeElement) || (!event.shiftKey && document.activeElement === last))) {
                    event.preventDefault(); first.focus();
                } else if (last && event.shiftKey && document.activeElement === first) {
                    event.preventDefault(); last.focus();
                }
            }
        });
        var mobile = window.matchMedia('(max-width: 991px)');
        mobile.addEventListener('change', function () {
            closeSidebar(false);
            sidebar.classList.remove('small-nav');
            document.querySelectorAll('.small-nav-handle').forEach(function (button) { button.setAttribute('aria-expanded', 'true'); });
        });
    }

    // Join the ready queue after each page's DataTables setup so only the table scrolls.
    if (window.jQuery) window.jQuery(init);
    else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
