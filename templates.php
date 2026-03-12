<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

// Обработка удаления
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Проверим, используется ли шаблон (в unknown_requests)
    $used = $database->has('unknown_requests', ['template_id' => $id]);
    if ($used) {
        $error = 'Невозможно удалить шаблон, так как есть неизвестные запросы, связанные с ним.';
    } else {
        $database->delete('phone_templates', ['id' => $id]);
        header('Location: templates.php');
        exit;
    }
}

// Получаем все шаблоны
$templates = $database->select('phone_templates', '*', ['ORDER' => ['oui' => 'ASC']]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Шаблоны телефонов (по OUI)</title>
    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
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
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Шаблоны по производителям (OUI)</h4>
        <a href="template_edit.php" class="btn btn-primary">
            <i class="material-icons align-middle">add</i> Создать шаблон
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if (empty($templates)): ?>
        <div class="alert alert-info">Нет шаблонов. Создайте первый шаблон.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>OUI</th>
                        <th>Название</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($template['oui']) ?></code></td>
                            <td><?= htmlspecialchars($template['name']) ?></td>
                            <td><?= $template['created_at'] ?></td>
                            <td>
                                <a href="template_edit.php?id=<?= $template['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="material-icons">edit</i>
                                </a>
                                <a href="templates.php?delete=<?= $template['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить шаблон?')">
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

<a href="template_edit.php" class="btn btn-primary btn-float">
    <i class="material-icons">add</i>
</a>
</body>
</html>