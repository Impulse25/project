<div class="form-group">
    <label class="form-label">Семестр</label>

    <select name="semester" class="form-control" onchange="this.form.submit()">
        <option value="0" <?= $filterSem === 0 ? 'selected' : '' ?>>Все семестры</option>
        <?php for ($s = 1; $s <= 8; $s++): ?>
          <option value="<?= $s ?>" <?= $s === $filterSem ? 'selected' : '' ?>>
            <?= $s ?> семестр
          </option>
        <?php endfor ?>
    </select>
</div>