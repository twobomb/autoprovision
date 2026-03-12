<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

// Обработка удаления
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Проверим, используется ли профиль в телефонах или есть дочерние профили
    $usedInPhones = $database->has('phones', ['profile_id' => $id]);
    $hasChildren = $database->has('profiles', ['parent_id' => $id]);
    if ($usedInPhones || $hasChildren) {
        $error = 'Невозможно удалить профиль, так как он используется телефонами или имеет дочерние профили.';
    } else {
        $database->delete('profiles', ['id' => $id]);
        header('Location: profiles.php');
        exit;
    }
}

// Получаем все профили для отображения (с названиями родителей)
$profiles = $database->query("
    SELECT p.*, parent.name as parent_name 
    FROM profiles p 
    LEFT JOIN profiles parent ON p.parent_id = parent.id
    ORDER BY p.name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Профили настроек</title>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Профили настроек</h4>
        <a href="profile_edit.php" class="btn btn-primary">
            <i class="material-icons align-middle">add</i> Создать профиль
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if (empty($profiles)): ?>
        <div class="alert alert-info">Нет профилей. Создайте первый профиль.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Родительский профиль</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($profiles as $profile): ?>
                        <tr>
                            <td><?= $profile['id'] ?></td>
                            <td><?= htmlspecialchars($profile['name']) ?></td>
                            <td><?= $profile['parent_name'] ? htmlspecialchars($profile['parent_name']) : '—' ?></td>
                            <td><?= $profile['created_at'] ?></td>
                            <td>
                                <a href="profile_edit.php?id=<?= $profile['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Редактировать">
                                    <i class="material-icons">edit</i>
                                </a>
                                <a href="profile_fields.php?profile_id=<?= $profile['id'] ?>" class="btn btn-sm btn-outline-primary" title="Управление полями">
                                    <i class="material-icons">settings</i>
                                </a>
                                <a href="profiles.php?delete=<?= $profile['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить профиль? Все поля будут удалены.')">
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
</body>
</html>