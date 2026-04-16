<?php
// config/ldap.php - Конфигурация LDAP/Active Directory для домена shc.local

// LDAP настройки для домена shc.local
define('LDAP_ENABLED', true); // Включить/выключить LDAP аутентификацию
define('LDAP_SERVER', 'ldap://192.168.30.1'); // IP ОСНОВНОГО контроллера домена
define('LDAP_PORT', 389); // Порт LDAP
define('LDAP_BASE_DN', 'dc=shc,dc=local'); // Base DN для домена shc.local
define('LDAP_USER_FILTER', '(sAMAccountName=%s)'); // Фильтр поиска пользователя
define('LDAP_DOMAIN', 'shc'); // Домен для bind

// Настройки для bind (если требуется служебная учетка для поиска)
// Оставьте пустыми если не требуется
define('LDAP_BIND_DN', ''); // Например: 'cn=service_account,dc=shc,dc=local'
define('LDAP_BIND_PASSWORD', ''); // Пароль служебной учетки

/**
 * Подключение к LDAP серверу
 */
function ldapConnect() {
    if (!LDAP_ENABLED) {
        return false;
    }
    
    $ldap = @ldap_connect(LDAP_SERVER, LDAP_PORT);
    
    if (!$ldap) {
        error_log("LDAP: Не удалось подключиться к серверу " . LDAP_SERVER);
        return false;
    }
    
    // Настройки LDAP протокола
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 10);
    
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
        // Попробуем разные форматы логина
        $loginFormats = [
            $username . '@shc.local',                           // user@shc.local
            'shc\\' . $username,                                // shc\user
            'CN=' . $username . ',CN=Users,' . LDAP_BASE_DN    // DN формат
        ];
        
        $bindSuccess = false;
        $userDn = '';
        
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
            error_log("LDAP: Все форматы логина не сработали");
            ldap_close($ldap);
            return false;
        }
        
        // Успешный bind - ищем пользователя для получения данных
        $filter = sprintf(LDAP_USER_FILTER, ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $search = @ldap_search($ldap, LDAP_BASE_DN, $filter, ['cn', 'mail', 'displayName', 'sAMAccountName', 'givenName', 'sn']);
        
        if (!$search) {
            error_log("LDAP: Поиск пользователя не удался");
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
        
        error_log("LDAP: Успешная авторизация пользователя $username");
        
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
        ldap_close($ldap);
        return true;
    }
    return false;
}
?>
