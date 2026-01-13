<!-- 
==================================================================
ГОТОВАЯ НАВИГАЦИЯ С ПРОВЕРКОЙ ПРАВ ДЛЯ admin_dashboard.php
==================================================================
Замените этим кодом строки 357-387 в вашем admin_dashboard.php
==================================================================
-->

<!-- Вкладки с проверкой прав доступа -->
<div class="mb-6">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-4 overflow-x-auto">
            
            <!-- Главная (доступна всем администраторам) -->
            <a href="?tab=dashboard" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'dashboard' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-home"></i>
                <?php echo t('main'); ?>
            </a>
            
            <!-- Заявки (если есть любое право, связанное с заявками) -->
            <?php if (hasAnyPermission($pdo, ['can_create_request', 'can_approve_request', 'can_work_on_request', 'can_view_all_requests'])): ?>
            <a href="?tab=requests" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-file-alt"></i>
                <?php echo t('requests'); ?>
            </a>
            <?php endif; ?>
            
            <!-- Пользователи (только если есть can_manage_users) -->
            <?php if (hasPermission($pdo, 'can_manage_users')): ?>
            <a href="?tab=users" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-users"></i>
                <?php echo t('users'); ?> (<?php echo $totalUsers; ?>)
            </a>
            <?php endif; ?>
            
            <!-- Роли (только если есть can_manage_users) -->
            <?php if (hasPermission($pdo, 'can_manage_users')): ?>
            <a href="?tab=roles" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'roles' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-user-tag"></i>
                <?php echo t('roles'); ?> (<?php echo count($allRoles); ?>)
            </a>
            <?php endif; ?>
            
            <!-- Отделения и кабинеты (только если есть can_manage_cabinets) -->
            <?php if (hasPermission($pdo, 'can_manage_cabinets')): ?>
            <a href="?tab=cabinets" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'cabinets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-building"></i>
                <?php echo t('departments_cabinets'); ?>
            </a>
            <?php endif; ?>
            
            <!-- Логи (доступны всем администраторам) -->
            <a href="?tab=logs" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'logs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-chart-line"></i>
                <?php echo t('logs'); ?>
            </a>
            
        </nav>
    </div>
</div>
