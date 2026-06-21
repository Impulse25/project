<div class="form-group">
    <label class="form-label">Группа</label>

    <select name="group_id" class="form-control" onchange="this.form.submit()">
        <option value="0">Все группы</option>
        <?php foreach ($dropdownGroups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= (int)$g['id'] === $filterGroupId ? 'selected' : '' ?>>
            <?= e($g['name']) ?>
          </option>
        <?php endforeach ?>
    </select>
</div>