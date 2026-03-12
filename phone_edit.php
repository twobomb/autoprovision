<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';
require_once 'functions.php'; // для get_profile_fields_with_inheritance

$id = $_GET['id'] ?? 0;
$phone = null;
if ($id) {
    $phone = $database->get('phones', '*', ['id' => $id]);
    if (!$phone) {
        header('Location: phones.php');
        exit;
    }
}

// Получаем список подразделений для выпадающего списка
$departments = $database->select('departments', ['id', 'name'], ['ORDER' => ['sort_id' => 'ASC', 'name' => 'ASC']]);

// Получаем список профилей для выпадающего списка (с иерархией)
$allProfiles = $database->select('profiles', '*', ['ORDER' => 'name']);
$profilesTree = buildProfilesTree($allProfiles); // функция строит дерево с отступами

// Обработка сохранения телефона
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $_POST['mac'] ?? ''));
    $profile_id = $_POST['profile_id'] ?: null;
    $department_id = $_POST['department_id'] ?: null;
    $workplace = $_POST['workplace'] ?: null;

    // Валидация MAC
    if (strlen($mac) !== 12 || !ctype_xdigit($mac)) {
        $error = 'Некорректный MAC-адрес. Ожидается 12 шестнадцатеричных символов.';
    } else {
        // Проверка уникальности MAC
        $exists = $database->has('phones', [
            'mac' => $mac,
            'id[!]' => $id ?: 0
        ]);
        if ($exists) {
            $error = 'Телефон с таким MAC уже существует.';
        } else {
            // Определяем шаблон по OUI (первые 6 символов)
            $oui = substr($mac, 0, 6);
            $template = $database->get('phone_templates', '*', ['oui' => $oui]);
            if (!$template) {
                $error = 'Не найден шаблон для производителя (OUI: ' . $oui . '). Сначала создайте шаблон с соответствующим OUI.';
            } else {
                $data = [
                    'mac' => $mac,
                    'profile_id' => $profile_id,
                    'department_id' => $department_id,
                    'workplace' => $workplace,
                ];

                if ($id) {
                    $database->update('phones', $data, ['id' => $id]);
                    $success = 'Телефон обновлён.';
                    $database->delete('unknown_requests', ['mac' => implode(':', str_split($mac, 2))]);
                } else {
                    $database->insert('phones', $data);
                    $id = $database->id();
                    $success = 'Телефон добавлен.';
                    $database->delete('unknown_requests', ['mac' => implode(':', str_split($mac, 2))]);

                }

                // После сохранения основных данных переходим к переопределениям (если нужно)
                // Но можно остаться на этой же странице для редактирования переопределений
                // Перенаправление для очистки POST
                header('Location: phone_edit.php?id=' . $id . '&saved=1');
                exit;
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $success = 'Изменения сохранены.';
}

// Получаем актуальные данные телефона после сохранения
if ($id) {
    $phone = $database->get('phones', '*', ['id' => $id]);
    // Определяем OUI для отображения шаблона
    $oui = substr($phone['mac'], 0, 6);
    $template = $database->get('phone_templates', '*', ['oui' => $oui]);
    $templateName = $template ? $template['name'] : 'Неизвестный (OUI: ' . $oui . ')';
}

// Получаем поля профиля с учётом наследования
$profileFields = [];
if ($phone && $phone['profile_id']) {
    $profileFields = get_profile_fields_with_inheritance($database, $phone['profile_id']);
    // Получаем переопределения для этого телефона
    $overrides = $database->select('phone_overrides', '*', ['phone_id' => $phone['id']]);
    $overrideValues = [];
    foreach ($overrides as $o) {
        $overrideValues[$o['field_key']] = $o['override_value'];
    }
}

// Обработка добавления/обновления переопределения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_override') {
    $field_key = $_POST['field_key'];
    $override_value = $_POST['override_value'];

    // Проверяем, существует ли уже переопределение
    $exists = $database->has('phone_overrides', [
        'phone_id' => $phone['id'],
        'field_key' => $field_key
    ]);

    if ($exists) {
        $database->update('phone_overrides', ['override_value' => $override_value], [
            'phone_id' => $phone['id'],
            'field_key' => $field_key
        ]);
    } else {
        $database->insert('phone_overrides', [
            'phone_id' => $phone['id'],
            'field_key' => $field_key,
            'override_value' => $override_value
        ]);
    }

    header('Location: phone_edit.php?id=' . $phone['id'] . '&saved=1');
    exit;
}

// Обработка удаления переопределения
if (isset($_GET['delete_override'])) {
    $field_key = $_GET['delete_override'];
    $database->delete('phone_overrides', [
        'phone_id' => $phone['id'],
        'field_key' => $field_key
    ]);
    header('Location: phone_edit.php?id=' . $phone['id'] . '&saved=1');
    exit;
}

// Функция для построения дерева профилей с отступами
function buildProfilesTree($profiles, $parentId = null, $level = 0) {
    $tree = [];
    foreach ($profiles as $profile) {
        if ($profile['parent_id'] == $parentId) {
            $profile['level'] = $level;
            $tree[] = $profile;
            $tree = array_merge($tree, buildProfilesTree($profiles, $profile['id'], $level + 1));
        }
    }
    return $tree;
}

if (!$id && isset($_GET['mac'])){
    $phone = ["mac"=>str_replace(":","",$_GET['mac'])];
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Редактировать' : 'Добавить' ?> телефон</title>

    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <link rel="stylesheet" href="/css/fontawesome-free-6.7.2-web/css/all.min.css">
    <link href="/css/Inter-4.1/web/inter.css" rel="stylesheet">
    <link href="/css/select2.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
        .inherited { background-color: #f8f9fa; }
        .required-badge { background-color: #ffc107; color: #000; font-size: 0.75rem; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="phones.php">Телефоны</a></li>
            <li class="breadcrumb-item active"><?= $id ? htmlspecialchars($phone['mac']) : 'Новый телефон' ?></li>
        </ol>
    </nav>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Основные данные</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="phoneForm">
                        <div class="mb-3">
                            <label for="mac" class="form-label">MAC-адрес</label>
                            <input type="text" class="form-control" id="mac" name="mac"
                                   value="<?= htmlspecialchars($phone['mac'] ?? '') ?>"
                                   placeholder="001122334455" required
                                   pattern="[a-fA-F0-9]{12}" title="12 шестнадцатеричных символов">
                            <div class="form-text">12 символов без разделителей. Например: 001122334455</div>
                        </div>

                        <div class="mb-3">
                            <label for="profile_id" class="form-label">Профиль настроек</label>
                            <select class="form-select" id="profile_id" name="profile_id" required>
                                <option value="">-- Выберите профиль --</option>
                                <?php foreach ($profilesTree as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($phone['profile_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                        <?= str_repeat('&nbsp;', $p['level'] * 4) ?><?= htmlspecialchars($p['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="department_id" class="form-label">Подразделение</label>
                            <select class="form-select select2" id="department_id" name="department_id">
                                <option value="">-- Не выбрано --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= ($phone['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="workplace" class="form-label">Рабочее место</label>
                            <input type="text" class="form-control" id="workplace" name="workplace"
                                   value="<?= htmlspecialchars($phone['workplace'] ?? '') ?>"
                                   placeholder="Вася Пупкин..." >
                        </div>

                        <?php if ($id): ?>
                            <div class="mb-3">
                                <label class="form-label">Шаблон (определён по OUI)</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($templateName ?? '') ?>" readonly disabled>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="phones.php" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($id && $phone['profile_id']): ?>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Параметры телефона</h5>
                        <span class="badge bg-light text-dark">Профиль: <?= htmlspecialchars($database->get('profiles', 'name', ['id' => $phone['profile_id']])) ?></span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Здесь отображаются поля из выбранного профиля (с учётом наследования). Поля, отмеченные как обязательные, подсвечены. Вы можете переопределить значение любого поля.</p>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>Ключ</th>
                                    <th>Название</th>
                                    <th>Значение</th>
                                    <th>Источник</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($profileFields as $field):
                                    $isRequired = $field['is_required'];
                                    $isOverridden = isset($overrideValues[$field['field_key']]);
                                    $currentValue = $isOverridden ? $overrideValues[$field['field_key']] : $field['field_value'];
                                    $sourceProfileId = $field['profile_id'];
                                    $sourceProfileName = $database->get('profiles', 'name', ['id' => $sourceProfileId]);
                                    ?>
                                    <tr class="<?= $isOverridden ? '' : ($sourceProfileId != $phone['profile_id'] ? 'inherited' : '') ?>">
                                        <td><code><?= htmlspecialchars($field['field_key']) ?></code></td>
                                        <td>
                                            <?= htmlspecialchars($field['field_name']) ?>
                                            <?php if ($isRequired): ?>
                                                <span class="badge required-badge">обязательное</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($currentValue) ?>">
                                                <?= htmlspecialchars($currentValue) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($isOverridden): ?>
                                                <span class="badge bg-success">переопределено</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($sourceProfileName) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-override"
                                                    data-key="<?= htmlspecialchars($field['field_key']) ?>"
                                                    data-name="<?= htmlspecialchars($field['field_name']) ?>"
                                                    data-value="<?= htmlspecialchars($currentValue) ?>"
                                                    data-original="<?= htmlspecialchars($field['field_value']) ?>"
                                                    data-overridden="<?= $isOverridden ? 1 : 0 ?>">
                                                <i class="material-icons">edit</i>
                                            </button>
                                            <?php if ($isOverridden): ?>
                                                <a href="?id=<?= $id ?>&delete_override=<?= urlencode($field['field_key']) ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Сбросить переопределение? Будет использовано значение из профиля.')">
                                                    <i class="material-icons">undo</i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для редактирования значения поля -->
<div class="modal fade" id="overrideModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="overrideForm">
                <input type="hidden" name="action" value="save_override">
                <div class="modal-header">
                    <h5 class="modal-title">Редактирование поля</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="field_key" id="modal_field_key">
                    <div class="mb-3">
                        <label for="modal_field_name" class="form-label">Название</label>
                        <input type="text" class="form-control" id="modal_field_name" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label for="modal_override_value" class="form-label">Значение (переопределение)</label>
                        <textarea class="form-control" id="modal_override_value" name="override_value" rows="3"></textarea>
                    </div>
                    <div class="form-text" id="modal_original_value"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/css/bootstrap-5.1.3-dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/jquery-3.6.0.min.js"></script>
<script src="/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%'
        });

        // Редактирование переопределения
        $('.edit-override').click(function() {
            var key = $(this).data('key');
            var name = $(this).data('name');
            var value = $(this).data('value');
            var original = $(this).data('original');
            $('#modal_field_key').val(key);
            $('#modal_field_name').val(name);
            $('#modal_override_value').val(value);
            $('#modal_original_value').text('Значение из профиля: ' + original);
            new bootstrap.Modal(document.getElementById('overrideModal')).show();
        });
    });
</script>
</body>
</html>