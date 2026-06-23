<?php
// edit_user.php - Редактирование профиля пользователя

require_once __DIR__ . '/../config/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/language.php';

requireRole('admin');

function request_table_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function request_ensure_department_head_columns(PDO $pdo): void
{
    try {
        if (!request_table_column_exists($pdo, 'users', 'is_department_head')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_department_head TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!request_table_column_exists($pdo, 'users', 'head_department_id')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN head_department_id INT NULL");
        }
        if (!request_table_column_exists($pdo, 'users', 'is_pcc_head')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_pcc_head TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!request_table_column_exists($pdo, 'users', 'is_methodist')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_methodist TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!request_table_column_exists($pdo, 'users', 'is_practice_director')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_practice_director TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {
        // Если у пользователя БД нет прав на ALTER, страницу не ломаем.
        // Для ручного применения есть edu/migrations/017_teacher_department_heads.sql и 018_hr_practice_director_and_statuses.sql.
    }
}

function request_is_teacher_role($role): bool
{
    $role = trim((string)$role);
    $role = function_exists('mb_strtolower') ? mb_strtolower($role, 'UTF-8') : strtolower($role);
    return in_array($role, ['teacher', '3', 'преподаватель', 'препод', 'технолог'], true);
}

request_ensure_department_head_columns($pdo);

$user = getCurrentUser();
$userId = $_GET['id'] ?? null;

if (!$userId) {
    header('Location: admin_dashboard.php?tab=users');
    exit();
}

// Получение данных пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$editUser = $stmt->fetch();

if (!$editUser) {
    header('Location: admin_dashboard.php?tab=users');
    exit();
}

// Получение всех ролей из таблицы roles
$stmt = $pdo->query("SELECT role_code, role_name_ru FROM roles ORDER BY role_name_ru ASC");
$roles = $stmt->fetchAll();

$departmentHeadColumnsAvailable = request_table_column_exists($pdo, 'users', 'is_department_head')
    && request_table_column_exists($pdo, 'users', 'head_department_id');

$pccMethodistColumnsAvailable = request_table_column_exists($pdo, 'users', 'is_pcc_head')
    && request_table_column_exists($pdo, 'users', 'is_methodist');
$practiceDirectorColumnAvailable = request_table_column_exists($pdo, 'users', 'is_practice_director');

$departments = [];
try {
    $departments = $pdo->query("
        SELECT id, department_name
        FROM (
            SELECT id, MIN(department_name) AS department_name
            FROM departments
            GROUP BY id
        ) d
        ORDER BY department_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $departments = [];
}

$success = '';
$error = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = $_POST['username'];
    $fullName = $_POST['full_name'];
    $role = $_POST['role'];
    $position = $_POST['position'];
    $newPassword = $_POST['new_password'] ?? '';
    $postedDepartmentHead = ($departmentHeadColumnsAvailable && request_is_teacher_role($role) && isset($_POST['is_department_head'])) ? 1 : 0;
    
    $isPccHead = ($pccMethodistColumnsAvailable && request_is_teacher_role($role) && isset($_POST['is_pcc_head'])) ? 1 : 0;
    $isMethodist = ($pccMethodistColumnsAvailable && request_is_teacher_role($role) && isset($_POST['is_methodist'])) ? 1 : 0;
    $isPracticeDirector = ($practiceDirectorColumnAvailable && isset($_POST['is_practice_director'])) ? 1 : 0;
    $isDepartmentHead = ($postedDepartmentHead && !$isPracticeDirector) ? 1 : 0;

    if ($postedDepartmentHead && $isPracticeDirector) {
        $error = 'Выберите только одну HR-роль: заведующий отделением или заместитель директора по практике.';
    }
    
    $headDepartmentId = null;
    if (!$error && $isDepartmentHead) {
        $headDepartmentId = ($_POST['head_department_id'] ?? '') !== '' ? (int)$_POST['head_department_id'] : null;
        if (!$headDepartmentId) {
            $error = 'Выберите отделение для заведующего.';
        }
    }
    
    // Проверка уникальности логина
    if ($username !== $editUser['username']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Пользователь с таким логином уже существует!';
        }
    }
    
    if (!$error) {
        // Обновление данных
        $setSql = "username = ?, full_name = ?, role = ?, position = ?";
        $updateParams = [$username, $fullName, $role, $position];

        if ($departmentHeadColumnsAvailable) {
            $setSql .= ", is_department_head = ?, head_department_id = ?";
            $updateParams[] = $isDepartmentHead;
            $updateParams[] = $isDepartmentHead ? $headDepartmentId : null;
        }
        
        if ($pccMethodistColumnsAvailable) {
            $setSql .= ", is_pcc_head = ?, is_methodist = ?";
            $updateParams[] = $isPccHead;
            $updateParams[] = $isMethodist;
        }

        if ($practiceDirectorColumnAvailable) {
            $setSql .= ", is_practice_director = ?";
            $updateParams[] = $isPracticeDirector;
        }

        if ($newPassword) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $setSql .= ", password = ?";
            $updateParams[] = $hashedPassword;
        }

        $updateParams[] = $userId;
        $stmt = $pdo->prepare("UPDATE users SET $setSql WHERE id = ?");
        $stmt->execute($updateParams);
        
        if (function_exists('clearPermissionsCache')) {
            clearPermissionsCache();
        }
        $success = 'Профиль успешно обновлён!';
        
        // Обновляем данные для отображения
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $editUser = $stmt->fetch();
    }
}

// Получение названия роли для отображения
$stmt = $pdo->prepare("SELECT role_name_ru FROM roles WHERE role_code = ?");
$stmt->execute([$editUser['role']]);
$currentRoleName = $stmt->fetchColumn();

// Если роль не найдена в таблице - используем старые названия
if (!$currentRoleName) {
    $oldRoleNames = [
        'admin' => 'Администратор',
        'director' => 'Директор',
        'teacher' => 'Учитель',
        'technician' => 'Системотехник'
    ];
    $currentRoleName = $oldRoleNames[$editUser['role']] ?? 'Неизвестная роль';
}

$isPccHeadChecked = $pccMethodistColumnsAvailable && ((int)($editUser['is_pcc_head'] ?? 0) === 1);
$isMethodistChecked = $pccMethodistColumnsAvailable && ((int)($editUser['is_methodist'] ?? 0) === 1);
$isPracticeDirectorChecked = $practiceDirectorColumnAvailable && ((int)($editUser['is_practice_director'] ?? 0) === 1);
$isDepartmentHeadChecked = $departmentHeadColumnsAvailable && !$isPracticeDirectorChecked && ((int)($editUser['is_department_head'] ?? 0) === 1);
$selectedHeadDepartmentId = $departmentHeadColumnsAvailable ? (int)($editUser['head_department_id'] ?? 0) : 0;
$isTeacherSelected = request_is_teacher_role($editUser['role'] ?? '');

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование профиля - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-user-edit text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Редактирование профиля</h1>
                    <p class="text-sm text-gray-600"><?php echo $editUser['full_name']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php?tab=users" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-arrow-left"></i>
                    Назад
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-4xl mx-auto p-6">
        
        <?php if ($success): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Основная информация -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-user-circle mr-2"></i>
                        Основная информация
                    </h2>
                    
                    <form method="POST">
                        <div class="space-y-4">
                            
                            <!-- Логин -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Логин <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($editUser['username']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            
                            <!-- ФИО -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    ФИО <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($editUser['full_name']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            
                            <!-- Роль - ДИНАМИЧЕСКИ ИЗ ТАБЛИЦЫ -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Роль <span class="text-red-500">*</span>
                                </label>
                                <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    <?php foreach ($roles as $roleOption): ?>
                                        <option value="<?php echo htmlspecialchars($roleOption['role_code']); ?>" 
                                                <?php echo $editUser['role'] === $roleOption['role_code'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($roleOption['role_name_ru']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($departmentHeadColumnsAvailable): ?>
                            <!-- Заведующий отделением -->
                            <div id="departmentHeadBlock" class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <label class="flex items-start gap-3 text-sm font-medium text-gray-700">
                                    <input type="checkbox"
                                           id="isDepartmentHead"
                                           name="is_department_head"
                                           value="1"
                                           class="mt-1"
                                           <?= ($isDepartmentHeadChecked && $isTeacherSelected) ? 'checked' : '' ?>
                                           <?= $isTeacherSelected ? '' : 'disabled' ?>>
                                    <span>
                                        Назначить преподавателя заведующим отделением
                                        <span class="block text-xs text-gray-500 font-normal mt-1">
                                            На главной странице edu ему будут видны все студенты из групп выбранного отделения.
                                            В HR-Аналитике будет доступна статистика только выбранного отделения.
                                        </span>
                                    </span>
                                </label>

                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Отделение</label>
                                    <select name="head_department_id"
                                            id="headDepartmentId"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            <?= ($isDepartmentHeadChecked && $isTeacherSelected) ? '' : 'disabled' ?>>
                                        <option value="">Выберите отделение</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= (int)$dept['id'] ?>" <?= $selectedHeadDepartmentId === (int)$dept['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['department_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!$departments): ?>
                                        <p class="text-xs text-red-600 mt-2">Отделения не найдены. Сначала добавьте отделение в разделе кабинетов/отделений.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($practiceDirectorColumnAvailable): ?>
                            <!-- Заместитель директора по практике -->
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <label class="flex items-start gap-3 text-sm font-medium text-gray-700">
                                    <input type="checkbox"
                                           id="isPracticeDirector"
                                           name="is_practice_director"
                                           value="1"
                                           class="mt-1"
                                           <?= $isPracticeDirectorChecked ? 'checked' : '' ?>>
                                    <span>
                                        Назначить заместителем директора по практике
                                        <span class="block text-xs text-gray-500 font-normal mt-1">
                                            В HR-Аналитике пользователь будет видеть статистику по всем отделениям колледжа и карточку диаграммы.
                                            Эта роль не совмещается с заведующим отделением.
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <?php endif; ?>

                            <?php if ($pccMethodistColumnsAvailable): ?>
                            <!-- Председатель ПЦК -->
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <label class="flex items-start gap-3 text-sm font-medium text-gray-700">
                                    <input type="checkbox"
                                           id="isPccHead"
                                           name="is_pcc_head"
                                           value="1"
                                           class="mt-1"
                                           <?= ($isPccHeadChecked && $isTeacherSelected) ? 'checked' : '' ?>
                                           <?= $isTeacherSelected ? '' : 'disabled' ?>>
                                    <span>
                                        Назначить преподавателя председателем ПЦК
                                        <span class="block text-xs text-gray-500 font-normal mt-1">
                                            Председатель ПЦК, сможет назначать преподавателей на предметы РУПл и делать тарификационную ведомость.
                                        </span>
                                    </span>
                                </label>
                            </div>
                            
                            <!-- Методист -->
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <label class="flex items-start gap-3 text-sm font-medium text-gray-700">
                                    <input type="checkbox"
                                           id="isMethodist"
                                           name="is_methodist"
                                           value="1"
                                           class="mt-1"
                                           <?= ($isMethodistChecked && $isTeacherSelected) ? 'checked' : '' ?>
                                           <?= $isTeacherSelected ? '' : 'disabled' ?>>
                                    <span>
                                        Назначить преподавателя методистом
                                        <span class="block text-xs text-gray-500 font-normal mt-1">
                                            Методист может работать с РУП отправленными преподавателями.
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Должность -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Должность <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="position" value="<?php echo htmlspecialchars($editUser['position']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            
                            <!-- Новый пароль -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Новый пароль
                                    <span class="text-gray-500 font-normal">(оставьте пустым, если не хотите менять)</span>
                                </label>
                                <input type="password" name="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Оставьте пустым для сохранения текущего">
                            </div>
                            
                            <!-- Кнопки -->
                            <div class="flex gap-3 pt-4">
                                <button type="submit" class="flex-1 bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition font-medium">
                                    <i class="fas fa-save mr-2"></i>
                                    Сохранить изменения
                                </button>
                                <a href="admin_dashboard.php?tab=users" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition font-medium text-center">
                                    Отмена
                                </a>
                            </div>
                            
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Боковая панель -->
            <div class="space-y-6">
                
                <!-- Информация о пользователе -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2"></i>
                        Информация
                    </h3>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600">ID пользователя</p>
                            <p class="text-lg font-semibold text-gray-800">#<?php echo $editUser['id']; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Дата создания</p>
                            <p class="text-lg font-semibold text-gray-800">
                                <?php echo date('d.m.Y', strtotime($editUser['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Текущая роль</p>
                            <p class="text-lg font-semibold text-gray-800">
                                <?php echo $currentRoleName; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Статистика пользователя -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar mr-2"></i>
                        Статистика
                    </h3>
                    
                    <?php
                    // Статистика для учителя и других ролей с правом создавать заявки
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ?");
                    $stmt->execute([$userId]);
                    $totalRequests = $stmt->fetchColumn();
                    
                    if ($totalRequests > 0) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'completed'");
                        $stmt->execute([$userId]);
                        $completedRequests = $stmt->fetchColumn();
                    ?>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <span class="text-sm text-gray-700">Всего заявок</span>
                                <span class="font-bold text-blue-600"><?php echo $totalRequests; ?></span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <span class="text-sm text-gray-700">Завершено</span>
                                <span class="font-bold text-green-600"><?php echo $completedRequests; ?></span>
                            </div>
                        </div>
                    <?php 
                    // Статистика для системотехника
                    } else {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE assigned_to = ?");
                        $stmt->execute([$userId]);
                        $assignedRequests = $stmt->fetchColumn();
                        
                        if ($assignedRequests > 0) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE assigned_to = ? AND status = 'completed'");
                            $stmt->execute([$userId]);
                            $completedRequests = $stmt->fetchColumn();
                        ?>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                    <span class="text-sm text-gray-700">Назначено заявок</span>
                                    <span class="font-bold text-blue-600"><?php echo $assignedRequests; ?></span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                    <span class="text-sm text-gray-700">Завершено</span>
                                    <span class="font-bold text-green-600"><?php echo $completedRequests; ?></span>
                                </div>
                            </div>
                        <?php 
                        } else {
                            echo '<p class="text-sm text-gray-600">Пока нет активности</p>';
                        }
                    }
                    ?>
                </div>
                
                <!-- Опасная зона -->
                <?php if ($editUser['id'] != $user['id']): ?>
                <div class="bg-red-50 rounded-lg shadow-md p-6 border-2 border-red-200">
                    <h3 class="text-lg font-bold text-red-800 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Опасная зона
                    </h3>
                    <p class="text-sm text-red-600 mb-4">
                        Удаление пользователя нельзя отменить!
                    </p>
                    <form method="POST" action="delete_user.php" onsubmit="return confirm('Удалить пользователя? Это действие нельзя отменить!')">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
                        <button type="submit" class="block w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-center font-medium">
                            <i class="fas fa-trash mr-2"></i>
                            Удалить пользователя
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
            </div>
            
        </div>
        
    </div>
    
<?php if ($departmentHeadColumnsAvailable || $pccMethodistColumnsAvailable || $practiceDirectorColumnAvailable): ?>
<script>
(function () {
    const roleSelect = document.querySelector('select[name="role"]');
    const headCheckbox = document.getElementById('isDepartmentHead');
    const practiceCheckbox = document.getElementById('isPracticeDirector');
    
    const departmentSelect = document.getElementById('headDepartmentId');

    const pccCheckbox = document.getElementById('isPccHead');
    const methodistCheckbox = document.getElementById('isMethodist');
    
    const teacherCodes = ['teacher', '3', 'преподаватель', 'препод', 'технолог'];

    function isTeacherRole(value) {
        return teacherCodes.includes(String(value || '').trim().toLowerCase());
    }

    function syncDepartmentHeadControls() {
        if (!roleSelect || !headCheckbox || !departmentSelect) return;
        const teacher = isTeacherRole(roleSelect.value);
        headCheckbox.disabled = !teacher;
        if (!teacher) {
            headCheckbox.checked = false;
        }
        departmentSelect.disabled = !teacher || !headCheckbox.checked;
        if (departmentSelect.disabled) {
            departmentSelect.value = '';
        }
    }

    function syncHrScopeChoice(changed) {
        if (changed === 'department' && headCheckbox && headCheckbox.checked && practiceCheckbox) {
            practiceCheckbox.checked = false;
        }
        if (changed === 'practice' && practiceCheckbox && practiceCheckbox.checked && headCheckbox) {
            headCheckbox.checked = false;
        }
        syncDepartmentHeadControls();
    }

    function syncPccMethodistControls() {
        if (!roleSelect) return;
        const teacher = isTeacherRole(roleSelect.value);
        if (pccCheckbox) {
            pccCheckbox.disabled = !teacher;
            if (!teacher) pccCheckbox.checked = false;
        }
        if (methodistCheckbox) {
            methodistCheckbox.disabled = !teacher;
            if (!teacher) methodistCheckbox.checked = false;
        }
    }

    roleSelect && roleSelect.addEventListener('change', syncDepartmentHeadControls);
    roleSelect && roleSelect.addEventListener('change', syncPccMethodistControls);
    headCheckbox && headCheckbox.addEventListener('change', () => syncHrScopeChoice('department'));
    practiceCheckbox && practiceCheckbox.addEventListener('change', () => syncHrScopeChoice('practice'));
    syncHrScopeChoice();
    syncDepartmentHeadControls();
    syncPccMethodistControls();
})();
</script>
<?php endif; ?>


</body>
</html>
