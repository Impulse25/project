<?php
// create_request.php - Создание заявки

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireLogin();

$user = getCurrentUser();

// Проверка права на создание заявок
$stmt = $pdo->prepare("SELECT can_create_request FROM roles WHERE role_code = ?");
$stmt->execute([$user['role']]);
$permission = $stmt->fetch();

// Если роль не найдена или нет прав - используем фоллбэк для старых ролей
if (!$permission) {
    // Для старых ролей без записи в таблице roles
    if ($user['role'] !== 'teacher') {
        header('Location: unified_dashboard.php');
        exit();
    }
} else {
    // Для новых ролей проверяем права
    if (!$permission['can_create_request']) {
        header('Location: unified_dashboard.php');
        exit();
    }
}

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: create_request.php');
    exit();
}

$success = '';
$error = '';

// Список кабинетов больше не нужен - пользователь вводит вручную
// $stmt = $pdo->query("SELECT cabinet_number FROM cabinets ORDER BY cabinet_number ASC");
// $cabinets = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Обработка создания заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestType = $_POST['request_type'];
    $fullName = $_POST['full_name'];
    $position = $_POST['position'];
    $cabinet = $_POST['cabinet'];
    $priority = $_POST['priority'] ?? 'normal'; // Получаем приоритет
    
    try {
        if ($requestType === 'repair') {
            // Заявка на ремонт
            $equipmentType = $_POST['equipment_type'];
            $inventoryNumber = $_POST['inventory_number'];
            $description = $_POST['description'];
            
            $stmt = $pdo->prepare("INSERT INTO requests (request_type, created_by, full_name, position, cabinet, equipment_type, inventory_number, description, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestType, $user['id'], $fullName, $position, $cabinet, $equipmentType, $inventoryNumber, $description, $priority]);
            
        } elseif ($requestType === 'software') {
            // Заявка на установку ПО
            $computerInventory = $_POST['computer_inventory'];
            $softwareList = $_POST['software_list'];
            $justification = $_POST['justification'];
            
            $stmt = $pdo->prepare("INSERT INTO requests (request_type, created_by, full_name, position, cabinet, computer_inventory, software_list, justification, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestType, $user['id'], $fullName, $position, $cabinet, $computerInventory, $softwareList, $justification, $priority]);
            
        } elseif ($requestType === '1c_database') {
            // Заявка на создание БД 1С
            $groupNumber = $_POST['group_number'];
            $databasePurpose = $_POST['database_purpose'];
            
            // Сбор списка студентов
            $students = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'student_') === 0 && !empty($value)) {
                    $students[] = $value;
                }
            }
            $studentsList = json_encode($students, JSON_UNESCAPED_UNICODE);
            
            $stmt = $pdo->prepare("INSERT INTO requests (request_type, created_by, full_name, position, cabinet, group_number, database_purpose, students_list, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestType, $user['id'], $fullName, $position, $cabinet, $groupNumber, $databasePurpose, $studentsList, $priority]);
        }
        
        $success = 'Заявка успешно отправлена!';
        
    } catch (PDOException $e) {
        $error = 'Ошибка при создании заявки: ' . $e->getMessage();
    }
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('create_request'); ?> - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-low { 
            background: #f3f4f6; 
            color: #374151;
            border: 2px solid #9ca3af;
        }
        .priority-normal { 
            background: #dbeafe; 
            color: #1e40af;
            border: 2px solid #3b82f6;
        }
        .priority-high { 
            background: #fed7aa; 
            color: #c2410c;
            border: 2px solid #f97316;
            font-weight: 700;
        }
        .priority-urgent { 
            background: #fecaca; 
            color: #991b1b;
            border: 2px solid #dc2626;
            font-weight: 700;
        }
        .priority-card {
            transition: all 0.2s ease;
        }
        .priority-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-cog text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600"><?php echo t('create_request'); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <a href="?lang=ru" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Рус</a>
                    <a href="?lang=kk" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Қаз</a>
                </div>
                <a href="javascript:history.back()" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo t('back'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-4xl mx-auto p-6">
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
                <a href="javascript:history.back()" class="underline ml-2">Вернуться к заявкам</a>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6"><?php echo t('new_request'); ?></h2>
            
            <form method="POST" id="requestForm">
                
                <!-- Выбор типа заявки -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3"><?php echo t('request_type'); ?></label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="request_type" value="repair" class="hidden request-type-radio" checked>
                            <div class="request-type-card p-4 border-2 border-red-500 bg-red-50 rounded-lg text-center transition">
                                <i class="fas fa-wrench text-3xl text-red-600 mb-2"></i>
                                <div class="font-medium"><?php echo t('repair'); ?></div>
                                <div class="text-xs text-gray-600 mt-1"><?php echo t('repair_maintenance'); ?></div>
                            </div>
                        </label>
                        
                        <label class="cursor-pointer">
                            <input type="radio" name="request_type" value="software" class="hidden request-type-radio">
                            <div class="request-type-card p-4 border-2 border-gray-200 rounded-lg text-center transition hover:border-gray-300">
                                <i class="fas fa-laptop-code text-3xl text-blue-600 mb-2"></i>
                                <div class="font-medium"><?php echo t('software'); ?></div>
                                <div class="text-xs text-gray-600 mt-1"><?php echo t('software_installation'); ?></div>
                            </div>
                        </label>
                        
                        <label class="cursor-pointer">
                            <input type="radio" name="request_type" value="1c_database" class="hidden request-type-radio">
                            <div class="request-type-card p-4 border-2 border-gray-200 rounded-lg text-center transition hover:border-gray-300">
                                <i class="fas fa-database text-3xl text-purple-600 mb-2"></i>
                                <div class="font-medium"><?php echo t('1c_database'); ?></div>
                                <div class="text-xs text-gray-600 mt-1"><?php echo t('database_1c'); ?></div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Общие поля -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('full_name'); ?></label>
                        <input type="text" name="full_name" required value="<?php echo $user['full_name']; ?>" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('position'); ?></label>
                        <input type="text" name="position" required value="<?php echo $user['position']; ?>" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo t('cabinet'); ?> <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="cabinet" 
                        required 
                        placeholder="<?php echo t('cabinet_placeholder'); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        maxlength="100"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        <?php echo t('cabinet_hint'); ?>
                    </p>
                </div>
                
                <!-- НОВОЕ ПОЛЕ: Приоритет заявки -->
                <div class="mb-6 p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border-2 border-gray-300">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-flag mr-2"></i>
                        <?php echo t('priority_label'); ?>
                    </label>
                    <div class="grid grid-cols-4 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="priority" value="low" class="hidden priority-radio">
                            <div class="priority-card p-3 border-2 border-gray-300 bg-gray-100 rounded-lg text-center transition hover:border-gray-400">
                                <i class="fas fa-angle-double-down text-3xl text-gray-600 mb-1"></i>
                                <div class="text-sm font-bold text-gray-700"><?php echo t('low_priority'); ?></div>
                                <div class="text-xs text-gray-600 mt-1"><?php echo t('not_urgent'); ?></div>
                            </div>
                        </label>
                        
                        <label class="cursor-pointer">
                            <input type="radio" name="priority" value="normal" class="hidden priority-radio" checked>
                            <div class="priority-card p-3 border-2 border-blue-500 bg-blue-50 rounded-lg text-center transition">
                                <i class="fas fa-minus text-3xl text-blue-600 mb-1"></i>
                                <div class="text-sm font-bold text-blue-700"><?php echo t('normal_priority'); ?></div>
                                <div class="text-xs text-blue-600 mt-1"><?php echo t('standard'); ?></div>
                            </div>
                        </label>
                        
                        <label class="cursor-pointer">
                            <input type="radio" name="priority" value="high" class="hidden priority-radio">
                            <div class="priority-card p-3 border-2 border-orange-400 bg-orange-100 rounded-lg text-center transition hover:border-orange-500">
                                <i class="fas fa-angle-double-up text-3xl text-orange-600 mb-1"></i>
                                <div class="text-sm font-bold text-orange-700"><?php echo t('high_priority'); ?></div>
                                <div class="text-xs text-orange-600 mt-1"><?php echo t('important'); ?></div>
                            </div>
                        </label>
                        
                        <label class="cursor-pointer">
                            <input type="radio" name="priority" value="urgent" class="hidden priority-radio">
                            <div class="priority-card p-3 border-2 border-red-500 bg-red-100 rounded-lg text-center transition hover:border-red-600">
                                <i class="fas fa-exclamation-triangle text-3xl text-red-600 mb-1"></i>
                                <div class="text-sm font-bold text-red-700"><?php echo t('urgent_priority'); ?></div>
                                <div class="text-xs text-red-600 mt-1"><?php echo t('very_urgent'); ?></div>
                            </div>
                        </label>
                    </div>
                    <p class="text-xs text-gray-600 mt-3 bg-white p-2 rounded border border-gray-200">
                        <i class="fas fa-info-circle mr-1 text-blue-500"></i>
                        <?php echo t('priority_processing_order'); ?>
                    </p>
                </div>
                
                <!-- Поля для ремонта -->
                <div id="repairFields" class="form-section">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('equipment_type'); ?></label>
                        <select name="equipment_type" class="w-full px-4 py-2 border rounded-lg">
                            <option value="Системный блок"><?php echo t('system_unit'); ?></option>
                            <option value="Монитор"><?php echo t('monitor'); ?></option>
                            <option value="Принтер"><?php echo t('printer'); ?></option>
                            <option value="Проектор"><?php echo t('projector'); ?></option>
                            <option value="Сканер"><?php echo t('scanner'); ?></option>
                            <option value="Другое"><?php echo t('other'); ?></option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('inventory_number'); ?> (при наличии)</label>
                        <input type="text" name="inventory_number" class="w-full px-4 py-2 border rounded-lg" placeholder="СБ-2024-001">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('description'); ?></label>
                        <textarea name="description" rows="4" class="w-full px-4 py-2 border rounded-lg" placeholder="Опишите проблему: не включается, не печатает, шумит вентилятор и т.п."></textarea>
                    </div>
                </div>
                
                <!-- Поля для установки ПО -->
                <div id="softwareFields" class="form-section hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('inventory_number'); ?> компьютера</label>
                        <input type="text" name="computer_inventory" class="w-full px-4 py-2 border rounded-lg" placeholder="ПК-2024-001">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('software_list'); ?></label>
                        <textarea name="software_list" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Укажите программы и версии, например:&#10;- AutoCAD 2024&#10;- Adobe Photoshop CC"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('justification'); ?></label>
                        <textarea name="justification" rows="2" class="w-full px-4 py-2 border rounded-lg" placeholder="Укажите причину установки (производственная необходимость, учебный процесс и т.д.)"></textarea>
                    </div>
                </div>
                
                <!-- Поля для 1С -->
                <div id="databaseFields" class="form-section hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('group_number'); ?></label>
                        <input type="text" name="group_number" class="w-full px-4 py-2 border rounded-lg" placeholder="ИС-21">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('database_purpose'); ?></label>
                        <textarea name="database_purpose" rows="2" class="w-full px-4 py-2 border rounded-lg" placeholder="Организация учебного процесса, выполнение лабораторных работ по дисциплинам"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo t('students_list'); ?> (количество: <span id="studentCount">4</span>)</label>
                        <input type="range" id="studentCountSlider" min="1" max="30" value="4" class="w-full mb-2">
                        <div id="studentsList">
                            <!-- Генерируется JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3 pt-4 border-t">
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        <?php echo t('send'); ?>
                    </button>
                    <a href="javascript:history.back()" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition">
                        <?php echo t('cancel'); ?>
                    </a>
                </div>
                
            </form>
        </div>
        
    </div>
    
    <script>
        // Переключение типа заявки
        document.querySelectorAll('.request-type-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.request-type-card').forEach(card => {
                    card.classList.remove('border-red-500', 'bg-red-50', 'border-blue-500', 'bg-blue-50', 'border-purple-500', 'bg-purple-50');
                    card.classList.add('border-gray-200');
                });
                
                const selectedCard = this.parentElement.querySelector('.request-type-card');
                if (this.value === 'repair') {
                    selectedCard.classList.add('border-red-500', 'bg-red-50');
                } else if (this.value === 'software') {
                    selectedCard.classList.add('border-blue-500', 'bg-blue-50');
                } else if (this.value === '1c_database') {
                    selectedCard.classList.add('border-purple-500', 'bg-purple-50');
                }
                selectedCard.classList.remove('border-gray-200');
                
                document.querySelectorAll('.form-section').forEach(section => {
                    section.classList.add('hidden');
                });
                
                if (this.value === 'repair') {
                    document.getElementById('repairFields').classList.remove('hidden');
                } else if (this.value === 'software') {
                    document.getElementById('softwareFields').classList.remove('hidden');
                } else if (this.value === '1c_database') {
                    document.getElementById('databaseFields').classList.remove('hidden');
                }
            });
        });
        
        // Переключение приоритета
        document.querySelectorAll('.priority-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.priority-card').forEach(card => {
                    card.classList.remove('border-gray-500', 'bg-gray-200', 'border-blue-500', 'bg-blue-50', 'border-orange-500', 'bg-orange-200', 'border-red-500', 'bg-red-200');
                    card.classList.add('border-gray-300', 'bg-gray-100');
                });
                
                const selectedCard = this.parentElement.querySelector('.priority-card');
                selectedCard.classList.remove('border-gray-300', 'bg-gray-100');
                
                if (this.value === 'low') {
                    selectedCard.classList.add('border-gray-500', 'bg-gray-200');
                } else if (this.value === 'normal') {
                    selectedCard.classList.add('border-blue-500', 'bg-blue-50');
                } else if (this.value === 'high') {
                    selectedCard.classList.add('border-orange-500', 'bg-orange-200');
                } else if (this.value === 'urgent') {
                    selectedCard.classList.add('border-red-500', 'bg-red-200');
                }
            });
        });
        
        // Динамический список студентов
        const slider = document.getElementById('studentCountSlider');
        const countDisplay = document.getElementById('studentCount');
        const studentsList = document.getElementById('studentsList');
        
        function updateStudentsList() {
            const count = slider.value;
            countDisplay.textContent = count;
            studentsList.innerHTML = '';
            
            for (let i = 1; i <= count; i++) {
                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'student_' + i;
                input.className = 'w-full px-4 py-2 border rounded-lg mb-2';
                input.placeholder = i + '. ФИО студента';
                studentsList.appendChild(input);
            }
        }
        
        slider.addEventListener('input', updateStudentsList);
        updateStudentsList();
    </script>
    
</body>
</html>