<?php
/**
 * edu/includes/curriculum_parser.php
 * Парсит xlsx/xls-файл РУПл и возвращает структурированные данные для БД.
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class CurriculumParser
{
    private array $errors   = [];
    private array $warnings = [];
    private ?string $selectedPlanSheet = null;
    private array $planSheets = [];
    private array $semesterMeta = [];

    private const MODULE_TYPES = [
        'ООД' => 'ООД',
        'ООМ' => 'ООМ',
        'БМ'  => 'БМ',
        'ПМ'  => 'ПМ',
        'ПА'  => 'ПА',
        'ИА'  => 'ИА',
        'ДП'  => 'ДП',
        'К'   => 'К',
        'Ф'   => 'Ф',
    ];

    /** Старый формат РУПл: используется только как резерв. */
    private const FALLBACK_COL = [
        'index'       => 1,
        'component'   => null,
        'name'        => 2,
        'exam'        => 3,
        'credit'      => 4,
        'ctrl_work'   => 5,
        'credits'     => 6,
        'total_hours' => 7,
        'theory'      => 8,
        'practice'    => 9,
        'coursework'  => 10,
        'srsp'        => 11,
        'srs'         => 12,
        'production'  => 13,
        'individual'  => 14,
        'sem_start'   => 15,
    ];

    public function parse(string $filePath, ?string $planSheet = null): array
    {
        $this->errors   = [];
        $this->warnings = [];
        $this->selectedPlanSheet = null;
        $this->planSheets = [];
        $this->semesterMeta = [];

        if (!file_exists($filePath)) {
            $this->errors[] = "Файл не найден: $filePath";
            return $this->fail();
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $e) {
            $this->errors[] = 'Не удалось открыть Excel-файл: ' . $e->getMessage();
            return $this->fail();
        }

        $this->planSheets = $this->findPlanSheets($spreadsheet->getSheetNames());

        $passport     = $this->parsePassport($spreadsheet);
        $passportRows = $passport['_rows'] ?? [];
        unset($passport['_rows']);
        $competencies = $this->parseCompetencies($spreadsheet);
        $calendar     = $this->parseProcessSchedule($spreadsheet);
        $summary      = $this->parseSummaryData($spreadsheet);
        $modules      = $this->parsePlan($spreadsheet, $planSheet);

        if (empty($modules)) {
            $this->errors[] = 'Лист «Учебный план» не содержит дисциплин.';
            return $this->fail();
        }

        return [
            'ok'                  => true,
            'errors'              => $this->errors,
            'warnings'            => $this->warnings,
            'passport'            => $passport,
            'passport_rows'       => $passportRows,
            'competencies'        => $competencies,
            'calendar'            => $calendar,
            'summary'             => $summary,
            'modules'             => $modules,
            'semester_meta'       => $this->semesterMeta,
            'plan_sheets'         => $this->planSheets,
            'selected_plan_sheet' => $this->selectedPlanSheet,
        ];
    }

    private function parsePassport(\PhpOffice\PhpSpreadsheet\Spreadsheet $wb): array
    {
        $sheetNames = $wb->getSheetNames();
        $name = $this->findSheet($sheetNames, ['паспорт', 'passport']);
        if (!$name) {
            $this->warnings[] = 'Лист «Паспорт» не найден — данные паспорта пропущены.';
            return [];
        }

        $ws = $wb->getSheetByName($name);
        $data = [];
        $rows = [];
        $qualificationParts = [];
        $lastField = null;
        $lastRowIndex = null;
        $maxRow = min((int)$ws->getHighestDataRow(), 120);

        for ($r = 1; $r <= $maxRow; $r++) {
            $label = $this->normText($this->cellValue($ws, 1, $r));
            $value = $this->normText($this->cellValue($ws, 3, $r));
            if ($value === '') {
                $value = $this->normText($this->cellValue($ws, 2, $r));
            }

            if ($label !== '' || $value !== '') {
                if ($label !== '') {
                    $rows[] = [
                        'label'      => $label,
                        'value'      => $value,
                        'sort_order' => count($rows) + 1,
                    ];
                    $lastRowIndex = count($rows) - 1;
                } elseif ($value !== '' && $lastRowIndex !== null && $lastField === 'qualification') {
                    $rows[$lastRowIndex]['value'] = trim($rows[$lastRowIndex]['value'] . "\n" . $value);
                }
            }

            $labelLow = mb_strtolower($label);

            if ($label !== '' && str_contains($labelLow, 'код') && str_contains($labelLow, 'специальност')) {
                if ($value !== '') {
                    if (preg_match('/^(\d{6,8})\s*[-–—]?\s*(.+)$/u', $value, $m)) {
                        $data['specialty_code'] = $m[1];
                        $data['specialty_name'] = trim($m[2]);
                    } else {
                        $data['specialty_name'] = $value;
                    }
                }
                $lastField = 'specialty';
                continue;
            }

            if ($label !== '' && str_contains($labelLow, 'квалификац')) {
                if ($value !== '') {
                    foreach (preg_split('/\R/u', $value) ?: [] as $part) {
                        $part = trim($part);
                        if ($part !== '') $qualificationParts[] = $part;
                    }
                }
                $lastField = 'qualification';
                continue;
            }

            // В шаблоне вторая квалификация идёт следующей строкой без подписи.
            if ($label === '' && $lastField === 'qualification' && $value !== '') {
                foreach (preg_split('/\R/u', $value) ?: [] as $part) {
                    $part = trim($part);
                    if ($part !== '') $qualificationParts[] = $part;
                }
                continue;
            }

            if ($label !== '') {
                $lastField = null;
            }
        }

        if ($qualificationParts) {
            $data['qualification'] = json_encode(array_values(array_unique($qualificationParts)), JSON_UNESCAPED_UNICODE);
        }
        $data['_rows'] = $rows;

        return $data;
    }

    private function parseProcessSchedule(\PhpOffice\PhpSpreadsheet\Spreadsheet $wb): array
    {
        $sheetNames = $wb->getSheetNames();
        $name = $this->findSheet($sheetNames, ['график учебного процесса', 'график']);
        if (!$name) return [];

        $ws = $wb->getSheetByName($name);
        $maxCol = min(Coordinate::columnIndexFromString($ws->getHighestColumn()), 80);
        $maxRow = min((int)$ws->getHighestDataRow(), 80);
        $mergeMap = $this->horizontalMergeMap($ws);
        $months = [];
        $weeks = [];
        $courses = [];
        $legend = [];

        for ($c = 1; $c <= $maxCol; $c++) {
            $month = $this->normText($this->cellValue($ws, $c, 3));
            if ($month !== '') $months[$c] = $month;
            $week = $this->normText($this->cellValue($ws, $c, 4));
            if (preg_match('/^\d+$/u', $week)) $weeks[$c] = (int)$week;
        }

        for ($r = 1; $r <= $maxRow; $r++) {
            $course = $this->normText($this->cellValue($ws, 2, $r));
            if (!preg_match('/^(I|II|III|IV|V|VI)$/u', $course)) continue;

            $items = [];
            $c = 1;
            while ($c <= $maxCol) {
                if (!isset($weeks[$c])) {
                    $c++;
                    continue;
                }

                $merge = $mergeMap[$r][$c] ?? null;
                if (($merge['skip'] ?? false) === true) {
                    $c++;
                    continue;
                }

                $endCol = (int)($merge['end_col'] ?? $c);
                $spanWeeks = 0;
                for ($cc = $c; $cc <= $endCol; $cc++) {
                    if (isset($weeks[$cc])) $spanWeeks++;
                }
                if ($spanWeeks < 1) $spanWeeks = 1;

                $items[] = [
                    'week_num' => $weeks[$c],
                    'month'    => $this->nearestLeftValue($months, $c),
                    'value'    => $this->normText($this->cellValue($ws, $c, $r)),
                    'span'     => $spanWeeks,
                ];

                $c = max($c + 1, $endCol + 1);
            }

            $courses[] = [
                'course' => $course,
                'items'  => $items,
            ];
        }

        $legendStartRow = null;
        for ($r = 1; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                if (str_contains(mb_strtolower($this->normText($this->cellValue($ws, $c, $r))), 'условные обозначения')) {
                    $legendStartRow = $r;
                    break 2;
                }
            }
        }
        $legendFrom = $legendStartRow ? $legendStartRow + 1 : 1;
        $legendTo = $legendStartRow ? min($maxRow, $legendStartRow + 8) : $maxRow;

        for ($r = $legendFrom; $r <= $legendTo; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $merge = $mergeMap[$r][$c] ?? null;
                if (($merge['skip'] ?? false) === true) continue;

                $code = $this->normText($this->cellValue($ws, $c, $r));
                if ($code === '' || !preg_match('/^(ТО|ПО|ПП|ДП|Пдн|ПА|ИА|К|ПС)$/u', $code)) continue;

                $descStart = (int)($merge['end_col'] ?? $c) + 1;
                for ($dc = $descStart; $dc <= min($maxCol, $descStart + 8); $dc++) {
                    $descMerge = $mergeMap[$r][$dc] ?? null;
                    if (($descMerge['skip'] ?? false) === true) continue;
                    $desc = $this->normText($this->cellValue($ws, $dc, $r));
                    if ($desc === '' || preg_match('/^(ТО|ПО|ПП|ДП|Пдн|ПА|ИА|К|ПС)$/u', $desc)) continue;
                    $legend[$code] = $desc;
                    break;
                }
            }
        }

        return ['courses' => $courses, 'legend' => $legend];
    }

    private function parseSummaryData(\PhpOffice\PhpSpreadsheet\Spreadsheet $wb): array
    {
        $sheetNames = $wb->getSheetNames();
        $name = $this->findSheet($sheetNames, ['сводные данные', 'свод']);
        if (!$name) return [];

        $ws = $wb->getSheetByName($name);
        $items = [];
        $maxRow = min((int)$ws->getHighestDataRow(), 80);
        for ($r = 1; $r <= $maxRow; $r++) {
            $course = $this->normText($this->cellValue($ws, 1, $r));
            if (!preg_match('/^(I|II|III|IV|V|VI|Итого)$/u', $course)) continue;
            $items[] = [
                'course'               => $course,
                'theory_weeks'         => $this->floatVal($this->cellValue($ws, 2, $r)),
                'theory_hours'         => $this->intVal($this->cellValue($ws, 3, $r)),
                'theory_credits'       => $this->floatVal($this->cellValue($ws, 4, $r)),
                'interim_attestation'  => $this->intVal($this->cellValue($ws, 5, $r)),
                'production_practice'  => $this->intVal($this->cellValue($ws, 6, $r)),
                'diploma_design'       => $this->intVal($this->cellValue($ws, 7, $r)),
                'final_attestation'    => $this->intVal($this->cellValue($ws, 8, $r)),
                'holidays'             => $this->intVal($this->cellValue($ws, 9, $r)),
                'vacations'            => $this->intVal($this->cellValue($ws, 10, $r)),
                'total_weeks'          => $this->intVal($this->cellValue($ws, 11, $r)),
                'sort_order'           => count($items) + 1,
            ];
        }
        return $items;
    }

    private function parseCompetencies(\PhpOffice\PhpSpreadsheet\Spreadsheet $wb): array
    {
        $sheetNames = $wb->getSheetNames();
        $name = $this->findSheet($sheetNames, ['компетенц', 'competenc']);
        if (!$name) {
            $this->warnings[] = 'Лист «Перечень компетенций» не найден.';
            return [];
        }

        $ws = $wb->getSheetByName($name);
        $items = [];
        $order = 0;
        $maxRow = (int)$ws->getHighestDataRow();
        $maxCol = min(Coordinate::columnIndexFromString($ws->getHighestColumn()), 12);

        for ($r = 1; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $code = $this->normText($this->cellValue($ws, $c, $r));
                if (!preg_match('/^К\s*\d+\.?$/u', $code)) continue;

                $desc = '';
                for ($dc = $c + 1; $dc <= min($maxCol, $c + 3); $dc++) {
                    $candidate = $this->normText($this->cellValue($ws, $dc, $r));
                    if ($candidate !== '') {
                        $desc = $candidate;
                        break;
                    }
                }
                if ($desc === '') continue 2;

                $items[] = [
                    'code'       => $code,
                    'name'       => $desc,
                    'sort_order' => ++$order,
                ];
                continue 2;
            }
        }

        return $items;
    }

    private function parsePlan(\PhpOffice\PhpSpreadsheet\Spreadsheet $wb, ?string $preferredSheet = null): array
    {
        $sheetNames = $wb->getSheetNames();
        $name = null;

        if ($preferredSheet !== null && $preferredSheet !== '' && in_array($preferredSheet, $sheetNames, true)) {
            $name = $preferredSheet;
        }

        if (!$name) {
            $candidates = $this->planSheets ?: $this->findPlanSheets($sheetNames);
            $name = $candidates[0] ?? null;
        }

        if (!$name) {
            $this->errors[] = 'Лист «Учебный план» не найден.';
            return [];
        }

        $this->selectedPlanSheet = $name;
        $ws = $wb->getSheetByName($name);
        $layout = $this->detectPlanLayout($ws);
        $semCols = $layout['semesters'];
        $this->semesterMeta = $this->parseSemesterMeta($ws, $layout);

        $modules = [];
        $parentStack = [];
        $parentTypes = [];
        $order = 0;
        $maxRow = (int)$ws->getHighestDataRow();

        for ($ri = 1; $ri <= $maxRow; $ri++) {
            $idx   = $this->normalizeIndexCode($this->normText($this->cellValue($ws, $layout['index'], $ri)));
            $componentName = '';
            if (!empty($layout['component'])) {
                $componentName = $this->normText($this->cellValue($ws, (int)$layout['component'], $ri));
            }
            $name2 = $this->normText($this->cellValue($ws, $layout['name'], $ri));

            if ($idx === '' && $name2 === '') continue;
            if ($idx === '1' && ($name2 === '2' || str_contains(mb_strtolower($name2), 'количество'))) continue;
            if (is_numeric($idx) && (int)$idx < 40 && $name2 === '') continue;

            if (($this->isLearningOutcomeIndex($idx) || $this->isChildDisciplineIndex($idx)) && $componentName === '') {
                [$name2, $componentName] = $this->splitLearningOutcomeName($name2);
            }

            $nameLow = mb_strtolower($name2);
            if (str_contains($nameLow, 'количество учебных недель') || str_contains($nameLow, 'итого в неделю')) continue;
            $isTotal = (str_contains($nameLow, 'итого') || str_contains($nameLow, 'всего'));

            $moduleType = $this->detectModuleType($idx, $name2);
            $parentKey = null;
            if (!$moduleType && $this->isLearningOutcomeIndex($idx)) {
                $parentKey = $this->detectParent($idx, $parentStack);
                if ($parentKey !== null && isset($parentTypes[$parentKey])) {
                    $moduleType = $parentTypes[$parentKey];
                }
            }
            if (!$moduleType && !$isTotal) continue;

            $credits    = $this->floatVal($this->cellValue($ws, $layout['credits'], $ri));
            $totalHours = $this->intVal($this->cellValue($ws, $layout['total_hours'], $ri));
            $theory     = $this->intVal($this->cellValue($ws, $layout['theory'], $ri));
            $practice   = $this->intVal($this->cellValue($ws, $layout['practice'], $ri));
            $coursework = $this->intVal($this->cellValue($ws, $layout['coursework'], $ri));
            $srsp       = $this->intVal($this->cellValue($ws, $layout['srsp'], $ri));
            $srs        = $this->intVal($this->cellValue($ws, $layout['srs'], $ri));
            $production = $this->sumIntValues($ws, $layout['production_cols'], $ri);
            $individual = $this->intVal($this->cellValue($ws, $layout['individual'], $ri));
            $exam       = $this->semesterVal($this->cellValue($ws, $layout['exam'], $ri));
            $credit     = $this->semesterVal($this->cellValue($ws, $layout['credit'], $ri));
            $ctrlWork   = $this->semesterVal($this->cellValue($ws, $layout['ctrl_work'], $ri));

            $distribution = [];
            foreach ($semCols as $semNum => $col) {
                $h = $this->intVal($this->cellValue($ws, $col, $ri));
                if ($h !== null && $h > 0) {
                    $distribution[(int)$semNum] = $h;
                }
            }

            $hasUsefulValues = ($credits !== null && $credits > 0)
                || ($totalHours !== null && $totalHours > 0)
                || ($theory !== null && $theory > 0)
                || ($practice !== null && $practice > 0)
                || ($coursework !== null && $coursework > 0)
                || ($srsp !== null && $srsp > 0)
                || ($srs !== null && $srs > 0)
                || ($production !== null && $production > 0)
                || ($individual !== null && $individual > 0)
                || $exam !== null
                || $credit !== null
                || $ctrlWork !== null
                || !empty($distribution);
            if (!$isTotal && $name2 === '' && $componentName === '' && !$hasUsefulValues) {
                continue;
            }

            if ($parentKey === null) {
                $parentKey = $this->detectParent($idx, $parentStack);
            }

            $module = [
                '_order'           => ++$order,
                '_parent_key'      => $parentKey,
                'index_code'       => $idx,
                'module_type'      => $moduleType ?? 'ИТОГО',
                'component_name'   => $componentName,
                'name'             => $name2,
                'credits'          => $credits,
                'total_hours'      => $totalHours,
                'theory_hours'     => $theory,
                'practice_hours'   => $practice,
                'coursework_hours' => $coursework,
                'srsp_hours'       => $srsp,
                'srs_hours'        => $srs,
                'production_hours' => $production,
                'individual_hours' => $individual,
                'exam_semester'    => $exam,
                'credit_semester'  => $credit,
                'control_work'     => $ctrlWork,
                'is_summary'       => $isTotal ? 1 : 0,
                'sort_order'       => $order,
                'distribution'     => $distribution,
            ];

            $modules[] = $module;
            if ($idx !== '') {
                $moduleIndex = count($modules) - 1;
                foreach ($this->indexAliases($idx) as $alias) {
                    $parentStack[$alias] = $moduleIndex;
                    $parentTypes[$alias] = $module['module_type'];
                }
            }
        }

        return $modules;
    }


    private function parseSemesterMeta(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $layout): array
    {
        $semCols = $layout['semesters'] ?? [];
        if (!$semCols) return [];

        $maxRow = min((int)$ws->getHighestDataRow(), 80);
        $nameCol = (int)($layout['name'] ?? self::FALLBACK_COL['name']);
        $weeksRow = null;
        $weeklyHoursRow = null;

        for ($r = 1; $r <= $maxRow; $r++) {
            $label = mb_strtolower($this->normText($this->cellValue($ws, $nameCol, $r)));
            if ($label === '') {
                // В некоторых файлах подпись может быть левее/правее основной колонки.
                for ($c = 1; $c <= min(6, Coordinate::columnIndexFromString($ws->getHighestColumn())); $c++) {
                    $candidate = mb_strtolower($this->normText($this->cellValue($ws, $c, $r)));
                    if ($candidate !== '') {
                        $label = $candidate;
                        break;
                    }
                }
            }

            if ($weeksRow === null && str_contains($label, 'количество учебных недель')) {
                $weeksRow = $r;
            }
            if ($weeklyHoursRow === null && (str_contains($label, 'итого в неделю') || str_contains($label, 'всего в неделю'))) {
                $weeklyHoursRow = $r;
            }
        }

        $items = [];
        foreach ($semCols as $semNum => $col) {
            $weeks = $weeksRow !== null ? $this->floatVal($this->cellValue($ws, (int)$col, $weeksRow)) : null;
            $weeklyHours = $weeklyHoursRow !== null ? $this->intVal($this->cellValue($ws, (int)$col, $weeklyHoursRow)) : null;
            if ($weeks === null && $weeklyHours === null) continue;
            $items[(int)$semNum] = [
                'semester_num' => (int)$semNum,
                'study_weeks'  => $weeks,
                'weekly_hours' => $weeklyHours,
            ];
        }

        return $items;
    }

    private function detectPlanLayout(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): array
    {
        $maxCol = Coordinate::columnIndexFromString($ws->getHighestColumn());
        $maxRow = min((int)$ws->getHighestDataRow(), 15);
        $logical = [];
        $bestRow = null;
        $bestMap = [];
        $bestCount = 0;

        for ($r = 1; $r <= $maxRow; $r++) {
            $map = [];
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = $this->normText($this->cellValue($ws, $c, $r));
                if (preg_match('/^\d+$/u', $v)) {
                    $n = (int)$v;
                    if ($n >= 1 && $n <= 40 && !isset($map[$n])) {
                        $map[$n] = $c;
                    }
                }
            }
            $count = count($map);
            if ($count > $bestCount && isset($map[1], $map[2], $map[3], $map[4])) {
                $bestCount = $count;
                $bestRow = $r;
                $bestMap = $map;
            }
        }

        if ($bestCount >= 8) {
            $logical = $bestMap;
        } else {
            $this->warnings[] = 'Не удалось точно определить строку нумерации колонок учебного плана — использована стандартная схема.';
        }

        $semesters = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = $this->normText($this->cellValue($ws, $c, $r));
                if (preg_match('/(\d+)\s*семестр/ui', $v, $m)) {
                    $sem = (int)$m[1];
                    if ($sem >= 1 && $sem <= 12 && !isset($semesters[$sem])) {
                        $semesters[$sem] = $c;
                    }
                }
            }
        }
        ksort($semesters, SORT_NUMERIC);

        if (!$semesters) {
            $start = $logical[15] ?? self::FALLBACK_COL['sem_start'];
            for ($s = 1; $s <= 8; $s++) {
                $semesters[$s] = $start + $s - 1;
            }
            $this->warnings[] = 'Заголовки семестров не найдены — распределение часов прочитано по стандартной схеме.';
        }

        $semActualCols = array_values($semesters);
        $firstSemesterCol = $semActualCols ? min($semActualCols) : null;
        $hasSeparatePractice = isset($logical[15]) && !in_array($logical[15], $semActualCols, true);

        $indexCol = $logical[1] ?? self::FALLBACK_COL['index'];
        $nameCol = $logical[2] ?? self::FALLBACK_COL['name'];
        $componentCol = null;
        if ($nameCol > $indexCol + 1) {
            // В новых РУПЛ между индексом и наименованием есть отдельная колонка:
            // например ПМ 8.1 | Производственная практика | РО 8.1...
            // Раньше эта колонка терялась, из-за чего практики выводились как РО.
            $componentCol = $indexCol + 1;
        }

        $headerRowLimit = $bestRow !== null ? min($maxRow, $bestRow) : min($maxRow, 8);
        $individualHeaderCol = $this->findHeaderColumn($ws, ['индивидуаль'], 1, $headerRowLimit, 1, $firstSemesterCol ? $firstSemesterCol - 1 : $maxCol);
        $individualCol = $individualHeaderCol
            ?? ($firstSemesterCol ? max(1, $firstSemesterCol - 1) : null)
            ?? ($hasSeparatePractice ? ($logical[15] ?? null) : ($logical[14] ?? null))
            ?? self::FALLBACK_COL['individual'];

        $srsCol = $logical[12] ?? self::FALLBACK_COL['srs'];
        if ($individualCol > $srsCol + 1) {
            $productionCols = range($srsCol + 1, $individualCol - 1);
        } else {
            $productionHeaderCols = $this->findHeaderColumns($ws, ['производствен', 'профессиональн'], 1, $headerRowLimit, 1, $firstSemesterCol ? $firstSemesterCol - 1 : $maxCol);
            if ($productionHeaderCols) {
                $productionCols = array_values(array_filter($productionHeaderCols, static fn($c) => $c < $individualCol));
            } else {
                $productionCols = $hasSeparatePractice
                    ? array_values(array_filter([$logical[13] ?? null, $logical[14] ?? null]))
                    : [($logical[13] ?? self::FALLBACK_COL['production'])];
            }
        }

        return [
            'index'           => $indexCol,
            'component'       => $componentCol,
            'name'            => $nameCol,
            'exam'            => $logical[3]  ?? self::FALLBACK_COL['exam'],
            'credit'          => $logical[4]  ?? self::FALLBACK_COL['credit'],
            'ctrl_work'       => $logical[5]  ?? self::FALLBACK_COL['ctrl_work'],
            'credits'         => $logical[6]  ?? self::FALLBACK_COL['credits'],
            'total_hours'     => $logical[7]  ?? self::FALLBACK_COL['total_hours'],
            'theory'          => $logical[8]  ?? self::FALLBACK_COL['theory'],
            'practice'        => $logical[9]  ?? self::FALLBACK_COL['practice'],
            'coursework'      => $logical[10] ?? self::FALLBACK_COL['coursework'],
            'srsp'            => $logical[11] ?? self::FALLBACK_COL['srsp'],
            'srs'             => $logical[12] ?? self::FALLBACK_COL['srs'],
            'production_cols' => $productionCols,
            'individual'      => $individualCol,
            'semesters'       => $semesters,
            '_number_row'     => $bestRow,
        ];
    }

    private function detectModuleType(string $idx, string $name): ?string
    {
        $idx = trim($idx);
        foreach (self::MODULE_TYPES as $prefix => $type) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\s|\d|$)/u', $idx)) return $type;
        }
        $nl = mb_strtolower($name);
        if (str_contains($nl, 'факультатив')) return 'Ф';
        if (str_contains($nl, 'консультации')) return 'К';
        if (str_contains($nl, 'дипломн')) return 'ДП';
        if (str_contains($nl, 'итоговая аттестация')) return 'ИА';
        if (str_contains($nl, 'промежуточная аттестация')) return 'ПА';
        return null;
    }

    private function detectParent(string $idx, array $parentStack): ?string
    {
        if ($idx === '') return null;
        $idxClean = rtrim(trim($idx), '.');

        // В РУПл формата «учебный план РО» дисциплины идут строками РО 1.1, РО 1.2...
        // Родитель при этом находится выше как ООМ 1 / ПМ 1 / БМ 1.
        if (preg_match('/^РО\s*(\d+)\.(\d+)/u', $idxClean, $m)) {
            $bestAlias = null;
            $bestPos = -1;
            foreach (['ООМ', 'БМ', 'ПМ'] as $prefix) {
                foreach ($this->indexAliases($prefix . ' ' . $m[1]) as $alias) {
                    if (isset($parentStack[$alias]) && $parentStack[$alias] > $bestPos) {
                        $bestAlias = $alias;
                        $bestPos = $parentStack[$alias];
                    }
                }
            }
            if ($bestAlias !== null) return $bestAlias;
        }

        if (preg_match('/^([\p{L}]+)\s*(\d+)\.(\d+)/u', $idxClean, $m)) {
            foreach ($this->indexAliases($m[1] . ' ' . $m[2]) as $alias) {
                if (isset($parentStack[$alias])) return $alias;
            }
            if (isset($parentStack[$m[1]])) return $m[1];
        }

        if (preg_match('/^([\p{L}]+)\s*(\d+)$/u', $idxClean)) {
            return null;
        }

        foreach ($this->indexAliases($idxClean) as $alias) {
            if (isset($parentStack[$alias])) return $alias;
        }
        return null;
    }

    private function findPlanSheets(array $names): array
    {
        $regular = [];
        $resultOriented = [];
        foreach ($names as $sn) {
            $sl = mb_strtolower(trim($sn));
            if (!str_contains($sl, 'учебный') || !str_contains($sl, 'план')) continue;
            if (str_contains($sl, ' ро') || str_ends_with($sl, 'ро')) {
                $resultOriented[] = $sn;
                continue;
            }
            $regular[] = $sn;
        }
        return array_merge($regular, $resultOriented);
    }

    private function normalizeIndexCode(string $idx): string
    {
        $idx = $this->normText($idx);
        if ($idx === '') return '';
        $idx = preg_replace('/\s+([.])/u', '$1', $idx) ?? $idx;
        $idx = preg_replace('/^([\p{L}]+)(\d)/u', '$1 $2', $idx) ?? $idx;
        $idx = preg_replace('/\s+/u', ' ', $idx) ?? $idx;
        return trim($idx);
    }

    private function isLearningOutcomeIndex(string $idx): bool
    {
        return (bool)preg_match('/^РО\s*\d+(?:\s*\.\s*\d+)+\s*\.?$/u', trim($idx));
    }

    private function isChildDisciplineIndex(string $idx): bool
    {
        return (bool)preg_match('/^[\p{L}]+\s*\d+\s*\.\s*\d+/u', trim($idx));
    }

    private function splitLearningOutcomeName(string $value): array
    {
        $value = $this->normText($value);
        if ($value === '' || !str_contains($value, '/')) {
            return [$value, ''];
        }

        [$left, $right] = array_pad(preg_split('/\s*\/\s*/u', $value, 2), 2, '');
        $left = $this->normText($left);
        $right = $this->normText($right);
        if ($left === '' || $right === '') {
            return [$value, ''];
        }
        return [$left, $right];
    }

    private function indexAliases(string $idx): array
    {
        $base = trim($idx);
        if ($base === '') return [];
        $withoutDot = rtrim($base, '.');
        $compact = preg_replace('/\s+/u', '', $withoutDot) ?? $withoutDot;
        $items = [$base, $withoutDot, $compact, $compact . '.'];
        return array_values(array_unique(array_filter($items, static fn($v) => $v !== '')));
    }

    private function findHeaderColumn(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $needles, int $fromRow, int $toRow, int $fromCol, int $toCol): ?int
    {
        $cols = $this->findHeaderColumns($ws, $needles, $fromRow, $toRow, $fromCol, $toCol);
        return $cols[0] ?? null;
    }

    private function findHeaderColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $needles, int $fromRow, int $toRow, int $fromCol, int $toCol): array
    {
        $found = [];
        for ($r = $fromRow; $r <= $toRow; $r++) {
            for ($c = $fromCol; $c <= $toCol; $c++) {
                $text = mb_strtolower($this->normText($this->cellValue($ws, $c, $r)));
                if ($text === '') continue;
                foreach ($needles as $needle) {
                    if (str_contains($text, mb_strtolower($needle))) {
                        $found[$c] = $c;
                        break;
                    }
                }
            }
        }
        ksort($found, SORT_NUMERIC);
        return array_values($found);
    }

    /**
     * Карта горизонтальных объединений Excel. Нужна для графика учебного процесса:
     * периоды вида C5:T5 должны отображаться одной ячейкой на 18 недель, а не теряться.
     */
    private function horizontalMergeMap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): array
    {
        $map = [];
        foreach ($ws->getMergeCells() as $range) {
            try {
                [$start, $end] = Coordinate::rangeBoundaries($range);
            } catch (Throwable $e) {
                continue;
            }

            $startCol = (int)$start[0];
            $startRow = (int)$start[1];
            $endCol = (int)$end[0];
            $endRow = (int)$end[1];
            if ($endCol <= $startCol) continue;

            for ($row = $startRow; $row <= $endRow; $row++) {
                $map[$row][$startCol] = [
                    'start_col' => $startCol,
                    'end_col'   => $endCol,
                    'skip'      => false,
                ];
                for ($col = $startCol + 1; $col <= $endCol; $col++) {
                    $map[$row][$col] = [
                        'start_col' => $startCol,
                        'end_col'   => $endCol,
                        'skip'      => true,
                    ];
                }
            }
        }
        return $map;
    }

    private function nearestLeftValue(array $map, int $col): string
    {
        for ($c = $col; $c >= 1; $c--) {
            if (isset($map[$c])) return (string)$map[$c];
        }
        return '';
    }

    private function findSheet(array $names, array $keywords): ?string
    {
        foreach ($names as $sn) {
            $sl = mb_strtolower($sn);
            foreach ($keywords as $kw) {
                if (str_contains($sl, mb_strtolower($kw))) return $sn;
            }
        }
        return null;
    }

    private function cell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $col, int $row): \PhpOffice\PhpSpreadsheet\Cell\Cell
    {
        $coord = Coordinate::stringFromColumnIndex(max(1, $col)) . max(1, $row);
        return $ws->getCell($coord);
    }

    private function cellValue(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, ?int $col, int $row)
    {
        if ($col === null || $col < 1) return null;
        $cell = $this->cell($ws, $col, $row);
        try {
            $v = $cell->getCalculatedValue();
        } catch (Throwable $e) {
            $v = $cell->getValue();
        }
        if ($v instanceof RichText) return $v->getPlainText();
        return $v;
    }

    private function normText($v): string
    {
        if ($v instanceof RichText) $v = $v->getPlainText();
        $s = trim((string)$v);
        $s = str_replace("\xc2\xa0", ' ', $s);
        $s = preg_replace('/[ \t]+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private function floatVal($v): ?float
    {
        $v = $this->normText($v);
        if ($v === '' || $v === '-' || str_starts_with($v, '=')) return null;
        $v = str_replace([' ', ','], ['', '.'], $v);
        return is_numeric($v) ? (float)$v : null;
    }

    private function intVal($v): ?int
    {
        $f = $this->floatVal($v);
        return $f !== null ? (int)round($f) : null;
    }

    private function sumIntValues(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $cols, int $row): ?int
    {
        $sum = 0;
        $hasValue = false;
        foreach ($cols as $col) {
            $value = $this->intVal($this->cellValue($ws, (int)$col, $row));
            if ($value !== null) {
                $sum += $value;
                $hasValue = true;
            }
        }
        return $hasValue ? $sum : null;
    }

    private function semesterVal($v): ?string
    {
        $v = $this->normText($v);
        if ($v === '' || $v === '-' || $v === '0' || str_starts_with($v, '=')) return null;
        return $v;
    }

    private function fail(): array
    {
        return [
            'ok'          => false,
            'errors'      => $this->errors,
            'warnings'    => $this->warnings,
            'plan_sheets' => $this->planSheets,
        ];
    }

    public function getErrors(): array { return $this->errors; }
    public function getWarnings(): array { return $this->warnings; }
}
