<?php if (!empty($allModuleIds)): ?>

    <div class="subject-filter-wrap">
        
        <div class="subject-filter-head" onclick="toggleSfBody()">
          
          <span class="subject-filter-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Отображаемые дисциплины
            <span id="sfCount" style="font-size:.72rem;color:var(--color-text-muted);font-weight:400"></span>
          </span>

          <div class="subject-filter-actions" onclick="event.stopPropagation()">
            <button class="sf-btn" onclick="sfSelectAll()">Все</button>
            <button class="sf-btn" onclick="sfSelectNone()">Ни одной</button>
            <svg class="sf-chevron" id="sfChevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </div>

        </div>

        <div class="subject-filter-body" id="sfBody">
          
            <?php foreach ($allModuleIds as $idx => $sm): ?>
                
                <?php $key = e($sm['index_code'] . '||' . $sm['name']); ?>

                <label class="sf-chip checked" id="sfchip-<?= $idx ?>" data-key="<?= $key ?>">
                    <input type="checkbox" checked onchange="sfToggle(this,'<?= $key ?>')">
                    <span class="chip-dot"></span>
                    <span class="badge-type t-<?= e($sm['module_type']) ?>" style="font-size:.65rem"><?= e($sm['module_type']) ?></span>
                    <?php if ($sm['index_code']): ?>
                    <strong><?= e($sm['index_code']) ?></strong>
                    <?php endif ?>
                    <?= e(mb_strimwidth($sm['name'], 0, 45, '…')) ?>
                </label>

            <?php endforeach ?>
        </div>

    </div>

<?php endif ?>