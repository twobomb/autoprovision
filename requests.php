<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';



if(isset($_GET["reject"])) {
    $database->delete("unknown_requests",["mac"=>$_GET["reject"]]);
    header('Location: requests.php');
    die;
}

// Получаем список запросов с информацией о шаблоне
$requests = $database->query("
    SELECT r.*, t.name as template_name 
    FROM unknown_requests r 
    LEFT JOIN phone_templates t ON r.template_id = t.id
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Запросы от неизвестных телефонов</title>
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
    <h4>Запросы от неизвестных телефонов</h4>
    <p class="text-muted">Телефоны, которые обращались за конфигом, но не зарегистрированы в системе. Шаблон по OUI найден.</p>

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">Нет записей.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>MAC</th>
                        <th>OUI</th>
                        <th>IP</th>
                        <th>User-Agent</th>
                        <th>Шаблон</th>
                        <th>Время</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($req['mac']) ?></code></td>
                            <td><code><?= htmlspecialchars($req['oui']) ?></code></td>
                            <td><?= htmlspecialchars($req['ip']) ?></td>
                            <td><small><?= htmlspecialchars(mb_substr($req['user_agent'], 0, 50)) ?>...</small></td>
                            <td><?= htmlspecialchars($req['template_name'] ?? '—') ?></td>
                            <td><?= $req['created_at'] ?></td>
                            <td>
                                <a href="phone_edit.php?mac=<?= urlencode($req['mac']) ?>" class="btn btn-sm btn-success"  style="display: flex;justify-content: center;align-items: center;" title="Создать телефон на основе этого MAC">
                                    <i class="material-icons">add</i> Добавить телефон
                                </a>

                                <a href="?reject=<?= urlencode($req['mac']) ?>" class="btn btn-sm btn-danger"  style="display: flex;justify-content: center;align-items: center;" >
                                    <i class="material-icons">remove</i> Отклонить
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
</body>
</html>