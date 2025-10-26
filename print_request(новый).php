<?php
// print_request.php - Страница для печати заявки

require_once 'config/db.php';
require_once 'includes/auth.php';

requireLogin();

$user = getCurrentUser();
$requestId = $_GET['id'] ?? null;

if (!$requestId) {
    header('Location: index.php');
    exit();
}

// Получение заявки
$stmt = $pdo->prepare("
    SELECT r.*, 
           creator.full_name as creator_name,
           creator.position as creator_position
    FROM requests r
    LEFT JOIN users creator ON r.created_by = creator.id
    WHERE r.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: index.php');
    exit();
}

// Проверка прав доступа
$canView = false;
if ($user['role'] === 'director' || $user['role'] === 'technician') {
    $canView = true;
} elseif ($user['role'] === 'teacher' && $request['created_by'] == $user['id']) {
    $canView = true;
}

if (!$canView) {
    header('Location: index.php');
    exit();
}

$createdDate = date('d', strtotime($request['created_at']));
$createdMonth = date('m', strtotime($request['created_at']));
$createdYear = date('Y', strtotime($request['created_at']));

$months = [
    '01' => 'января', '02' => 'февраля', '03' => 'марта',
    '04' => 'апреля', '05' => 'мая', '06' => 'июня',
    '07' => 'июля', '08' => 'августа', '09' => 'сентября',
    '10' => 'октября', '11' => 'ноября', '12' => 'декабря'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка №<?php echo $request['id']; ?> - <?php echo $request['request_type'] === 'repair' ? 'Ремонт и обслуживание' : 'Заявка'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 14pt;
            line-height: 1.5;
            padding: 2cm;
            background: white;
        }
        
        .document {
            max-width: 21cm;
            margin: 0 auto;
        }
        
        .header {
            text-align: right;
            margin-bottom: 30px;
        }
        
        .header p {
            margin: 3px 0;
        }
        
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 16pt;
            margin: 30px 0 20px;
            text-transform: uppercase;
        }
        
        .subtitle {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 30px;
        }
        
        .content {
            text-align: justify;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 400px;
            min-height: 20px;
            padding: 0 5px;
        }
        
        .long-underline {
            border-bottom: 1px solid black;
            display: block;
            min-height: 60px;
            padding: 5px;
            margin: 10px 0;
        }
        
        .footer {
            margin-top: 50px;
        }
        
        .footer-item {
            margin: 15px 0;
        }
        
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid black;
            min-width: 200px;
            margin-left: 20px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            @page {
                size: A4;
                margin: 2cm;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #4F46E5;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-family: Arial, sans-serif;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #4338CA;
        }
        
        .back-button {
            position: fixed;
            top: 70px;
            right: 20px;
            padding: 12px 24px;
            background: #6B7280;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-family: Arial, sans-serif;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            text-decoration: none;
            display: inline-block;
            z-index: 1000;
        }
        
        .back-button:hover {
            background: #4B5563;
        }
    </style>
</head>
<body>
    
    <button onclick="window.print()" class="print-button no-print">
        🖨️ Печать
    </button>
    
    <a href="view_request.php?id=<?php echo $request['id']; ?>" class="back-button no-print">
        ← Назад
    </a>
    
    <div class="document">
        
        <?php if ($request['request_type'] === 'repair'): ?>
            <!-- ФОРМА ДЛЯ РЕМОНТА И ОБСЛУЖИВАНИЯ -->
            
            <div class="header">
                <p><strong>Директору СВГТК</strong></p>
                <p><strong>Темирбулатовой А.А</strong></p>
                <p>от <?php echo $request['creator_name']; ?></p>
                <p>(<?php echo $request['creator_position']; ?>)</p>
            </div>
            
            <div class="title">
                ЗАЯВКА
            </div>
            
            <div class="subtitle">
                на обслуживание и ремонт техники
            </div>
            
            <div class="content">
                <p>Прошу направить неисправное оборудование на диагностику и ремонт.</p>
            </div>
            
            <div class="section-title">
                Описание неисправности:
            </div>
            
            <div class="content">
                <div class="long-underline">
                    <?php echo nl2br(htmlspecialchars($request['equipment_type'])); ?>
                </div>
                <p style="font-size: 10pt; color: #666; font-style: italic;">
                    [тип техники: системный блок, монитор, принтер и т.д.]
                </p>
            </div>
            
            <div class="content">
                <div class="long-underline">
                    <?php echo nl2br(htmlspecialchars($request['problem_description'])); ?>
                </div>
                <p style="font-size: 10pt; color: #666; font-style: italic;">
                    [краткое описание проблемы: не включается, не печатает, шумит вентилятор, повреждён кабель и т.п.]
                </p>
            </div>
            
            <div class="content" style="margin-top: 30px;">
                <p><strong>Место установки техники:</strong> кабинет № <?php echo htmlspecialchars($request['cabinet']); ?>.</p>
            </div>
            
            <div class="content">
                <p><strong>Инвентарный номер (при наличии):</strong> 
                    <span class="underline" style="min-width: 200px;">
                        <?php echo $request['inventory_number'] ? htmlspecialchars($request['inventory_number']) : ''; ?>
                    </span>
                </p>
            </div>
            
            <div class="footer">
                <div class="footer-item">
                    <strong>Дата:</strong> «<?php echo $createdDate; ?>» <?php echo $months[$createdMonth]; ?> <?php echo $createdYear; ?> г.
                </div>
                
                <div class="footer-item">
                    <strong>Подпись:</strong> <span class="signature-line"></span>
                </div>
            </div>
            
        <?php elseif ($request['request_type'] === 'software'): ?>
            <!-- ФОРМА ДЛЯ УСТАНОВКИ ПО -->
            
            <div class="header">
                <p><strong>Директору СВГТК</strong></p>
                <p><strong>Темирбулатовой А.А</strong></p>
                <p>от <?php echo $request['creator_name']; ?></p>
                <p>(<?php echo $request['creator_position']; ?>)</p>
            </div>
            
            <div class="title">
                ЗАЯВКА
            </div>
            
            <div class="subtitle">
                на установку программного обеспечения
            </div>
            
            <div class="content">
                <p>Прошу установить программное обеспечение для учебного процесса.</p>
            </div>
            
            <div class="section-title">
                Наименование ПО:
            </div>
            
            <div class="content">
                <div class="long-underline">
                    <?php echo nl2br(htmlspecialchars($request['software_name'])); ?>
                </div>
            </div>
            
            <div class="section-title">
                Обоснование необходимости:
            </div>
            
            <div class="content">
                <div class="long-underline">
                    <?php echo nl2br(htmlspecialchars($request['justification'])); ?>
                </div>
            </div>
            
            <div class="content" style="margin-top: 30px;">
                <p><strong>Кабинет:</strong> № <?php echo htmlspecialchars($request['cabinet']); ?>.</p>
            </div>
            
            <?php if ($request['students_list']): 
                $students = json_decode($request['students_list'], true);
            ?>
            <div class="section-title">
                Список студентов (<?php echo count($students); ?>):
            </div>
            
            <div class="content">
                <ol style="margin-left: 30px;">
                    <?php foreach ($students as $student): ?>
                        <li><?php echo htmlspecialchars($student); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <div class="footer-item">
                    <strong>Дата:</strong> «<?php echo $createdDate; ?>» <?php echo $months[$createdMonth]; ?> <?php echo $createdYear; ?> г.
                </div>
                
                <div class="footer-item">
                    <strong>Подпись:</strong> <span class="signature-line"></span>
                </div>
            </div>
            
        <?php elseif ($request['request_type'] === '1c_database'): ?>
            <!-- ФОРМА ДЛЯ БАЗЫ ДАННЫХ 1С -->
            
            <div class="header">
                <p><strong>Директору СВГТК</strong></p>
                <p><strong>Темирбулатовой А.А</strong></p>
                <p>от <?php echo $request['creator_name']; ?></p>
                <p>(<?php echo $request['creator_position']; ?>)</p>
            </div>
            
            <div class="title">
                ЗАЯВКА
            </div>
            
            <div class="subtitle">
                на создание базы данных 1С
            </div>
            
            <div class="content">
                <p>Прошу создать учебную базу данных 1С для проведения занятий.</p>
            </div>
            
            <div class="section-title">
                Назначение базы данных:
            </div>
            
            <div class="content">
                <div class="long-underline">
                    <?php echo nl2br(htmlspecialchars($request['database_purpose'])); ?>
                </div>
            </div>
            
            <div class="content" style="margin-top: 30px;">
                <p><strong>Кабинет:</strong> № <?php echo htmlspecialchars($request['cabinet']); ?>.</p>
            </div>
            
            <?php if ($request['students_list']): 
                $students = json_decode($request['students_list'], true);
            ?>
            <div class="section-title">
                Список студентов (<?php echo count($students); ?>):
            </div>
            
            <div class="content">
                <ol style="margin-left: 30px;">
                    <?php foreach ($students as $student): ?>
                        <li><?php echo htmlspecialchars($student); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <div class="footer-item">
                    <strong>Дата:</strong> «<?php echo $createdDate; ?>» <?php echo $months[$createdMonth]; ?> <?php echo $createdYear; ?> г.
                </div>
                
                <div class="footer-item">
                    <strong>Подпись:</strong> <span class="signature-line"></span>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
    
    <script>
        // Автоматически открыть диалог печати при загрузке (опционально)
        // window.onload = function() {
        //     setTimeout(() => window.print(), 500);
        // };
    </script>
    
</body>
</html>
