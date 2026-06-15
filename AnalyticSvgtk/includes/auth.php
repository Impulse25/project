<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentRole(): string
{
    return $_SESSION['role'] ?? '';
}

function isAdmin(): bool
{
    return currentRole() === 'admin';
}

function isTeacher(): bool
{
    return currentRole() === 'teacher';
}

function isStudent(): bool
{
    return currentRole() === 'student';
}

function getTeacherId(PDO $pdo): ?int
{
    if (!isTeacher()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
    $stmt->execute([currentUserId()]);
    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function getStudentId(PDO $pdo): ?int
{
    if (!isStudent()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([currentUserId()]);
    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function getAllowedGroupIds(PDO $pdo): array
{
    if (isAdmin()) {
        $stmt = $pdo->query("SELECT id FROM groups ORDER BY name");
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if (isTeacher()) {
        $teacherId = getTeacherId($pdo);
        if (!$teacherId) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT group_id
            FROM teacher_groups
            WHERE teacher_id = ?

            UNION

            SELECT DISTINCT id
            FROM groups
            WHERE curator_id = ?
        ");
        $stmt->execute([$teacherId, $teacherId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if (isStudent()) {
        $stmt = $pdo->prepare("SELECT group_id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([currentUserId()]);
        $groupId = $stmt->fetchColumn();
        return $groupId ? [(int)$groupId] : [];
    }

    return [];
}

function placeholders(array $items): string
{
    return implode(',', array_fill(0, count($items), '?'));
}

function roleTitle(): string
{
    return [
        'admin' => 'Администратор',
        'teacher' => 'Преподаватель',
        'student' => 'Студент',
    ][currentRole()] ?? 'Пользователь';
}

function formatPersonName(?string $name): string
{
    $value = trim((string)$name);
    $map = [
        'IVANOV S.P.' => 'Иванов С. П.', 'URAKOVA M.S.' => 'Уракова М. С.', 'SADYKOV A.A.' => 'Садыков А. А.',
        'KUZNETSOVA E.V.' => 'Кузнецова Е. В.', 'ISKAKOV N.T.' => 'Искаков Н. Т.', 'SMIRNOVA O.P.' => 'Смирнова О. П.',
        'AKHMETOV D.K.' => 'Ахметов Д. К.', 'KARIMOVA A.S.' => 'Каримова А. С.', 'PETROV V.I.' => 'Петров В. И.',
        'SULTANOV B.M.' => 'Султанов Б. М.', 'PUSHKAREV A.A.' => 'Пушкарев А. А.', 'ABDRAKHMANOV A.K.' => 'Абдрахманов А. К.',
        'SAPAROV N.R.' => 'Сапаров Н. Р.', 'TULEGENOV D.A.' => 'Тулегенов Д. А.', 'NURPEISOVA M.K.' => 'Нурпеисова М. К.',
        'ZHUMABEKOV A.T.' => 'Жумабеков А. Т.', 'KADYROVA A.A.' => 'Кадырова А. А.', 'SMAGULOV E.R.' => 'Смагулов Е. Р.',
        'PUSHKAREV A.A' => 'Пушкарев А. А.', 'IVANOV D.S' => 'Иванов Д. С.', 'PETROV K.A' => 'Петров К. А.',
        'SMIRNOV I.V' => 'Смирнов И. В.', 'SULTANOV A.A' => 'Султанов А. А.', 'KUZNETSOV P.P' => 'Кузнецов П. П.',
        'ERLANOV N.N' => 'Ерланов Н. Н.', 'KIM A.S' => 'Ким А. С.', 'ABDRAKHMANOV A.A' => 'Абдрахманов А. А.',
        'OMAROV D.S' => 'Омаров Д. С.', 'SERIKOV A.A' => 'Сериков А. А.', 'ZHUMABEKOV A.S' => 'Жумабеков А. С.',
        'KALIEV M.S' => 'Калиев М. С.', 'AMANOV A.A' => 'Аманов А. А.', 'BEKETOV D.S' => 'Бекетов Д. С.',
        'NURPEISOV A.A' => 'Нурпеисов А. А.', 'SAPAROV A.A' => 'Сапаров А. А.', 'TLEUBERGENOV A.S' => 'Тлеубергенов А. С.',
        'AKHMETOV A.A' => 'Ахметов А. А.', 'ALIMOV D.S' => 'Алимов Д. С.', 'ISKAKOV A.A' => 'Искаков А. А.',
        'MUKANOV A.S' => 'Муканов А. С.', 'BAYTASOV A.A' => 'Байтасов А. А.', 'KARIMOV A.S' => 'Каримов А. С.',
        'DOSZHANOV A.A' => 'Досжанов А. А.', 'AKYLOV A.S' => 'Акылов А. С.', 'KALIYEV A.A' => 'Калиев А. А.',
        'SEITOV A.S' => 'Сеитов А. С.', 'RAKHIMOV A.A' => 'Рахимов А. А.', 'MUSIN A.S' => 'Мусин А. С.',
        'TOKTAROV A.A' => 'Токтаров А. А.', 'YESENOV A.S' => 'Есенов А. С.', 'MAKSUTOV A.A' => 'Максутов А. А.',
        'ALPYSBAYEV A.S' => 'Алпысбаев А. С.', 'TASBOLATOV A.A' => 'Тасболатов А. А.', 'YERMEKOV A.S' => 'Ермеков А. С.',
        'KUATOV A.A' => 'Куатов А. А.', 'ZHANIBEKOV A.S' => 'Жанибеков А. С.', 'ASANOV A.A' => 'Асанов А. А.',
        'SAGYNOV A.S' => 'Сагынов А. С.', 'AIDOSOV A.A' => 'Айдосов А. А.', 'NURLANOV A.S' => 'Нурланов А. С.',
        'RYSBEKOV A.A' => 'Рысбеков А. А.', 'ABDULLIN A.S' => 'Абдуллин А. С.', 'AMANBEKOV A.A' => 'Аманбеков А. А.',
        'SERIKBAEV A.S' => 'Серикбаев А. С.', 'DAULETOV A.A' => 'Даулетов А. А.', 'KUANYSHOV A.S' => 'Куанышов А. С.',
        'AMANZHOL A.S.' => 'Аманжол А. С.', 'SERIKOV M.T.' => 'Сериков М. Т.', 'KALIEVA D.R.' => 'Калиева Д. Р.',
        'BEKETOV A.N.' => 'Бекетов А. Н.', 'RAKHIMOVA G.S.' => 'Рахимова Г. С.', 'ISMAILOV E.K.' => 'Исмаилов Е. К.',
        'NURZHANOVA A.T.' => 'Нуржанова А. Т.', 'BAIMUKHANOV S.D.' => 'Баймуханов С. Д.', 'KUSHPANOVA M.A.' => 'Кушпанова М. А.',
        'TOKTAROV B.N.' => 'Токтаров Б. Н.', 'ABDULLIN A.A.' => 'Абдуллин А. А.', 'YERZHANOV D.K.' => 'Ержанов Д. К.',
        'MUSINA A.S.' => 'Мусина А. С.', 'AIDAROV T.M.' => 'Айдаров Т. М.', 'KARATAEVA E.R.' => 'Каратаева Е. Р.',
        'SAGYNDYKOV A.B.' => 'Сагындыков А. Б.', 'OMAROVA D.K.' => 'Омарова Д. К.', 'BEISENOV N.A.' => 'Бейсенов Н. А.',
        'MOLDAGALIYEVA A.S.' => 'Молдагалиева А. С.', 'ZHANIBEKOV E.T.' => 'Жанибеков Е. Т.', 'SATPAEVA M.R.' => 'Сатпаева М. Р.',
        'KUDAIBERGENOV A.K.' => 'Кудайбергенов А. К.', 'YESSENOVA A.A.' => 'Есенова А. А.', 'TASBOLATOV N.D.' => 'Тасболатов Н. Д.',
        'KENZHETAYEV M.S.' => 'Кенжетаев М. С.', 'NURGALIEVA A.T.' => 'Нургалиева А. Т.', 'AKHMETZHANOV D.R.' => 'Ахметжанов Д. Р.',
        'KULZHANOVA G.K.' => 'Кулжанова Г. К.', 'SARSENOV E.A.' => 'Сарсенов Е. А.', 'ZHAKSYLYKOVA M.N.' => 'Жаксылыкова М. Н.',
        'ALIMOV B.S.' => 'Алимов Б. С.', 'YESPENOVA A.R.' => 'Еспенова А. Р.', 'DUISEBAYEV N.T.' => 'Дуйсебаев Н. Т.',
        'NURGALIYEVA K.A.' => 'Нургалиева К. А.', 'BAKYTOV M.E.' => 'Бакытов М. Е.', 'SEITOVA A.D.' => 'Сеитова А. Д.',
        'AUBAKIROV D.S.' => 'Аубакиров Д. С.', 'MUKHAMEDZHANOVA A.K.' => 'Мухамеджанова А. К.', 'KALIYEV N.R.' => 'Калиев Н. Р.',
        'ZHOLDASOVA M.S.' => 'Жолдасова М. С.'
    ];
    return $map[$value] ?? $value;
}

function gradeCategoryKey($grade): string
{
    if ($grade === null || $grade === '') {
        return 'none';
    }
    $value = (int)$grade;
    if ($value >= 90 && $value <= 100) return 'excellent';
    if ($value >= 70 && $value <= 89) return 'good';
    if ($value >= 51 && $value <= 69) return 'satisfactory';
    if ($value >= 0 && $value <= 50) return 'unsatisfactory';
    return 'none';
}

function gradeCategoryTitle($grade): string
{
    return [
        'excellent' => '5 (90–100) — отлично',
        'good' => '4 (70–89) — хорошо',
        'satisfactory' => '3 (51–69) — удовлетворительно',
        'unsatisfactory' => '2 (0–50) — неудовлетворительно',
        'none' => 'Нет оценки',
    ][gradeCategoryKey($grade)] ?? 'Нет оценки';
}

function emptyGradeDistribution(): array
{
    return ['excellent' => 0, 'good' => 0, 'satisfactory' => 0, 'unsatisfactory' => 0];
}




function gradeCategoryCss($grade): string
{
    return 'grade-' . gradeCategoryKey($grade);
}

function gradeCategoryShortTitle($grade): string
{
    return [
        'excellent' => '5',
        'good' => '4',
        'satisfactory' => '3',
        'unsatisfactory' => '2',
        'none' => '-',
    ][gradeCategoryKey($grade)] ?? '-';
}

function gradeCriterionRange(string $criterion): ?array
{
    $ranges = [
        'excellent' => [90, 100],
        'good' => [70, 89],
        'satisfactory' => [51, 69],
        'unsatisfactory' => [0, 50],
    ];
    return $ranges[$criterion] ?? null;
}

function periodStartDate(string $period, string $dateTo = ''): ?string
{
    $base = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : date('Y-m-d');
    if ($period === 'today') return $base;
    if ($period === 'week') return date('Y-m-d', strtotime($base . ' -6 days'));
    if ($period === 'month') return date('Y-m-d', strtotime($base . ' -30 days'));
    if ($period === 'halfyear') return date('Y-m-d', strtotime($base . ' -6 months'));
    if ($period === 'year') return date('Y-m-d', strtotime($base . ' -1 year'));
    return null;
}

function studentStatusByCriteria($avgGrade, $avgAttendance, int $badGrades = 0): array
{
    // Статус в кабинете ученика определяется по успеваемости.
    // Посещаемость выводится отдельным критерием, чтобы средний балл 70+ не давал статус "Требует внимания".
    $grade = (float)($avgGrade ?? 0);
    if ($grade >= 70 && $badGrades === 0) {
        return ['Стабильно', 'badge-success', 'Средний балл в норме'];
    }
    if ($grade < 60 || $badGrades >= 2) {
        return ['Риск', 'badge-danger', 'Нужен контроль успеваемости'];
    }
    return ['Требует внимания', 'badge-warning', 'Есть показатели, которые лучше подтянуть'];
}

function ensurePvtDemoGrades(PDO $pdo): void
{
    try {
        $studentStmt = $pdo->query("SELECT s.id AS student_id, s.group_id FROM students s JOIN groups g ON g.id = s.group_id WHERE g.name LIKE 'ПВТ %' ORDER BY s.group_id, s.id");
        $students = $studentStmt ? $studentStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $subjectStmt = $pdo->query("SELECT sg.group_id, sg.subject_id FROM subject_groups sg JOIN groups g ON g.id = sg.group_id JOIN subjects sub ON sub.id = sg.subject_id WHERE g.name LIKE 'ПВТ %' AND sub.name IN ('Программирование','Базы данных','Веб-разработка') ORDER BY sg.group_id, sg.subject_id");
        $subjectRows = $subjectStmt ? $subjectStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $subjectsByGroup = [];
        foreach ($subjectRows as $row) {
            $gid = (int)$row['group_id'];
            if (!isset($subjectsByGroup[$gid])) {
                $subjectsByGroup[$gid] = [];
            }
            $sid = (int)$row['subject_id'];
            if (!in_array($sid, $subjectsByGroup[$gid], true)) {
                $subjectsByGroup[$gid][] = $sid;
            }
        }
        $demo = [
            1 => [94, 86, 91, 88, 97, 83],
            2 => [78, 72, 84, 69, 75, 81],
            3 => [82, 89, 76, 85, 73, 80],
            4 => [67, 74, 79, 88, 92, 70],
        ];
        $check = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_id = ? ORDER BY id DESC LIMIT 1");
        $insert = $pdo->prepare("INSERT INTO grades (student_id, subject_id, grade, grade_date) VALUES (?, ?, ?, ?)");
        $update = $pdo->prepare("UPDATE grades SET grade = ?, grade_date = ? WHERE id = ?");
        $cleanup = $pdo->prepare("DELETE FROM grades WHERE grade IN (2,3,4,5) AND student_id = ?");
        $i = 0;
        foreach ($students as $student) {
            $studentId = (int)$student['student_id'];
            $groupId = (int)$student['group_id'];
            if (empty($subjectsByGroup[$groupId])) {
                continue;
            }
            $cleanup->execute([$studentId]);
            foreach ($subjectsByGroup[$groupId] as $subjectId) {
                $values = $demo[$groupId] ?? [78, 84, 91, 67];
                $grade = (int)$values[$i % count($values)];
                $date = date('Y-m-d', strtotime('2026-05-01 +' . ($i % 28) . ' days'));
                $check->execute([$studentId, $subjectId]);
                $existingId = $check->fetchColumn();
                if ($existingId) {
                    $update->execute([$grade, $date, (int)$existingId]);
                } else {
                    $insert->execute([$studentId, $subjectId, $grade, $date]);
                }
                $i++;
            }
        }
    } catch (Throwable $e) {
        // Демо-наполнение не должно ломать страницу, если таблицы ещё не импортированы.
    }
}
