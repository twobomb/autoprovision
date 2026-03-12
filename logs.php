<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

// Параметры фильтрации
$filter_mac = $_GET['mac'] ?? '';
$filter_ip = $_GET['ip'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$textinfo = $_GET['textinfo'] ?? '';

// Построение условий
$where = [];
if (!empty($filter_mac)) {
    $where['mac[~]'] = $filter_mac;
}
if (!empty($filter_ip)) {
    $where['ip[~]'] = $filter_ip;
}
if (!empty($date_from)) {
    $where['created_at[>=]'] = $date_from . ' 00:00:00';
}
if (!empty($date_to)) {
    $where['created_at[<=]'] = $date_to . ' 23:59:59';
}
if (!empty($textinfo)) {
    $where['textinfo[~]'] = $textinfo;
}

// Пагинация
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Получаем общее количество для пагинации
$total = $database->count('logs', $where);

// Получаем записи
$logs = $database->select('logs', '*', array_merge($where, [
    'ORDER' => ['created_at' => 'DESC'],
    'LIMIT' => [$offset, $limit]
]));

$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи обращений</title>
    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
        .filter-row {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h4 class="mb-4">Логи обращений телефонов</h4>

    <!-- Фильтр -->
    <div class="filter-row">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="mac" class="form-label">MAC-адрес</label>
                <input type="text" class="form-control" id="mac" name="mac" value="<?= htmlspecialchars($filter_mac) ?>" placeholder="00:11:22:33:44:55">
            </div>
            <div class="col-md-3">
                <label for="ip" class="form-label">IP-адрес</label>
                <input type="text" class="form-control" id="ip" name="ip" value="<?= htmlspecialchars($filter_ip) ?>" placeholder="192.168.1.100">
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Дата с</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Дата по</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-md-2">
                <label for="textinfo" class="form-label">Информация</label>
                <input type="text" class="form-control" id="textinfo" name="textinfo" value="<?= htmlspecialchars($textinfo) ?>" placeholder="Текст..">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Применить</button>
            </div>
        </form>
    </div>

    <?php if (empty($logs)): ?>
        <div class="alert alert-info">Логи не найдены</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>Дата/время</th>
                        <th>MAC</th>
                        <th>IP</th>
                        <th>User-Agent</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $log['created_at'] ?></td>
                            <td><?= htmlspecialchars($log['mac']) ?></td>
                            <td><?= htmlspecialchars($log['ip']) ?></td>
                            <td><small><?= htmlspecialchars($log['user_agent']) ?></small></td>
                            <td><?= htmlspecialchars($log['textinfo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Пагинация -->
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="/css/bootstrap-5.1.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>