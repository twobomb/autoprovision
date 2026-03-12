<?php

use Medoo\Medoo;

require_once 'config/db.php';

/**
 * Парсит лог запроса автопровижена и возвращает MAC, модель и прошивку
 * (код из вашего файла, без изменений)
 */
function parseProvisioningRequest($requestData) {
    if (is_string($requestData)) {
        $data = @unserialize($requestData);
        if ($data === false) {
            $data = [];
        }
    } else {
        $data = (array) $requestData;
    }

    $result = [
        'mac'       => null,
        'model'     => null,
        'firmware'  => null,
        'ip'        => $data['ip'] ?? null,
        'timestamp' => $data['timestamp'] ?? null,
        'uri'       => $data['uri'] ?? null,
        'user_agent'=> $data['user_agent'] ?? null,
    ];

    $uri = $result['uri'];
    $ua  = $result['user_agent'];

    // --- 1. Ищем MAC ---
    $mac = null;

    if ($uri && preg_match('/([a-f0-9]{12})\.(cfg|txt)$/i', $uri, $matches)) {
        $mac = strtolower($matches[1]);
    }

    if (!$mac && $ua) {
        if (preg_match('/([a-f0-9]{2}[:-]){5}[a-f0-9]{2}/i', $ua, $matches)) {
            $mac = strtolower(str_replace([':', '-'], '', $matches[0]));
        }
        elseif (preg_match('/\b([a-f0-9]{12})\b/i', $ua, $matches)) {
            $mac = strtolower($matches[1]);
        }
    }

    $result['mac'] = $mac;

    // --- 2. Ищем модель и версию (из User-Agent) ---
    if ($ua) {
        if (preg_match('/(\d+(?:\.\d+){2,})/', $ua, $verMatches)) {
            $result['firmware'] = $verMatches[1];
            $modelPart = substr($ua, 0, strpos($ua, $verMatches[1]));
            $modelPart = trim($modelPart);
            $modelPart = preg_replace('/\s+[a-f0-9]{2}(?:[:\-][a-f0-9]{2}){5}$/i', '', $modelPart);
            $result['model'] = $modelPart ?: null;
        } else {
            $withoutMac = $ua;
            if ($mac) {
                $withoutMac = str_ireplace($mac, '', $ua);
                $withoutMac = preg_replace('/[a-f0-9]{2}(?:[:\-][a-f0-9]{2}){5}/i', '', $withoutMac);
            }
            $withoutMac = trim($withoutMac);
            if ($withoutMac) {
                $result['model'] = $withoutMac;
            }
        }
    }

    return $result;
}

/**
 * Рекурсивно собирает поля профиля с учётом наследования
 */
function getProfileFields($database, $profileId, &$result = []) {
    $profile = $database->get('profiles', '*', ['id' => $profileId]);
    if (!$profile) {
        return $result;
    }

    // Получаем поля текущего профиля
    $fields = $database->select('profile_fields', '*', ['profile_id' => $profileId]);
    foreach ($fields as $field) {
        // Если ключ ещё не определён (не был переопределён в дочернем профиле), добавляем
        if (!isset($result[$field['field_key']])) {
            $result[$field['field_key']] = [
                'value' => $field['field_value'],
                'is_required' => $field['is_required'],
                'name' => $field['field_name']
            ];
        }
    }

    // Рекурсивно обрабатываем родителя
    if ($profile['parent_id']) {
        getProfileFields($database, $profile['parent_id'], $result);
    }

    return $result;
}



// Логируем обращение
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$requestData = getFullRequest();

$parseData = parseProvisioningRequest($requestData);


$addData = "";
if(isset($_GET["test"])){
    $parseData['mac'] = $_GET["test"];
    $addData = "[Тестовое обращение]";
}

if (!is_null($parseData["model"]))
    $addData .= " Модель: " . $parseData["model"];
if (!is_null($parseData["firmware"]))
    $addData .= " Прошивка: " . $parseData["firmware"];

$macRaw = $parseData['mac']; // без разделителей
if (is_null($macRaw)) {
    $database->insert('logs', [
        'mac' => "-",
        'ip' => $ip,
        'user_agent' => $userAgent,
        'textinfo' => "MAC не был найден" . $addData,
        'request_info' => $requestData
    ]);
    http_response_code(400);
    die('MAC not provided');
}

// Извлекаем OUI (первые 6 символов)
$oui = strtoupper(substr($macRaw, 0, 6));

// Ищем шаблон по OUI
$template = $database->get('phone_templates', '*', ['oui' => $oui]);

if (!$template) {
    // Шаблон для производителя не найден
    $database->insert('logs', [
        'mac' => $macRaw,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'textinfo' => "Шаблон для OUI $oui не найден" . $addData,
        'request_info' => $requestData
    ]);
    http_response_code(404);
    die('No template for this manufacturer');
}

// Форматируем MAC для поиска в БД (с двоеточиями)
$macFormatted = implode(':', str_split($macRaw, 2));

// Ищем телефон
$phone = $database->get('phones', '*', ['mac' => $macRaw]);

if (!$phone && !isset($_GET["test"])) {
    // Телефон не зарегистрирован, но шаблон есть
    $database->insert('unknown_requests', [
        'mac' => $macFormatted,
        'oui' => $oui,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'template_id' => $template['id'],
        'created_at' => date('Y-m-d H:i:s')
    ], 'REPLACE'); // Третий параметр 'REPLACE' заставляет Medoo выполнить REPLACE INTO

    $database->insert('logs', [
        'mac' => $macFormatted,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'textinfo' => "Телефон не найден, но шаблон есть. Запись в unknown_requests." . $addData,
        'request_info' => $requestData
    ]);

    http_response_code(404);
    die('Phone not registered');
}

// Телефон найден, логируем успешное обращение
$database->insert('logs', [
    'mac' => $macFormatted,
    'ip' => $ip,
    'user_agent' => $userAgent,
    'request_info' => $requestData,
    'textinfo' => "УСПЕХ ".$addData,
]);

// Обновляем IP телефона
$database->update('phones', ['ip' => $ip], ['id' => $phone['id']]);
$database->update('phones', [
    'last_request' => Medoo::raw('CURRENT_TIMESTAMP')
], ['id' => $phone['id']]);

// Получаем все поля профиля с учётом наследования
$profileFields = getProfileFields($database, $phone['profile_id']);
$profileValues = [];
foreach ($profileFields as $key => $data) {
    $profileValues[$key] = $data['value'];
}

// Получаем переопределения для телефона
$overrides = $database->select('phone_overrides', '*', ['phone_id' => $phone['id']]);
foreach ($overrides as $o) {
    $profileValues[$o['field_key']] = $o['override_value'];
}

// Загружаем правила преобразования для текущего шаблона
$rules = $database->select('field_transform_rules', '*', [
    'template_id' => $template['id']
]);
$rulesByKey = [];
foreach ($rules as $rule) {
    $rulesByKey[$rule['field_key']] = [
        'type' => $rule['rule_type'],
        'data' => json_decode($rule['rule_data'], true)
    ];
}

// Применяем правила к значениям
foreach ($profileValues as $key => $value) {
    if (isset($rulesByKey[$key])) {
        $rule = $rulesByKey[$key];
        if ($rule['type'] === 'fixed') {
            // Фиксированное значение
            $profileValues[$key] = isset($rule['data']['fixed']) ? $rule['data']['fixed'] : $value;
        } elseif ($rule['type'] === 'map') {
            // Сопоставление
            $map = $rule['data'];
            if (isset($map[$value])) {
                $profileValues[$key] = $map[$value];
            }
        }
    }
}


// Выполняем замены в шаблоне
$content = $template['template_content'];
foreach ($profileValues as $key => $value) {
    $content = str_replace('%' . $key . '%', $value, $content);
}

// Отдаём результат
header('Content-Type: text/plain; charset=utf-8');
echo $content;