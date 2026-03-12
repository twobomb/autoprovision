<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

$id = $_GET['id'] ?? 0;
$phone = $database->get('phones', '*', ['id' => $id]);
if (!$phone) {
    header('Location: phones.php');
    exit;
}

// Получаем активный профиль
$activeProfile = $database->get('profiles', '*', ['is_active' => 1]);
if (!$activeProfile) {
    $error = 'Нет активного профиля. Создайте и активируйте профиль.';
}

// Получаем все поля активного профиля
$profileFields = [];
if ($activeProfile) {
    $profileFields = $database->select('profile_fields', '*', [
        'profile_id' => $activeProfile['id'],
        'ORDER' => ['sort_order' => 'ASC', 'id' => 'ASC']
    ]);
}

// Получаем переопределения для этого телефона
$overrides = $database->select('phone_overrides', '*', ['phone_id' => $id]);
$overrideMap = [];
foreach ($overrides as $o) {
    $overrideMap[$o['field_key']] = $o['override_value'];
}

// Обработка сохранения переопределений
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Сохраняем изменения для обязательных полей
    foreach ($profileFields as $field) {
        $key = $field['field_key'];
        $value = $_POST['field_' . $key] ?? null;
        if ($value !== null) {
            // Если поле было изменено (не совпадает со значением профиля) или уже есть переопределение
            $profileValue = $field['field_value'];
            if ($value != $profileValue || isset($overrideMap[$key])) {
                // Сохраняем или обновляем переопределение
                if ($value == $profileValue && isset($overrideMap[$key])) {
                    // Если значение стало как в профиле, удаляем переопределение
                    $database->delete('phone_overrides', ['phone_id' => $id, 'field_key' => $key]);
                } else {
                    // Вставляем или обновляем
                    $database->replace('phone_overrides', [
                        'phone_id' => $id,
                        'field_key' => $key,
                        'override_value' => $value
                    ]);
                }
            }
        }
    }

    // Обработка добавления нового поля для переопределения
    if (!empty($_POST['new_field_key']) && !empty($_POST['new_field_value'])) {
        $newKey = $_POST['new_field_key'];
        $newValue = $_POST['new_field_value'];
        // Проверим, что такой ключ существует в профиле
        $fieldExists = $database->has('profile_fields', [
            'profile_id' => $activeProfile['id'],
            'field_key' => $newKey
        ]);
        if ($fieldExists) {
            $database->replace('phone_overrides', [
                'phone_id' => $id,
                'field_key' => $newKey,
                'override_value' => $newValue
            ]);
        }
    }

    header('Location: phone.php?id=' . $id);
    exit;
}

// Разделяем поля на обязательные и нет
$requiredFields = array_filter($profileFields, function($f) { return $f['is_required']; });
$optionalFields = array_filter($profileFields, function($f) { return !$f['is_required']; });

// Для удобства составим список всех ключей из профиля, которые уже переопределены
$overriddenKeys = array_keys($overrideMap);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Настройки телефона: <?= htmlspecialchars($phone['mac']) ?></title>

    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <link rel="stylesheet" href="/css/fontawesome-free-6.7.2-web/css/all.min.css">
    <link href="/css/Inter-4.1/web/inter.css" rel="stylesheet">
    <link href="/css/select2.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="phones.php">Телефоны</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($phone['mac']) ?></li>
        </ol>
    </nav>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php else: ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Информация о телефоне</h5>
            </div>
            <div class="card-body">
                <p><strong>MAC:</strong> <?= htmlspecialchars($phone['mac']) ?></p>
                <p><strong>IP:</strong> <?= htmlspecialchars($phone['ip'] ?: '-') ?></p>
                <p><strong>Последнее обращение:</strong> <?= $phone['updated_at'] ?></p>
                <p><strong>Шаблон:</strong>
                    <?php
                    if ($phone['template_id']) {
                        $tpl = $database->get('phone_templates', 'name', ['id' => $phone['template_id']]);
                        echo htmlspecialchars($tpl);
                    } else {
                        echo 'Авто (по User-Agent)';
                    }
                    ?>
                </p>
                <p><strong>Подразделение:</strong>
                    <?php
                    if ($phone['department_id']) {
                        $dept = $database->get('departments', 'name', ['id' => $phone['department_id']]);
                        echo htmlspecialchars($dept);
                    } else {
                        echo '-';
                    }
                    ?>
                </p>
            </div>
        </div>

        <form method="post">
            <!-- Обязательные поля -->
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Обязательные поля (требуют переопределения)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($requiredFields)): ?>
                        <p class="text-muted">Нет обязательных полей в профиле.</p>
                    <?php else: ?>
                        <?php foreach ($requiredFields as $field):
                            $currentValue = $overrideMap[$field['field_key']] ?? $field['field_value'];
                            ?>
                            <div class="mb-3">
                                <label for="field_<?= $field['field_key'] ?>" class="form-label">
                                    <?= htmlspecialchars($field['field_name']) ?> (<?= $field['field_key'] ?>)
                                </label>
                                <input type="text" class="form-control" id="field_<?= $field['field_key'] ?>"
                                       name="field_<?= $field['field_key'] ?>"
                                       value="<?= htmlspecialchars($currentValue) ?>">
                                <div class="form-text">Значение по умолчанию: <?= htmlspecialchars($field['field_value']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Необязательные поля (уже переопределённые) -->
            <?php
            $overriddenOptional = array_filter($optionalFields, function($f) use ($overriddenKeys) {
                return in_array($f['field_key'], $overriddenKeys);
            });
            ?>
            <?php if (!empty($overriddenOptional)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Переопределённые необязательные поля</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($overriddenOptional as $field):
                            $currentValue = $overrideMap[$field['field_key']];
                            ?>
                            <div class="mb-3">
                                <label for="field_<?= $field['field_key'] ?>" class="form-label">
                                    <?= htmlspecialchars($field['field_name']) ?> (<?= $field['field_key'] ?>)
                                </label>
                                <input type="text" class="form-control" id="field_<?= $field['field_key'] ?>"
                                       name="field_<?= $field['field_key'] ?>"
                                       value="<?= htmlspecialchars($currentValue) ?>">
                                <div class="form-text">Значение по умолчанию: <?= htmlspecialchars($field['field_value']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Добавление нового поля для переопределения -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Добавить поле для переопределения</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <select class="form-select select2" id="new_field_key" name="new_field_key">
                                <option value="">-- Выберите поле --</option>
                                <?php foreach ($optionalFields as $field):
                                    if (!in_array($field['field_key'], $overriddenKeys)): ?>
                                        <option value="<?= $field['field_key'] ?>">
                                            <?= htmlspecialchars($field['field_name']) ?> (<?= $field['field_key'] ?>)
                                        </option>
                                    <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="new_field_value" name="new_field_value" placeholder="Новое значение">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Добавить</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <button type="submit" class="btn btn-success">Сохранить все изменения</button>
                <a href="phones.php" class="btn btn-secondary">Назад к списку</a>
            </div>
        </form>

    <?php endif; ?>
</div>

<script src="/css/bootstrap-5.1.3-dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/jquery-3.6.0.min.js"></script>
<script src="/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%'
        });
    });
</script>
</body>
</html>