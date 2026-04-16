<?php
// config/ldap.php - Конфигурация LDAP/Active Directory для домена shc.local

// ════════════════════════════════════════════════════════════════
// LDAP настройки для домена shc.local
// ════════════════════════════════════════════════════════════════

define('LDAP_ENABLED', true); // Включить/выключить LDAP аутентификацию

// ✅ ИСПРАВЛЕНО: Используем IP контроллера домена из nslookup
define('LDAP_HOST', 'ldap://192.168.30.1'); // IP контроллера домена svgtk-s1.shc.local
define('LDAP_SERVER', 'ldap://192.168.30.1'); // Дублируем для совместимости
define('LDAP_PORT', 389); // Порт LDAP

// ✅ ИСПРАВЛЕНО: Base DN для домена shc.local
define('LDAP_BASE_DN', 'DC=shc,DC=local'); // Base DN для домена shc.local

// ✅ ИСПРАВЛЕНО: Домен для аутентификации
define('LDAP_DOMAIN', 'SHC'); // NetBIOS имя домена (в верхнем регистре)

// Фильтр поиска пользователей
define('LDAP_USER_FILTER', '(sAMAccountName=%s)');

// Настройки для bind (если требуется служебная учетка для поиска)
// Оставьте пустыми если не требуется
define('LDAP_BIND_DN', ''); // Например: 'cn=service_account,dc=shc,dc=local'
define('LDAP_BIND_PASSWORD', ''); // Пароль служебной учетки

// Таймаут подключения
define('LDAP_TIMEOUT', 3);

// Использовать TLS
define('LDAP_USE_TLS', false);

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
 * @param string $username Имя пользователя
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
        // ✅ ИСПРАВЛЕНО: Попробуем разные форматы логина
        $loginFormats = [
            $username . '@shc.local',                           // user@shc.local
            'SHC\\' . $username,                                // SHC\user
            $username . '@' . LDAP_DOMAIN,                      // user@SHC
            'CN=' . $username . ',CN=Users,' . LDAP_BASE_DN    // DN формат
        ];
        
        $bindSuccess = false;
        
        foreach ($loginFormats as $loginFormat) {
            error_log("LDAP: Пробуем формат: $loginFormat");
            
            $bind = @ldap_bind($ldap, $loginFormat, $password);
            
            if ($bind) {
                error_log("LDAP: Bind успешен с форматом: $loginFormat");
                $bindSuccess = true;
                break;
            } else {
                $error = ldap_error($ldap);
                error_log("LDAP: Bind не удался ($loginFormat): $error");
            }
        }
        
        if (!$bindSuccess) {
            error_log("LDAP: Все форматы логина не сработали для пользователя: $username");
            ldap_close($ldap);
            return false;
        }
        
        // Успешный bind - ищем пользователя для получения данных
        $filter = sprintf(LDAP_USER_FILTER, ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        error_log("LDAP: Поиск пользователя с фильтром: $filter в " . LDAP_BASE_DN);
        
        $search = @ldap_search($ldap, LDAP_BASE_DN, $filter, ['cn', 'mail', 'displayName', 'sAMAccountName', 'givenName', 'sn']);
        
        if (!$search) {
            error_log("LDAP: Поиск пользователя не удался, ошибка: " . ldap_error($ldap));
            // Но bind прошел успешно, поэтому возвращаем минимальные данные
            ldap_close($ldap);
            return [
                'username' => strtolower($username),
                'full_name' => $username,
                'email' => ''
            ];
        }
        
        $entries = ldap_get_entries($ldap, $search);
        
        if ($entries['count'] === 0) {
            error_log("LDAP: Пользователь не найден в поиске, но bind был успешен");
            ldap_close($ldap);
            return [
                'username' => strtolower($username),
                'full_name' => $username,
                'email' => ''
            ];
        }
        
        // Формируем ФИО из разных полей AD
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
        if (isset($ldap) && $ldap) {
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
        // Попытка анонимного bind для проверки
        $bind = @ldap_bind($ldap);
        ldap_close($ldap);
        return $bind !== false;
    }
    return false;
}

// ════════════════════════════════════════════════════════════════
// ИНФОРМАЦИЯ О КОНФИГУРАЦИИ
// ════════════════════════════════════════════════════════════════

/*
ТЕКУЩИЕ НАСТРОЙКИ:
- LDAP Server: 192.168.30.1 (svgtk-s1.shc.local)
- Domain: shc.local (NetBIOS: SHC)
- Base DN: DC=shc,DC=local
- Port: 389

ФОРМАТЫ ЛОГИНА:
Система попробует несколько форматов:
1. username@shc.local
2. SHC\username
3. username@SHC
4. CN=username,CN=Users,DC=shc,DC=local

ТЕСТИРОВАНИЕ:
Откройте: http://172.12.0.16:8888/test_ldap.php
Для проверки работы LDAP

ЛОГИ:
Все сообщения записываются в error_log PHP
Путь к логу: C:\Windows\Temp\php-7.x.x_errors.log
*/
?>
