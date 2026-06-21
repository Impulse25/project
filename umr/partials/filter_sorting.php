<div class="form-group">
    <label class="form-label">Сортировка</label>
    
    <div style="display:flex;gap:.4rem">

        <a href="<?= sortLink($filterYear, $filterSem, $filterGroupId, 'asc', $onlyMine) ?>"
             class="sort-btn <?= $sortDir === 'asc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
            </svg>А → Я
        </a>

        <a href="<?= sortLink($filterYear, $filterSem, $filterGroupId, 'desc', $onlyMine) ?>"
             class="sort-btn <?= $sortDir === 'desc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
            </svg>Я → А
        </a>
        
    </div>
</div>