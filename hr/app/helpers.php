<?php
// hr/app/helpers.php — функции форматирования для шаблонов
// ── Цвет и текст статуса ──────────────────────────────────────
function statusBadge(?string $s): string {
    return match($s) {
        'employed'   => '<span class="badge badge-green">Трудоустроен</span>',
        'unemployed' => '<span class="badge badge-red">Не трудоустроен</span>',
        'studying'   => '<span class="badge badge-blue">Продолжает учёбу</span>',
        'decree'     => '<span class="badge badge-amber">В декрете</span>',
        'military'   => '<span class="badge badge-gray">Военная служба</span>',
        'relocation' => '<span class="badge badge-gray">Выезд на ПМЖ</span>',
        'other'      => '<span class="badge badge-gray">Прочее</span>',
        'unknown'    => '<span class="badge badge-gray">Неизвестно</span>',
        default      => '<span class="badge badge-gray">—</span>',
    };
}
function empTypeText(?string $t): string {
    return match($t) {
        'full_time'     => 'Полная занятость',
        'part_time'     => 'Частичная',
        'contract'      => 'Договор/Контракт',
        'self_employed' => 'Самозанятый',
        'other'         => 'Прочее',
        default         => '—',
    };
}
function fmtDate(?string $d): string {
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d.m.Y') : $d;
}

function groupStateBadge(?string $state): string {
    return match($state) {
        'current'  => '',
        'previous' => '<span class="badge badge-amber">Выпускники</span>',
        'archive'  => '<span class="badge badge-gray">Архив</span>',
        default    => '<span class="badge badge-gray">—</span>',
    };
}

function percentOf($part, $total): float {
    $part = (int)$part;
    $total = (int)$total;
    return $total > 0 ? round($part / $total * 100, 1) : 0.0;
}
