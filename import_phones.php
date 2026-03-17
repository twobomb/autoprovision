<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

// Получаем список профилей для выпадающего списка
$profiles = $database->select('profiles', ['id', 'name'], ['ORDER' => ['name' => 'ASC']]);

// Получаем список подразделений для выпадающего списка (с иерархией для select2)
$departments = $database->select('departments', ['id', 'name', 'parent_id'], ['ORDER' => ['sort_id' => 'ASC', 'name' => 'ASC']]);

// Функция для построения дерева подразделений для select2 (плоский список с отступами)
function buildDepartmentOptions($departments, $parentId = null, $level = 0) {
    $options = [];
    foreach ($departments as $dept) {
        if ($dept['parent_id'] == $parentId) {
            $dept['level'] = $level;
            $options[] = $dept;
            $options = array_merge($options, buildDepartmentOptions($departments, $dept['id'], $level + 1));
        }
    }
    return $options;
}

$departmentOptions = buildDepartmentOptions($departments);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Импорт телефонов из XLSX</title>
    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <link rel="stylesheet" href="/css/fontawesome-free-6.7.2-web/css/all.min.css">
    <link href="/css/Inter-4.1/web/inter.css" rel="stylesheet">
    <link href="/css/select2.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
        .alert ul { margin-bottom: 0; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="material-icons align-middle me-2">upload_file</i> Импорт телефонов из XLSX</h5>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['import_errors'])): ?>
                <div class="alert alert-danger">
                    <strong>Ошибки при импорте:</strong>
                    <ul>
                        <?php foreach ($_SESSION['import_errors'] as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php unset($_SESSION['import_errors']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['import_success'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['import_success'] ?>
                </div>
                <?php unset($_SESSION['import_success']); ?>
            <?php endif; ?>

            <form action="import_phones_process.php" method="post" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="profile_id" class="form-label">Профиль настроек <span class="text-danger">*</span></label>
                        <select class="form-select" id="profile_id" name="profile_id" required>
                            <option value="">-- Выберите профиль --</option>
                            <?php foreach ($profiles as $profile): ?>
                                <option value="<?= $profile['id'] ?>"><?= htmlspecialchars($profile['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="department_id" class="form-label">Подразделение <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="department_id" name="department_id" required>
                            <option value="">-- Выберите подразделение --</option>
                            <?php foreach ($departmentOptions as $dept): ?>
                                <option value="<?= $dept['id'] ?>">
                                    <?= str_repeat('— ', $dept['level']) . htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="update_existing" class="form-label">
                        <input type="checkbox" name="update_existing" id="update_existing" value="1">
                        Обновлять существующие телефоны (по MAC)
                    </label>
                    <small class="text-muted d-block">Если отмечено, при совпадении MAC данные телефона (workplace и переопределения) будут обновлены.</small>
                </div>

                <div class="mb-3">
                    <label for="file" class="form-label">XLSX файл <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="file" name="file" accept=".xlsx" required>
                    <span class="text-muted">
                        <b>Первая строка должна содержать названия колонок. Обязательные колонки: <code>MAC</code> и <code>NAME</code> (рабочее место).
                        Остальные колонки должны соответствовать ключам полей в выбранном профиле (с учётом наследования).<br>

                        <a href="/example_import_phones.xlsx">Скачать пример xlsx файла</a></b>
                    </span>
                </div>

                <button type="submit" class="btn btn-primary">Загрузить и импортировать</button>
                <a href="phones.php" class="btn btn-secondary">Отмена</a>
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
    });
</script>
</body>
</html>