<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

// Подключаем PhpSpreadsheet (убедитесь, что установлен через composer)
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Функция для рекурсивного получения всех ключей полей профиля (включая родителей)
function getAllProfileFieldKeys($database, $profileId, &$keys = []) {
    $profile = $database->get('profiles', '*', ['id' => $profileId]);
    if (!$profile) return $keys;

    $fields = $database->select('profile_fields', 'field_key', ['profile_id' => $profileId]);
    $keys = array_merge($keys, $fields);

    if ($profile['parent_id']) {
        getAllProfileFieldKeys($database, $profile['parent_id'], $keys);
    }
    return array_unique($keys);
}

// Функция валидации MAC
function validateMac($mac) {
    // Очищаем от возможных разделителей
    $clean = preg_replace('/[^a-fA-F0-9]/', '', $mac);
    if (strlen($clean) !== 12) return false;
    if (!preg_match('/^[a-fA-F0-9]{12}$/', $clean)) return false;
    // Возвращаем отформатированный MAC с двоеточиями
    //return strtolower(implode(':', str_split($clean, 2)));
    return $clean;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: import_phones.php');
    exit;
}

$profileId = $_POST['profile_id'] ?? 0;
$departmentId = $_POST['department_id'] ?? 0;
$updateExisting = isset($_POST['update_existing']);

if (!$profileId || !$departmentId) {
    $_SESSION['import_errors'] = ['Не выбран профиль или подразделение.'];
    header('Location: import_phones.php');
    exit;
}

// Проверяем загруженный файл
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['import_errors'] = ['Ошибка загрузки файла.'];
    header('Location: import_phones.php');
    exit;
}

$tmpFile = $_FILES['file']['tmp_name'];
$errors = [];

try {
    $spreadsheet = IOFactory::load($tmpFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    if (count($rows) < 2) {
        $errors[] = 'Файл не содержит данных (нужна хотя бы одна строка с заголовками и одна строка с данными).';
    }

    // Получаем заголовки (первая строка)
    $headers = array_shift($rows);
    $headers = array_map('trim', $headers);

    // Проверяем наличие обязательных колонок
    $macIndex = array_search('MAC', $headers);
    $nameIndex = array_search('NAME', $headers);
    if ($macIndex === false || $nameIndex === false) {
        $errors[] = 'В первой строке должны быть колонки MAC и NAME.';
    }

    // Получаем допустимые ключи полей из выбранного профиля
    $allowedKeys = getAllProfileFieldKeys($database, $profileId);
    $allowedKeys[] = 'MAC';
    $allowedKeys[] = 'NAME';

    // Проверяем, что все остальные заголовки (кроме MAC и NAME) есть среди допустимых ключей
    $extraColumns = [];
    foreach ($headers as $index => $header) {
        if ($index == $macIndex || $index == $nameIndex || $header == "") continue;
        if (!in_array($header, $allowedKeys)) {
            $extraColumns[] = $header;
        }
    }
    if (!empty($extraColumns)) {
        $errors[] = 'Следующие колонки не являются допустимыми ключами полей в выбранном профиле: ' . implode(', ', $extraColumns);
    }

    // Если есть ошибки, выводим их и прерываем
    if (!empty($errors)) {
        $_SESSION['import_errors'] = $errors;
        header('Location: import_phones.php');
        exit;
    }

    // Подготавливаем данные для импорта
    $importData = [];
    $rowNum = 2; // для сообщений об ошибках (начиная со второй строки)
    $macValues = [];

    foreach ($rows as $row) {
        $rowData = [];
        $isEmpty = true;
        foreach ($row as $cell) {
            if (!empty($cell)) {
                $isEmpty = false;
                break;
            }
        }
        if ($isEmpty) continue; // пропускаем пустые строки

        // Извлекаем MAC и NAME
        $macRaw = $row[$macIndex] ?? '';
        $name = $row[$nameIndex] ?? '';

        // Валидация MAC
        $macValid = validateMac($macRaw);
        if (!$macValid) {
            $errors[] = "Строка $rowNum: некорректный MAC-адрес '$macRaw'.";
        } else {
            // Проверка дубликатов MAC в файле
            if (in_array($macValid, $macValues)) {
                $errors[] = "Строка $rowNum: MAC-адрес '$macValid' дублируется в файле.";
            }
            $macValues[] = $macValid;
        }

        // Проверка NAME (необязательно, но можно добавить)
        if (empty($name)) {
            $errors[] = "Строка $rowNum: не заполнено рабочее место (NAME).";
        }

        // Собираем переопределения для остальных колонок
        $overrides = [];
        foreach ($headers as $index => $header) {
            if ($index == $macIndex || $index == $nameIndex || $header == "") continue;
            $value = $row[$index] ?? '';
            if ($value !== '') {
                $overrides[$header] = $value;
            }
        }

        $importData[] = [
            'mac' => $macValid,
            'name' => $name,
            'overrides' => $overrides
        ];
        $rowNum++;
    }

    if (!empty($errors)) {
        $_SESSION['import_errors'] = $errors;
        header('Location: import_phones.php');
        exit;
    }

    // Начинаем транзакцию
    $database->pdo->beginTransaction();

    $imported = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($importData as $data) {
        $mac = $data['mac'];
        $name = $data['name'];
        $overrides = $data['overrides'];

        // Проверяем, существует ли телефон
        $existing = $database->get('phones', '*', ['mac' => $mac]);

        if ($existing) {
            if ($updateExisting) {
                // Обновляем
                $database->update('phones', [
                    'workplace' => $name,
                    'profile_id' => $profileId,
                    'department_id' => $departmentId
                ], ['id' => $existing['id']]);
                $phoneId = $existing['id'];
                $updated++;
            } else {
                $skipped++;
                continue;
            }
        } else {
            // Создаём новый телефон
            $database->insert('phones', [
                'mac' => $mac,
                'workplace' => $name,
                'profile_id' => $profileId,
                'department_id' => $departmentId
            ]);
            $phoneId = $database->id();
            $imported++;
        }

        // Удаляем старые переопределения для этого телефона
        $database->delete('phone_overrides', ['phone_id' => $phoneId]);

        // Вставляем новые переопределения
        foreach ($overrides as $key => $value) {
            $database->insert('phone_overrides', [
                'phone_id' => $phoneId,
                'field_key' => $key,
                'override_value' => $value
            ]);
        }

        // Если телефон был в unknown_requests, удаляем его оттуда
        $database->delete('unknown_requests', ['mac' => $mac]);
    }

    $database->pdo->commit();

    $message = "Импорт завершён. Добавлено новых: $imported, обновлено: $updated, пропущено (уже есть): $skipped.";
    $_SESSION['import_success'] = $message;

} catch (Exception $e) {
    if ($database->pdo->inTransaction()) {
        $database->pdo->rollBack();
    }
    $_SESSION['import_errors'] = ['Ошибка обработки файла: ' . $e->getMessage()];
}

header('Location: import_phones.php');
exit;