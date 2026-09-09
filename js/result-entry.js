/* Keep subjects tied to the current learner and exam, even when requests finish out of order. */
var resultRequestVersion = 0;
var studentRequestVersion = 0;

function clearResultEntry() {
    resultRequestVersion++;
    $('#subject').empty();
    $('#reslt').text('Select a class, exam and learner to load registered subjects.');
    $('#submit').prop('disabled', true);
}

function resultLoadError(xhr) {
    return xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not load result entry. Please reload the page and sign in if needed.';
}

function getStudent(classId) {
    var version = ++studentRequestVersion;
    clearResultEntry();
    $('#studentid').empty().append($('<option>').val('').text('Select Student'));
    if (!classId) return;
    $.ajax({
        type: 'POST', url: 'get_student.php', dataType: 'json', data: {classid: classId},
        success: function(data) {
            if (version !== studentRequestVersion) return;
            $.each(data.students, function(_, student) {
                $('#studentid').append($('<option>').val(student.StudentId).text(student.StudentName));
            });
        },
        error: function(xhr) {
            if (version === studentRequestVersion) $('#reslt').text(resultLoadError(xhr));
        }
    });
}

function getresult() {
    clearResultEntry();
    var version = resultRequestVersion;
    var selection = {class: $('#classid').val(), studentid: $('#studentid').val(), examid: $('#examid').val()};
    if (!selection.class || !selection.studentid || !selection.examid) return;
    $('#reslt').text('Loading registered subjects...');
    $.ajax({
        type: 'POST', url: 'get_student.php', dataType: 'json', data: selection,
        success: function(data) {
            if (version !== resultRequestVersion) return;
            $('#subject').html(data.fields);
            $('#reslt').text(data.message);
            $('#submit').prop('disabled', !data.canSubmit);
        },
        error: function(xhr) {
            if (version === resultRequestVersion) $('#reslt').text(resultLoadError(xhr));
        }
    });
}
