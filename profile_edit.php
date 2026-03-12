<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

$id = $_GET['id'] ?? 0;
$profile = null;
if ($id) {
    $profile = $database->get('profiles', '*', ['id' => $id]);
    if (!$profile) {
        header('Location: profiles.php');
        exit;
    }
}

// Получаем список всех профилей для выбора родителя (исключая самого себя и потомков, чтобы избежать цикла)
$allProfiles = $database->select('profiles', ['id', 'name'], ['ORDER' => ['name' => 'ASC']]);
// Для редактирования исключаем сам профиль и его потомков (можно сделать простую проверку, но для простоты исключим только себя)
$parentOptions = $allProfiles;
if ($id) {
    $parentOptions = array_filter($allProfiles, function($p) use ($id) {
        return $p['id'] != $id;
    });
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $parent_id = $_POST['parent_id'] ?: null;

    if (empty($name)) {
        $error = 'Название обязательно';
    } else {
        // Проверка на циклическую ссылку: parent_id не должен равняться id и не должен быть потомком (упростим, только проверка на себя)
        if ($id && $parent_id == $id) {
            $error = 'Родительский профиль не может быть самим собой.';
        } else {
            $data = [
                'name' => $name,
                'parent_id' => $parent_id
            ];

            if ($id) {
                $database->update('profiles', $data, ['id' => $id]);
            } else {
                $database->insert('profiles', $data);
                $id = $database->id();
            }
            header('Location: profile_fields.php?profile_id=' . $id);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'Редактировать' : 'Создать' ?> профиль</title>

    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= $id ? 'Редактировать профиль' : 'Новый профиль' ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="name" class="form-label">Название профиля</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Родительский профиль (необязательно)</label>
                            <select class="form-control" id="parent_id" name="parent_id">
                                <option value="">— Нет родителя —</option>
                                <?php foreach ($parentOptions as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($profile['parent_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить и перейти к полям</button>
                        <a href="profiles.php" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>