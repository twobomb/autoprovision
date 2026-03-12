<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';
require_once 'functions.php';

// Получаем список подразделений для фильтра
$departments = $database->select('departments', ['id', 'name'], ['ORDER' => ['sort_id' => 'ASC', 'name' => 'ASC']]);

// Получаем все шаблоны для сопоставления OUI -> имя
$templates = $database->select('phone_templates', ['oui', 'name']);
$templatesByOui = [];
foreach ($templates as $tpl) {
    $templatesByOui[$tpl['oui']] = $tpl['name'];
}

// Построение запроса с фильтрами
$where = [];
if (!empty($_GET['department'])) {
    $where['phones.department_id'] = $_GET['department'];
}
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where['OR'] = [
        'phones.mac[~]' => $search,
        'phones.ip[~]' => $search,
        'phones.workplace[~]' => $search,
    ];
}

$phones = $database->select('phones', [
    '[>]departments' => ['department_id' => 'id'],
    '[>]profiles' => ['profile_id' => 'id']
], [
    'phones.id',
    'phones.mac',
    'phones.ip',
    'phones.workplace',
    'phones.created_at',
    'phones.updated_at',
    'phones.last_request',
    'departments.name (department_name)',
    'profiles.name (profile_name)'
],
    array_merge($where,['ORDER' => ['phones.updated_at' => 'DESC']])
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Телефоны</title>

    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <link rel="stylesheet" href="/css/fontawesome-free-6.7.2-web/css/all.min.css">
    <link href="/css/Inter-4.1/web/inter.css" rel="stylesheet">
    <link href="/css/select2.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
        .btn-float {
            position: fixed;
            bottom: 20px;
            right: 20px;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px 0 rgba(0,0,0,0.26);
        }
        .template-badge {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Телефоны</h4>
        <a href="phone_edit.php" class="btn btn-primary">
            <i class="material-icons align-middle">add</i> Добавить телефон
        </a>
    </div>

    <!-- Фильтр -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="department" class="form-label">Подразделение</label>
                    <select class="form-select select2" id="department" name="department">
                        <option value="">Все подразделения</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($_GET['department'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Поиск по MAC или IP или рабочему месту</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Введите MAC или IP или раб.место">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Применить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Список телефонов -->
    <?php if (empty($phones)): ?>
        <div class="alert alert-info">Нет телефонов.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>MAC / Шаблон</th>
                        <th>IP</th>
                        <th>Рабочее место</th>
                        <th>Профиль</th>
                        <th>Подразделение</th>
                        <th>Последнее обращение</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($phones as $phone):
                        // Определяем шаблон по OUI
                        $macClean = str_replace(':', '', $phone['mac']);
                        $oui = strtoupper(substr($macClean, 0, 6));
                        $templateName = $templatesByOui[$oui] ?? '—';
                        ?>
                        <tr>
                            <td>
                                <code><?= htmlspecialchars($phone['mac']) ?></code>
                                <div class="template-badge">Шаблон: <?= htmlspecialchars($templateName) ?></div>
                            </td>
                            <td><?= htmlspecialchars($phone['ip'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($phone['workplace'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($phone['profile_name']) ?></td>
                            <td><?= htmlspecialchars($phone['department_name'] ?? '-') ?></td>
                            <td><?= formatLastSeen($phone['last_request']) ?></td>
                            <td>
                                <a href="phone_edit.php?id=<?= $phone['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="material-icons">edit</i>
                                </a>
                                <a href="phone_delete.php?id=<?= $phone['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить телефон?')">
                                    <i class="material-icons">delete</i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<a href="phone_edit.php" class="btn btn-primary btn-float">
    <i class="material-icons">add</i>
</a>

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