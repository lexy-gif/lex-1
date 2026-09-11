(function ($) {
    'use strict';
    $(function () {
        var tables = [];
        if ($.fn.DataTable) $('.senior-table').each(function () {
            var selectable = !!this.querySelector('.senior-select-all');
            var options = {pageLength: 25, order: [[selectable ? 2 : 0, 'asc']]};
            if (selectable) options.columnDefs = [{targets: 0, orderable: false, searchable: false}];
            tables.push($(this).DataTable(options));
        });
        $('.senior-select-all').on('change', function () {
            var formId = this.dataset.form, checked = this.checked;
            $(this).closest('table').find('tbody input[type="checkbox"]').each(function () {
                if (this.getAttribute('form') === formId && !this.disabled) this.checked = checked;
            });
        });
        $('.senior-form').on('submit', function () {
            var form = this;
            form.querySelectorAll('[data-selection-copy]').forEach(function (input) { input.remove(); });
            tables.forEach(function (table) {
                table.rows().nodes().toArray().forEach(function (row) {
                    row.querySelectorAll('input[name="Students[]"]:checked').forEach(function (input) {
                        if (input.getAttribute('form') !== form.id || input.isConnected) return;
                        [input, row.querySelector('input[type="hidden"]')].forEach(function (source) {
                            if (!source) return;
                            var copy = document.createElement('input');
                            copy.type = 'hidden'; copy.name = source.name; copy.value = source.value;
                            copy.setAttribute('data-selection-copy', '1'); form.appendChild(copy);
                        });
                    });
                });
            });
        });
        var pendingForm = null;
        $('form[data-confirm]').on('submit', function (event) {
            event.preventDefault(); pendingForm = this;
            $('#senior-confirm .senior-confirm-message').text(this.dataset.confirm);
            $('#senior-confirm').modal('show');
        });
        $('.senior-confirm-accept').on('click', function () { if (pendingForm) pendingForm.submit(); });
        $('.senior-editor[data-open="1"]').first().modal('show');
        if (new URLSearchParams(location.search).has('mapping')) $('#senior-mapping').modal('show');

        document.querySelectorAll('form').forEach(function (form) {
            var action = form.elements.namedItem('action');
            if (!action || !['assign', 'combination', 'mapping', 'teacher', 'lesson', 'exam_session', 'track'].includes(action.value)) return;
            var mode = action.value, generation = 0;
            var field = function (name) { return form.elements.namedItem(name); };
            var value = function (name) { var input = field(name); return input ? input.value : ''; };
            var note = document.createElement('p'); note.className = 'help-block'; note.setAttribute('aria-live', 'polite');
            form.appendChild(note);
            function options(name, rows, selected, autoSelect) {
                var input = field(name); if (!input) return;
                var chosen = (selected || Array.from(input.selectedOptions).map(function (o) { return o.value; })).map(String);
                input.replaceChildren();
                if (!input.multiple) input.add(new Option(input.required ? 'Select' : 'School default / None', ''));
                rows.forEach(function (row) { input.add(new Option(row.Label, row.id, false, chosen.includes(String(row.id)))); });
                if (autoSelect && rows.length === 1 && !input.value) input.value = String(rows[0].id);
            }
            function refresh(changed) {
                var current = ++generation;
                if (changed === 'AcademicYearId' && field('TermId')) field('TermId').value = '';
                if (changed === 'GradeId' && field('ClassId')) field('ClassId').value = '';
                var submit = form.querySelector('button[type="submit"], button:not([type])');
                if (submit) submit.disabled = true;
                var query = new URLSearchParams({mode: mode, year: value('AcademicYearId') || new URLSearchParams(location.search).get('year') || '',
                    term: value('TermId'), grade: value('GradeId'), class: value('ClassId'), pathway: value('PathwayId'), track: value('TrackId'), subject: value('SubjectId'), exam: value('ExamId')});
                if (!query.get('year')) query.delete('year');
                fetch('dean-senior-options.php?' + query.toString(), {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                    .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.error || 'Unable to load choices.'); return data; }); })
                    .then(function (data) {
                        if (current !== generation) return;
                        options('ClassId', data.classes, changed === 'GradeId' ? [] : null);
                        var previousTerm = value('TermId');
                        options('TermId', data.terms, null, mode === 'lesson');
                        if (mode === 'lesson' && value('TermId') !== previousTerm) { refresh('TermId'); return; }
                        options('TrackId', data.tracks, changed === 'PathwayId' ? [] : null);
                        options('CombinationId', data.combinations, ['PathwayId', 'TrackId', 'AcademicYearId'].includes(changed) ? [] : null);
                        options('Subjects[]', data.subjects, mode === 'mapping' && changed ? data.mapped : null);
                        options('SubjectId', data.subjects, ['ClassId', 'PathwayId', 'ExamId'].includes(changed) ? [] : null);
                        if (mode === 'lesson') options('TeacherId', data.teachers, ['ClassId', 'SubjectId', 'AcademicYearId', 'TermId'].includes(changed) ? [] : null, true);
                        var combination = field('CombinationId'), subjects = field('Subjects[]');
                        if (subjects && mode === 'assign') subjects.disabled = !!(combination && combination.value);
                        note.textContent = ['assign', 'combination'].includes(mode) ? 'This pathway requires ' + data.elective_count + ' electives. Core subjects are added automatically.' : '';
                        if (submit) submit.disabled = false;
                    }).catch(function (error) {
                        if (current !== generation) return;
                        note.textContent = error.message;
                        if (submit) submit.disabled = true;
                    });
            }
            ['AcademicYearId', 'TermId', 'GradeId', 'ClassId', 'PathwayId', 'TrackId', 'SubjectId', 'ExamId'].forEach(function (name) {
                var input = field(name); if (input) input.addEventListener('change', function () { refresh(name); });
            });
            var combination = field('CombinationId');
            if (combination) combination.addEventListener('change', function () { if (field('Subjects[]')) field('Subjects[]').disabled = !!this.value; });
            refresh('');
        });
    });
})(jQuery);
