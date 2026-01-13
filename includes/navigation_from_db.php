<!-- 
=================================================================
УНИВЕРСАЛЬНАЯ НАВИГАЦИЯ С ПРАВАМИ ИЗ БД
=================================================================
Вкладки автоматически появляются/исчезают в зависимости от
галочек в админке!

Используйте в любом dashboard:
<?php include 'includes/navigation_from_db.php'; ?>
=================================================================
-->

<div class="mb-6">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-4 overflow-x-auto">
            
            <!-- ГЛАВНАЯ - всегда показываем -->
            <a href="?tab=dashboard" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'dashboard' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-home"></i>
                <?php echo t('main'); ?>
            </a>
            
            <!-- СОЗДАТЬ ЗАЯВКУ - если есть can_create_request -->
            <?php if (hasPermission('can_create_request')): ?>
            <a href="create_request.php" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 border-transparent text-green-600 hover:text-green-700">
                <i class="fas fa-plus-circle"></i>
                <?php echo t('create_request'); ?>
            </a>
            <?php endif; ?>
            
            <!-- МОИ ЗАЯВКИ - если can_create_request (но НЕ can_view_all_requests) -->
            <?php if (hasPermission('can_create_request') && !hasPermission('can_view_all_requests')): ?>
            <a href="?tab=my_requests" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'my_requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-file-alt"></i>
                <?php echo t('my_requests'); ?>
                <?php if (isset($myRequestsCount) && $myRequestsCount > 0): ?>
                    <span class="bg-indigo-100 text-indigo-600 px-2 py-1 rounded-full text-xs"><?php echo $myRequestsCount; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- ВСЕ ЗАЯВКИ - если can_view_all_requests -->
            <?php if (hasPermission('can_view_all_requests')): ?>
            <a href="?tab=requests" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-list"></i>
                <?php echo t('all_requests'); ?>
                <?php if (isset($totalRequests) && $totalRequests > 0): ?>
                    <span class="bg-indigo-100 text-indigo-600 px-2 py-1 rounded-full text-xs"><?php echo $totalRequests; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- НАЗНАЧЕННЫЕ МНЕ - если can_work_on_request (техники) -->
            <?php if (hasPermission('can_work_on_request') && !hasPermission('can_view_all_requests')): ?>
            <a href="?tab=assigned_requests" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'assigned_requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-tasks"></i>
                <?php echo t('assigned_to_me'); ?>
                <?php if (isset($assignedCount) && $assignedCount > 0): ?>
                    <span class="bg-green-100 text-green-600 px-2 py-1 rounded-full text-xs"><?php echo $assignedCount; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- СТАТИСТИКА - если can_view_all_requests или can_manage_users -->
            <?php if (hasPermission('can_view_all_requests') || hasPermission('can_manage_users')): ?>
            <a href="?tab=statistics" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'statistics' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-chart-bar"></i>
                <?php echo t('statistics'); ?>
            </a>
            <?php endif; ?>
            
            <!-- ПОЛЬЗОВАТЕЛИ - если can_manage_users -->
            <?php if (hasPermission('can_manage_users')): ?>
            <a href="?tab=users" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-users"></i>
                <?php echo t('users'); ?>
                <?php if (isset($totalUsers) && $totalUsers > 0): ?>
                    (<?php echo $totalUsers; ?>)
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- РОЛИ - если can_manage_users -->
            <?php if (hasPermission('can_manage_users')): ?>
            <a href="?tab=roles" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'roles' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-user-tag"></i>
                <?php echo t('roles'); ?>
            </a>
            <?php endif; ?>
            
            <!-- КАБИНЕТЫ - если can_manage_cabinets -->
            <?php if (hasPermission('can_manage_cabinets')): ?>
            <a href="?tab=cabinets" 
               class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 
                      <?php echo $tab === 'cabinets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-building"></i>
                <?php echo t('departments_cabinets'); ?>
            </a>
            <?php endif; ?>
            
            <!-- ЛОГИ - если can_manage_users (только админ) -->
            <?php if (hasPermission('can_manage_users')): ?>
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
КАК ЭТО РАБОТАЕТ:
=================================================================

1. ADMIN (все галочки включены):
   Видит: Главная, Создать, Все заявки, Статистика, Пользователи, Роли, Кабинеты, Логи

2. DIRECTOR (включить can_create_request + can_view_all_requests + can_manage_cabinets):
   Видит: Главная, Создать ✅, Все заявки, Статистика, Кабинеты

3. TEACHER (включить can_create_request):
   Видит: Главная, Создать, Мои заявки

4. TECHNICIAN (включить can_work_on_request):
   Видит: Главная, Назначенные мне

=================================================================
ЧТО ДЕЛАТЬ:
=================================================================

1. Откройте админку → Роли → Редактировать "Директор"
2. Поставьте галочку "Создавать заявки" (can_create_request)
3. Сохраните
4. Войдите как директор → вкладка "Создать заявку" появится автоматически!

=================================================================
-->
