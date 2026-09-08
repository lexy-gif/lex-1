document.addEventListener('DOMContentLoaded', function () {
    function searchable(root) {
        root.querySelectorAll('select.academic-search').forEach(function (select) {
            if (select.dataset.searchReady) return;
            select.dataset.searchReady = '1';
            var input = document.createElement('input');
            input.type = 'search'; input.className = 'form-control academic-search-input';
            input.placeholder = 'Search options'; input.setAttribute('aria-label', 'Search ' + select.name);
            select.before(input);
            input.addEventListener('input', function () {
                var term = input.value.toLowerCase();
                Array.from(select.options).forEach(function (option) { option.hidden = !!option.value && !option.text.toLowerCase().includes(term); });
            });
        });
    }
    var rows = document.getElementById('academic-assignment-rows');
    if (rows) {
        var offerings = JSON.parse(document.getElementById('academic-offerings').textContent);
        function filterSubjects(row) {
            var classId = Number(row.querySelector('[name="assignment_class[]"]').value);
            var select = row.querySelector('[name="assignment_subject[]"]');
            Array.from(select.options).forEach(function (option) {
                option.disabled = !!option.value && !offerings.some(function (o) { return Number(o.ClassId) === classId && Number(o.SubjectId) === Number(option.value); });
                option.hidden = option.disabled;
                if (option.disabled && option.selected) select.value = '';
            });
        }
        var template = rows.firstElementChild.cloneNode(true);
        document.getElementById('academic-add').addEventListener('click', function () {
            var row = template.cloneNode(true); rows.appendChild(row); searchable(row); filterSubjects(row);
        });
        rows.addEventListener('change', function (event) { if (event.target.name === 'assignment_class[]') filterSubjects(event.target.closest('.academic-assignment-row')); });
        filterSubjects(rows.firstElementChild);
        rows.addEventListener('click', function (event) {
            if (event.target.classList.contains('academic-remove')) event.target.closest('.academic-assignment-row').remove();
        });
    }
    searchable(document);
    var incumbentData = document.getElementById('academic-incumbents');
    if (incumbentData) {
        var form = incumbentData.closest('form');
        var approved = false;
        form.addEventListener('submit', function (event) {
            if (approved) return;
            var data = new FormData(form), year = Number(data.get('academic_year')), term = Number(data.get('academic_term'));
            var classes = data.getAll('assignment_class[]'), subjects = data.getAll('assignment_subject[]');
            var conflicts = JSON.parse(incumbentData.textContent).filter(function (a) {
                if (a.year !== year) return false;
                if (a.kind === 'class') return data.has('class_role') && a.class === Number(data.get('academic_class'));
                return data.has('subject_role') && (!a.term || !term || a.term === term) && classes.some(function (c, i) { return Number(c) === a.class && Number(subjects[i]) === a.subject; });
            });
            if (!conflicts.length) return;
            event.preventDefault();
            var dialog = document.createElement('div');
            dialog.className = 'modal fade'; dialog.setAttribute('role', 'dialog');
            dialog.innerHTML = '<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4>Confirm reassignment</h4></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning academic-approve">Replace assignments and create teacher</button></div></div></div>';
            conflicts.forEach(function (a) { var p = document.createElement('p'); p.textContent = a.message; dialog.querySelector('.modal-body').appendChild(p); });
            document.body.appendChild(dialog);
            dialog.querySelector('.academic-approve').addEventListener('click', function () {
                conflicts.forEach(function (a) { var input = document.createElement('input'); input.type = 'hidden'; input.name = 'confirmed_assignments[]'; input.value = a.kind + ':' + a.id; form.appendChild(input); });
                approved = true; form.requestSubmit(form.querySelector('button[name="submit"]'));
            });
            jQuery(dialog).on('hidden.bs.modal', function () { dialog.remove(); }).modal('show');
        });
    }
    var modal = document.getElementById('academic-confirmation');
    if (modal && window.jQuery) jQuery(modal).modal({backdrop:'static', keyboard:false});
});
