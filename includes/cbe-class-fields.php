<?php
$fieldValue=static fn($key)=>is_scalar($classValues[$key]??null)?(string)$classValues[$key]:'';
?>
<div class="form-group has-success">
    <label for="classname" class="control-label">Class Name</label>
    <input type="text" name="classname" id="classname" class="form-control" maxlength="80" required value="<?= academic_h($fieldValue('ClassName')) ?>">
    <span class="help-block">The class name shown in student records and reports.</span>
</div>
<div class="form-group has-success">
    <label for="gradeid" class="control-label">Configured Grade</label>
    <select name="GradeId" id="gradeid" class="form-control" required>
        <option value="">Select an active grade</option>
        <?php foreach($grades as $grade) { ?>
        <option value="<?= (int)$grade['id'] ?>" <?= $fieldValue('GradeId')===(string)$grade['id']?'selected':'' ?> <?= (int)$grade['Status']!==1?'disabled':'' ?>><?= academic_h($grade['Name'].((int)$grade['Status']!==1?' (inactive)':'')) ?></option>
        <?php } ?>
    </select>
    <span class="help-block">The grade number follows this selection. Configure grades in <a href="dean-academics.php?area=structure">Academic Structure</a>.</span>
</div>
<div class="form-group has-success">
    <label for="section" class="control-label">Section</label>
    <input type="text" name="section" id="section" class="form-control" maxlength="5" required value="<?= academic_h($fieldValue('Section')) ?>">
    <span class="help-block">For example A, B or C (up to 5 characters).</span>
</div>
