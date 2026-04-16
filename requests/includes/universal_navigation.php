<!-- 
=================================================================
УНИВЕРСАЛЬНАЯ НАВИГАЦИЯ ДЛЯ ВСЕХ РОЛЕЙ
=================================================================
Используйте этот код в любом dashboard
Навигация автоматически адаптируется под права пользователя!
=================================================================
-->

<!-- Вкладки с автоматической проверкой прав -->
<div class="mb-6">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-4 overflow-x-auto">
            
            <!-- ГЛАВНАЯ - доступна всем авторизованным пользователям -->
            <?php if (hasPermission('view_dashboard')): ?>
            <a href="?tab=dashboard" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'dashboard' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-home"></i>
                <?php echo t('main'); ?>
            </a>
            <?php endif; ?>
            
            <!-- СОЗДАТЬ ЗАЯВКУ - для всех кто может создавать (учитель, директор, админ) -->
            <?php if (hasPermission('create_request')): ?>
            <a href="create_request.php" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 border-transparent text-gray-500 hover:text-gray-700">
                <i class="fas fa-plus-circle"></i>
                <?php echo t('create_request'); ?>
            </a>
            <?php endif; ?>
            
            <!-- МОИ ЗАЯВКИ - для обычных пользователей (учитель, директор) -->
            <?php if (hasPermission('view_own_requests')): ?>
            <a href="?tab=my_requests" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'my_requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-file-alt"></i>
                <?php echo t('my_requests'); ?>
            </a>
            <?php endif; ?>
            
            <!-- ВСЕ ЗАЯВКИ - для админа и директора -->
            <?php if (hasPermission('view_all_requests')): ?>
            <a href="?tab=all_requests" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'all_requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-list"></i>
                <?php echo t('all_requests'); ?>
                <?php if (isset($totalRequests)): ?>
                    <span class="bg-indigo-100 text-indigo-600 px-2 py-1 rounded-full text-xs"><?php echo $totalRequests; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- НАЗНАЧЕННЫЕ МНЕ - для техников -->
            <?php if (hasPermission('view_assigned_requests')): ?>
            <a href="?tab=assigned_requests" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'assigned_requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-tasks"></i>
                <?php echo t('assigned_to_me'); ?>
                <?php if (isset($assignedCount)): ?>
                    <span class="bg-green-100 text-green-600 px-2 py-1 rounded-full text-xs"><?php echo $assignedCount; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- СТАТИСТИКА - для админа и директора -->
            <?php if (hasPermission('view_statistics')): ?>
            <a href="?tab=statistics" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'statistics' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-chart-bar"></i>
                <?php echo t('statistics'); ?>
            </a>
            <?php endif; ?>
            
            <!-- ПОЛЬЗОВАТЕЛИ - только для админа -->
            <?php if (hasPermission('manage_users')): ?>
            <a href="?tab=users" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-users"></i>
                <?php echo t('users'); ?>
                <?php if (isset($totalUsers)): ?>
                    (<?php echo $totalUsers; ?>)
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- РОЛИ - только для админа -->
            <?php if (hasPermission('manage_roles')): ?>
            <a href="?tab=roles" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'roles' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-user-tag"></i>
                <?php echo t('roles'); ?>
            </a>
            <?php endif; ?>
            
            <!-- КАБИНЕТЫ - для админа и директора -->
            <?php if (hasPermission('manage_cabinets')): ?>
            <a href="?tab=cabinets" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'cabinets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-building"></i>
                <?php echo t('departments_cabinets'); ?>
            </a>
            <?php endif; ?>
            
            <!-- ЛОГИ - для админа -->
            <?php if (hasPermission('view_logs')): ?>
            <a href="?tab=logs" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'logs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-history"></i>
                <?php echo t('logs'); ?>
            </a>
            <?php endif; ?>
            
        </nav>
    </div>
</div>

<!-- 
=================================================================
ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ В РАЗНЫХ РОЛЯХ:
=================================================================

ADMIN увидит:
- Главная
- Создать заявку
- Мои заявки
- Все заявки
- Статистика
- Пользователи
- Роли
- Кабинеты
- Логи

DIRECTOR увидит:
- Главная
- Создать заявку ✅ (теперь может!)
- Мои заявки ✅ (свои заявки)
- Все заявки (для контроля)
- Статистика
- Кабинеты

TEACHER увидит:
- Главная
- Создать заявку
- Мои заявки

TECHNICIAN увидит:
- Главная
- Назначенные мне

=================================================================
-->