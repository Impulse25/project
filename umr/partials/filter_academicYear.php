<div class="form-group">
    <label class="form-label">Учебный год</label>

    <select name="academic_year" class="form-control" onchange="this.form.submit()">
        <?php foreach ($allAcademicYears as $yr): ?>
            <option value="<?= $yr ?>" <?= $yr == $filterYear ? 'selected' : '' ?>>
                <?= $yr ?>/<?= $yr + 1 ?>
            </option>
        <?php endforeach ?>
    </select>
</div>