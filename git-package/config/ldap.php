<?php
// config/ldap.php - Конфигурация LDAP/Active Directory для домена shc.local

// LDAP настройки для домена shc.local
define('LDAP_ENABLED', true); // Включить/выключить LDAP аутентификацию
define('LDAP_SERVER', 'ldap://172.12.0.16'); // IP контроллера домена
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
    ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);
    
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
        return false;
    }
    
    $ldap = ldapConnect();
    if (!$ldap) {
        return false;
    }
    
    try {
        // Вариант 1: Если нужен bind служебной учеткой для поиска
        if (!empty(LDAP_BIND_DN)) {
            $bind = @ldap_bind($ldap, LDAP_BIND_DN, LDAP_BIND_PASSWORD);
            if (!$bind) {
                error_log("LDAP: Ошибка bind служебной учетки");
                ldap_close($ldap);
                return false;
            }
        }
        
        // Поиск пользователя
        $filter = sprintf(LDAP_USER_FILTER, ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $search = @ldap_search($ldap, LDAP_BASE_DN, $filter, ['cn', 'mail', 'displayName', 'sAMAccountName', 'givenName', 'sn']);
        
        if (!$search) {
            error_log("LDAP: Пользователь $username не найден");
            ldap_close($ldap);
            return false;
        }
        
        $entries = ldap_get_entries($ldap, $search);
        
        if ($entries['count'] === 0) {
            error_log("LDAP: Пользователь $username не найден в домене shc.local");
            ldap_close($ldap);
            return false;
        }
        
        // Получаем DN пользователя
        $userDn = $entries[0]['dn'];
        
        // Пытаемся аутентифицировать пользователя
        $userBind = @ldap_bind($ldap, $userDn, $password);
        
        if (!$userBind) {
            error_log("LDAP: Неверный пароль для пользователя $username");
            ldap_close($ldap);
            return false;
        }
        
        // Успешная аутентификация - извлекаем данные
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
            'email' => $entries[0]['mail'][0] ?? '',
            'ldap_dn' => $userDn
        ];
        
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
