<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

$profile_id = $_GET['profile_id'] ?? 0;
$profile = $database->get('profiles', '*', ['id' => $profile_id]);
if (!$profile) {
    header('Location: profiles.php');
    exit;
}

// Функция рекурсивного сбора полей родителей
function getParentFields($database, $parentId, &$result = []) {
    if (!$parentId) return $result;
    $parent = $database->get('profiles', '*', ['id' => $parentId]);
    if (!$parent) return $result;

    // Сначала идём вглубь, чтобы поля ближайшего родителя имели приоритет
    if ($parent['parent_id']) {
        getParentFields($database, $parent['parent_id'], $result);
    }

    $fields = $database->select('profile_fields', '*', ['profile_id' => $parentId]);
    foreach ($fields as $field) {
        // Если ключ ещё не определён (не переопределён в более близком родителе), добавляем
        if (!isset($result[$field['field_key']])) {
            $result[$field['field_key']] = [
                'id' => $field['id'],
                'name' => $field['field_name'],
                'value' => $field['field_value'],
                'is_required' => $field['is_required'],
                'source_profile_id' => $parentId,
                'source_profile_name' => $parent['name']
            ];
        }
    }
    return $result;
}

// Обработка добавления/редактирования поля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field_id = $_POST['field_id'] ?? 0;
    $field_key = trim($_POST['field_key']);
    $field_name = trim($_POST['field_name']);
    $field_value = $_POST['field_value'];
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (empty($field_key) || empty($field_name)) {
        $error = 'Ключ и название обязательны';
    } else {
        // Проверяем уникальность ключа в пределах текущего профиля (при создании)
        if (!$field_id) {
            $exists = $database->has('profile_fields', [
                'profile_id' => $profile_id,
                'field_key' => $field_key
            ]);
            if ($exists) {
                $error = 'Ключ уже существует в этом профиле';
            }
        }

        if (empty($error)) {
            $data = [
                'profile_id' => $profile_id,
                'field_key' => $field_key,
                'field_name' => $field_name,
                'field_value' => $field_value,
                'is_required' => $is_required,
                'sort_order' => $sort_order
            ];

            if ($field_id) {
                $database->update('profile_fields', $data, ['id' => $field_id, 'profile_id' => $profile_id]);
            } else {
                $database->insert('profile_fields', $data);
            }

            // Если это переопределение родительского поля, то после сохранения перезагружаем страницу
            header('Location: profile_fields.php?profile_id=' . $profile_id);
            exit;
        }
    }
}

// Удаление поля
if (isset($_GET['delete_field'])) {
    $field_id = $_GET['delete_field'];
    $database->delete('profile_fields', ['id' => $field_id, 'profile_id' => $profile_id]);
    header('Location: profile_fields.php?profile_id=' . $profile_id);
    exit;
}

// Получаем поля текущего профиля
$currentFields = $database->select('profile_fields', '*', [
    'profile_id' => $profile_id,
    'ORDER' => ['sort_order' => 'ASC', 'id' => 'ASC']
]);

// Получаем унаследованные поля (от всех родителей)
$inheritedFields = [];
if ($profile['parent_id']) {
    $inheritedFields = getParentFields($database, $profile['parent_id']);
    // Убираем те, которые уже переопределены в текущем профиле
    $currentKeys = array_column($currentFields, 'field_key');
    foreach ($currentKeys as $key) {
        unset($inheritedFields[$key]);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поля профиля: <?= htmlspecialchars($profile['name']) ?></title>

    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
        .sortable-handle { cursor: grab; }
        .inherited-badge { background-color: #e9ecef; color: #495057; font-size: 0.8rem; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="profiles.php">Профили</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($profile['name']) ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Поля профиля "<?= htmlspecialchars($profile['name']) ?>"</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal">
            <i class="material-icons align-middle">add</i> Добавить поле
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Поля текущего профиля -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Поля текущего профиля</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($currentFields)): ?>
                <p class="text-muted p-3">Нет полей. Добавьте первое поле.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush sortable" id="field-list">
                    <?php foreach ($currentFields as $field): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" data-id="<?= $field['id'] ?>">
                            <div class="d-flex align-items-center">
                                <span class="sortable-handle me-2"><i class="material-icons">drag_handle</i></span>
                                <div>
                                    <strong><?= htmlspecialchars($field['field_name']) ?></strong>
                                    <span class="text-muted ms-2">(<?= htmlspecialchars($field['field_key']) ?>)</span>
                                    <?php if ($field['is_required']): ?>
                                        <span class="badge bg-warning ms-2">обязательное</span>
                                    <?php endif; ?>
                                    <div class="small text-truncate" style="max-width: 300px;"><?= htmlspecialchars($field['field_value']) ?></div>
                                </div>
                            </div>
                            <div>
                                <a href="field_rules.php?field_key=<?= urlencode($field['field_key']) ?>&profile_id=<?= $profile_id ?>" class="btn btn-sm btn-outline-info" title="Правила преобразования">
                                    <i class="material-icons">settings</i>
                                </a>
                                <button class="btn btn-sm btn-outline-secondary edit-field"
                                        data-id="<?= $field['id'] ?>"
                                        data-key="<?= htmlspecialchars($field['field_key']) ?>"
                                        data-name="<?= htmlspecialchars($field['field_name']) ?>"
                                        data-value="<?= htmlspecialchars($field['field_value']) ?>"
                                        data-required="<?= $field['is_required'] ?>"
                                        data-sort="<?= $field['sort_order'] ?>">
                                    <i class="material-icons">edit</i>
                                </button>
                                <a href="?profile_id=<?= $profile_id ?>&delete_field=<?= $field['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить поле?')">
                                    <i class="material-icons">delete</i>
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Унаследованные поля -->
    <?php if (!empty($inheritedFields)): ?>
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Унаследованные поля (из родительских профилей)</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($inheritedFields as $key => $field): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($field['name']) ?></strong>
                                <span class="text-muted ms-2">(<?= htmlspecialchars($key) ?>)</span>
                                <?php if ($field['is_required']): ?>
                                    <span class="badge bg-warning ms-2">обязательное</span>
                                <?php endif; ?>
                                <div class="small">
                                    <span class="badge inherited-badge">из профиля "<?= htmlspecialchars($field['source_profile_name']) ?>"</span>
                                    <span class="text-muted ms-2">значение: <?= htmlspecialchars($field['value']) ?></span>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary override-field"
                                    data-key="<?= htmlspecialchars($key) ?>"
                                    data-name="<?= htmlspecialchars($field['name']) ?>"
                                    data-value="<?= htmlspecialchars($field['value']) ?>"
                                    data-required="<?= $field['is_required'] ?>">
                                <i class="material-icons">edit</i> Переопределить
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно для добавления/редактирования/переопределения поля -->
<div class="modal fade" id="fieldModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="fieldForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Добавить поле</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="field_id" id="field_id">
                    <div class="mb-3">
                        <label for="field_key" class="form-label">Ключ (уникальный в профиле)</label>
                        <input type="text" class="form-control" id="field_key" name="field_key" required pattern="[a-zA-Z0-9_]+" title="Только латиница, цифры и подчёркивание">
                        <div class="form-text" id="key-warning" style="color: orange; display: none;">
                            Этот ключ уже существует в родительских профилях. Вы переопределите его.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="field_name" class="form-label">Название</label>
                        <input type="text" class="form-control" id="field_name" name="field_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="field_value" class="form-label">Значение</label>
                        <textarea class="form-control" id="field_value" name="field_value" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_required" name="is_required" value="1">
                        <label class="form-check-label" for="is_required">Обязательно к переопределению (is_required)</label>
                    </div>
                    <input type="hidden" name="sort_order" id="sort_order" value="0">
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
<script src="/js/Sortable.min.js"></script>
<script>
    // Инициализация Sortable для перетаскивания
    const fieldList = document.getElementById('field-list');
    if (fieldList) {
        new Sortable(fieldList, {
            handle: '.sortable-handle',
            animation: 150,
            onEnd: function() {
                const items = fieldList.children;
                const order = [];
                for (let i = 0; i < items.length; i++) {
                    order.push(items[i].dataset.id);
                }
                fetch('ajax_reorder_fields.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile_id: <?= $profile_id ?>, order: order })
                });
            }
        });
    }

    // Заполнение формы при редактировании текущего поля
    document.querySelectorAll('.edit-field').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('field_id').value = this.dataset.id;
            document.getElementById('field_key').value = this.dataset.key;
            document.getElementById('field_key').readOnly = true; // ключ нельзя менять при редактировании
            document.getElementById('field_name').value = this.dataset.name;
            document.getElementById('field_value').value = this.dataset.value;
            document.getElementById('is_required').checked = this.dataset.required == '1';
            document.getElementById('sort_order').value = this.dataset.sort || 0;
            document.getElementById('modalTitle').innerText = 'Редактировать поле';
            document.getElementById('key-warning').style.display = 'none';
            new bootstrap.Modal(document.getElementById('fieldModal')).show();
        });
    });

    // Переопределение унаследованного поля
    document.querySelectorAll('.override-field').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('field_id').value = ''; // новое поле
            document.getElementById('field_key').value = this.dataset.key;
            document.getElementById('field_key').readOnly = true; // ключ фиксирован
            document.getElementById('field_name').value = this.dataset.name;
            document.getElementById('field_value').value = this.dataset.value;
            document.getElementById('is_required').checked = this.dataset.required == '1';
            document.getElementById('sort_order').value = 0;
            document.getElementById('modalTitle').innerText = 'Переопределить поле (из родителя)';
            document.getElementById('key-warning').style.display = 'none';
            new bootstrap.Modal(document.getElementById('fieldModal')).show();
        });
    });

    // Очистка формы при открытии на добавление
    document.querySelector('[data-bs-target="#fieldModal"]').addEventListener('click', function() {
        document.getElementById('fieldForm').reset();
        document.getElementById('field_id').value = '';
        document.getElementById('field_key').readOnly = false;
        document.getElementById('key-warning').style.display = 'none';
        document.getElementById('modalTitle').innerText = 'Добавить поле';
    });

    // Проверка ключа при вводе (для добавления)
    document.getElementById('field_key').addEventListener('input', function() {
        if (this.readOnly) return;
        const key = this.value;
        // Список ключей из унаследованных полей (переданный из PHP)
        const inheritedKeys = <?= json_encode(array_keys($inheritedFields)) ?>;
        if (inheritedKeys.includes(key)) {
            document.getElementById('key-warning').style.display = 'block';
        } else {
            document.getElementById('key-warning').style.display = 'none';
        }
    });
</script>
</body>
</html>