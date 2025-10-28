# 🎓 СВГТК - Система управления заявками

Система управления заявками для Степногорского высшего технического колледжа с интеграцией LDAP/Active Directory.

## ✨ Возможности

- 🔐 **Гибридная авторизация**: LDAP (домен shc.local) + локальная БД
- 📊 **Полное логирование**: входы, выходы, неудачные попытки
- 👥 **Управление пользователями**: роли, права доступа
- 📝 **Система заявок**: создание, обработка, статистика
- 🌐 **Многоязычность**: Русский, Казахский
- 📈 **Статистика и отчёты**: детальная аналитика активности

## 🚀 Установка

### Требования

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Apache / Nginx
- PHP LDAP модуль (для доменной авторизации)

### Шаг 1: Клонирование репозитория

```bash
git clone https://github.com/ваш-username/svgtk-requests.git
cd svgtk-requests
```

### Шаг 2: Настройка базы данных

1. Создайте базу данных:
```sql
CREATE DATABASE svgtk_requests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Импортируйте SQL миграции:
```bash
mysql -u root -p svgtk_requests < migrations/migration_add_ldap_support.sql
mysql -u root -p svgtk_requests < migrations/migration_add_auth_type_to_logs.sql
```

3. Создайте файл `config/db.php`:
```php
<?php
$host = 'localhost';
$dbname = 'svgtk_requests';
$username = 'root';
$password = 'ваш_пароль';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
?>
```

### Шаг 3: Настройка LDAP (опционально)

Файл `config/ldap.php` уже настроен для домена **shc.local**.

Если ваш домен другой, измените:
```php
define('LDAP_SERVER', 'ldap://172.12.0.16'); // IP контроллера домена
define('LDAP_BASE_DN', 'dc=shc,dc=local');   // Base DN
```

Для отключения LDAP:
```php
define('LDAP_ENABLED', false);
```

### Шаг 4: Создание локального администратора

```bash
php setup_local_admin.php
```

**Данные по умолчанию:**
- Логин: `local_admin`
- Пароль: `Admin2025!`

⚠️ **ВАЖНО:** Смените пароль после первого входа!

### Шаг 5: Проверка установки

```bash
# Проверка LDAP подключения
php test_ldap.php

# Проверка логирования
php test_logging.php
```

## 📁 Структура проекта

```
svgtk-requests/
├── config/
│   ├── db.php              # Настройки БД (создайте вручную)
│   └── ldap.php            # Настройки LDAP/AD
├── includes/
│   ├── auth.php            # Система авторизации
│   └── language.php        # Многоязычность
├── migrations/
│   ├── migration_add_ldap_support.sql
│   └── migration_add_auth_type_to_logs.sql
├── admin_dashboard.php     # Админ-панель
├── admin_logs.php          # Страница логов
├── logout.php              # Выход из системы
├── index.php               # Страница входа
└── README.md
```

## 🔐 Система авторизации

### Гибридная аутентификация

Система поддерживает два типа авторизации:

1. **LDAP (Домен shc.local)**
   - Автоматическое создание пользователей из AD
   - Синхронизация ФИО
   - Роль по умолчанию: `teacher`

2. **Локальная БД**
   - Резервный доступ (local_admin)
   - Независимость от домена

### Приоритет авторизации

```
Попытка входа
    ↓
1. LDAP (если включен)
    ↓
    Успешно? → Вход через домен
    ↓
    Неудачно?
    ↓
2. Локальная БД
    ↓
    Найден? → Вход локально
    ↓
    Не найден? → Отказ
```

## 📊 Логирование

Все действия пользователей логируются в таблицу `user_logs`:

- ✅ Входы (успешные)
- ❌ Неудачные попытки входа
- 🚪 Выходы
- 📝 Тип авторизации (LDAP/Local)
- 🌐 IP адрес и User-Agent
- ⏰ Дата и время

### Просмотр логов

```sql
-- Последние 20 действий
SELECT * FROM user_logs ORDER BY created_at DESC LIMIT 20;

-- Все LDAP входы
SELECT * FROM user_logs WHERE auth_type = 'ldap' AND action = 'login';

-- Неудачные попытки за сегодня
SELECT * FROM user_logs 
WHERE success = 0 AND DATE(created_at) = CURDATE();
```

## 👥 Управление пользователями

### Роли по умолчанию

- **admin** - полный доступ
- **director** - просмотр всех заявок, статистика
- **teacher** - создание заявок
- **technician** - обработка заявок

### Назначение роли доменному пользователю

```sql
-- Сделать пользователя админом
UPDATE users 
SET role = 'admin' 
WHERE username = 'ivanov' AND auth_type = 'ldap';
```

### Создание локального пользователя

```sql
INSERT INTO users (username, password, full_name, role, auth_type, created_at)
VALUES (
    'testuser',
    '$2y$10$...',  -- password_hash('password', PASSWORD_DEFAULT)
    'Тестовый пользователь',
    'teacher',
    'local',
    NOW()
);
```

## 🔧 Настройка веб-сервера

### Apache (.htaccess)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?route=$1 [QSA,L]

# Защита конфигурации
<FilesMatch "^(db|ldap)\.php$">
    Require all denied
</FilesMatch>
```

### Nginx

```nginx
server {
    listen 80;
    server_name svgtk.local;
    root /var/www/svgtk-requests;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    location ~ ^/(config|includes)/.*\.php$ {
        deny all;
    }
}
```

## 🐛 Устранение проблем

### LDAP не подключается

```bash
# Проверьте сеть
ping 172.12.0.16
telnet 172.12.0.16 389

# Проверьте PHP модуль
php -m | grep ldap

# Установите если нет
sudo apt-get install php-ldap
sudo systemctl restart apache2
```

### Логи не пишутся

```bash
# Проверьте таблицу
mysql -u root -p -e "USE svgtk_requests; SHOW TABLES LIKE '%log%';"

# Проверьте права
mysql -u root -p -e "USE svgtk_requests; SHOW GRANTS FOR 'user'@'localhost';"
```

### Не могу войти

Используйте резервный доступ:
- Логин: `local_admin`
- Пароль: `Admin2025!`

## 📝 Разработка

### Добавление новых действий для логирования

```php
// В вашем коде
logUserAction(
    $pdo,
    $userId,
    $username,
    $fullName,
    $role,
    'custom_action',  // Название действия
    $authType,
    true              // Успешно или нет
);
```

### Создание новой роли

```sql
INSERT INTO roles (role_code, role_name_ru, role_name_kk, description)
VALUES ('manager', 'Менеджер', 'Менеджер', 'Управление процессами');
```

## 📄 Лицензия

Proprietary - Степногорский высший технический колледж

## 👨‍💻 Авторы

- Система управления заявками - СВГТК IT отдел
- LDAP интеграция - 2025

## 📞 Поддержка

По вопросам работы системы обращайтесь в IT отдел колледжа.

---

**Версия:** 2.0 (с LDAP и полным логированием)  
**Дата:** 2025-10-28  
**Домен:** shc.local
