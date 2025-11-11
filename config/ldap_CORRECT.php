<?php
// config/ldap.php - Конфигурация LDAP/Active Directory для домена shc.local

// ════════════════════════════════════════════════════════════════
// ОСНОВНЫЕ НАСТРОЙКИ LDAP
// ════════════════════════════════════════════════════════════════

define('LDAP_ENABLED', true); // Включить LDAP аутентификацию

// ✅ ИСПРАВЛЕНО: Правильные параметры для домена shc.local
define('LDAP_HOST', 'ldap://192.168.30.1'); // IP контроллера домена (из nslookup)
define('LDAP_SERVER', 'ldap://192.168.30.1'); // Дубликат для совместимости
define('LDAP_PORT', 389); // Стандартный порт LDAP

define('LDAP_BASE_DN', 'DC=shc,DC=local'); // Base DN для домена shc.local
define('LDAP_DOMAIN', 'SHC'); // NetBIOS имя домена (ЗАГЛАВНЫМИ!)

define('LDAP_USER_FILTER', '(sAMAccountName=%s)'); // Фильтр поиска пользователя

// Настройки для служебной учетки (если требуется)
// Оставьте пустыми если не нужна
define('LDAP_BIND_DN', ''); 
define('LDAP_BIND_PASSWORD', '');

// Дополнительные настройки
define('LDAP_USE_TLS', false); // TLS отключен
define('LDAP_TIMEOUT', 10); // Таймаут подключения

// ════════════════════════════════════════════════════════════════
// ФУНКЦИИ LDAP
// ════════════════════════════════════════════════════════════════

/**
 * Подключение к LDAP серверу
 */
function ldapConnect() {
    if (!LDAP_ENABLED) {
        return false;
    }
    
    $ldap = @ldap_connect(LDAP_HOST, LDAP_PORT);
    
    if (!$ldap) {
        error_log("LDAP: Не удалось подключиться к серверу " . LDAP_HOST);
        return false;
    }
    
    // Настройки LDAP протокола
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, LDAP_TIMEOUT);
    
    return $ldap;
}

/**
 * Аутентификация через LDAP
 * 
 * @param string $username Имя пользователя (без домена)
 * @param string $password Пароль
 * @return array|false Данные пользователя или false
 */
function ldapAuthenticate($username, $password) {
    if (!LDAP_ENABLED || empty($username) || empty($password)) {
        error_log("LDAP: Пустой логин или пароль");
        return false;
    }
    
    $ldap = ldapConnect();
    if (!$ldap) {
        error_log("LDAP: Не удалось подключиться");
        return false;
    }
    
    try {
        // ✅ ИСПРАВЛЕНО: Правильные форматы логина для домена shc.local
        $loginFormats = [
            $username . '@shc.local',                           // user@shc.local (основной)
            'SHC\\' . $username,                                // SHC\user (альтернативный)
            strtoupper($username) . '@shc.local',               // USER@shc.local
            'CN=' . $username . ',CN=Users,' . LDAP_BASE_DN    // DN формат
        ];
        
        $bindSuccess = false;
        
        foreach ($loginFormats as $loginFormat) {
            error_log("LDAP: Попытка bind с форматом: $loginFormat");
            
            $bind = @ldap_bind($ldap, $loginFormat, $password);
            
            if ($bind) {
                error_log("LDAP: Bind успешен с форматом: $loginFormat");
                $bindSuccess = true;
                break;
            } else {
                $error = ldap_error($ldap);
                error_log("LDAP: Bind не удался для $loginFormat: $error");
            }
        }
        
        if (!$bindSuccess) {
            error_log("LDAP: Все форматы логина не сработали для пользователя: $username");
            ldap_close($ldap);
            return false;
        }
        
        // Успешная аутентификация - получаем данные пользователя
        error_log("LDAP: Поиск данных пользователя $username");
        
        $filter = sprintf(LDAP_USER_FILTER, ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $search = @ldap_search(
            $ldap, 
            LDAP_BASE_DN, 
            $filter, 
            ['cn', 'mail', 'displayName', 'sAMAccountName', 'givenName', 'sn', 'userPrincipalName']
        );
        
        if (!$search) {
            error_log("LDAP: Поиск пользователя не удался: " . ldap_error($ldap));
            // Bind был успешен, возвращаем минимальные данные
            ldap_close($ldap);
            return [
                'username' => strtolower($username),
                'full_name' => $username,
                'email' => ''
            ];
        }
        
        $entries = ldap_get_entries($ldap, $search);
        
        if ($entries['count'] === 0) {
            error_log("LDAP: Пользователь не найден в каталоге (но bind был успешен)");
            ldap_close($ldap);
            return [
                'username' => strtolower($username),
                'full_name' => $username,
                'email' => ''
            ];
        }
        
        // Получаем полное имя из разных полей AD
        $fullName = '';
        if (isset($entries[0]['displayname'][0])) {
            $fullName = $entries[0]['displayname'][0];
        } elseif (isset($entries[0]['cn'][0])) {
            $fullName = $entries[0]['cn'][0];
        } elseif (isset($entries[0]['givenname'][0]) && isset($entries[0]['sn'][0])) {
            $fullName = $entries[0]['givenname'][0] . ' ' . $entries[0]['sn'][0];
        } else {
            $fullName = $username;
        }
        
        $userData = [
            'username' => strtolower($username),
            'full_name' => $fullName,
            'email' => $entries[0]['mail'][0] ?? ''
        ];
        
        error_log("LDAP: Успешная авторизация пользователя $username (ФИО: $fullName)");
        
        ldap_close($ldap);
        return $userData;
        
    } catch (Exception $e) {
        error_log("LDAP Exception: " . $e->getMessage());
        if ($ldap) {
            ldap_close($ldap);
        }
        return false;
    }
}

/**
 * Проверка доступности LDAP сервера
 */
function ldapCheckConnection() {
    $ldap = ldapConnect();
    if ($ldap) {
        // Пробуем анонимный bind для проверки доступности
        $bind = @ldap_bind($ldap);
        ldap_close($ldap);
        return $bind !== false;
    }
    return false;
}

/**
 * Получение информации о пользователе из AD (без аутентификации)
 * Требует служебную учетку в LDAP_BIND_DN
 */
function ldapGetUserInfo($username) {
    if (!LDAP_ENABLED || empty($username)) {
        return false;
    }
    
    $ldap = ldapConnect();
    if (!$ldap) {
        return false;
    }
    
    try {
        // Если есть служебная учетка - используем её
        if (!empty(LDAP_BIND_DN) && !empty(LDAP_BIND_PASSWORD)) {
            $bind = @ldap_bind($ldap, LDAP_BIND_DN, LDAP_BIND_PASSWORD);
        } else {
            // Иначе пробуем анонимный bind
            $bind = @ldap_bind($ldap);
        }
        
        if (!$bind) {
            ldap_close($ldap);
            return false;
        }
        
        $filter = sprintf(LDAP_USER_FILTER, ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $search = @ldap_search($ldap, LDAP_BASE_DN, $filter);
        
        if (!$search) {
            ldap_close($ldap);
            return false;
        }
        
        $entries = ldap_get_entries($ldap, $search);
        ldap_close($ldap);
        
        if ($entries['count'] === 0) {
            return false;
        }
        
        return $entries[0];
        
    } catch (Exception $e) {
        error_log("LDAP getUserInfo Exception: " . $e->getMessage());
        if ($ldap) {
            ldap_close($ldap);
        }
        return false;
    }
}

// ════════════════════════════════════════════════════════════════
// ИНСТРУКЦИЯ ПО ИСПОЛЬЗОВАНИЮ
// ════════════════════════════════════════════════════════════════

/*
ИСПОЛЬЗОВАНИЕ:

1. Аутентификация:
   ------------------
   $userData = ldapAuthenticate('username', 'password');
   if ($userData) {
       echo "Добро пожаловать, " . $userData['full_name'];
   } else {
       echo "Неверный логин или пароль";
   }

2. Проверка подключения:
   -----------------------
   if (ldapCheckConnection()) {
       echo "LDAP сервер доступен";
   } else {
       echo "LDAP сервер недоступен";
   }

3. Получение информации о пользователе:
   -------------------------------------
   $userInfo = ldapGetUserInfo('username');
   if ($userInfo) {
       print_r($userInfo);
   }

ФОРМАТ ЛОГИНА:
--------------
Пользователи должны вводить ТОЛЬКО username без домена:
✓ username
✗ username@shc.local (не нужно)
✗ SHC\username (не нужно)

Функция ldapAuthenticate сама попробует разные форматы.

ЛОГИ:
-----
Все действия записываются в error_log PHP:
- Windows: C:\Windows\Temp\php-7.x.x_errors.log
- Или в файл указанный в php.ini

Для отладки смотрите логи!
*/
?>
