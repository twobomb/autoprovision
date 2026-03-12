<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

$id = $_GET['id'] ?? 0;
$template = null;
if ($id) {
    $template = $database->get('phone_templates', '*', ['id' => $id]);
    if (!$template) {
        header('Location: templates.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $oui = strtoupper(trim($_POST['oui'] ?? ''));
    $template_content = $_POST['template_content'] ?? '';

    // Валидация OUI: 6 шестнадцатеричных символов
    if (!preg_match('/^[A-F0-9]{6}$/', $oui)) {
        $error = 'OUI должен состоять из 6 шестнадцатеричных символов (A-F, 0-9).';
    } elseif (empty($name)) {
        $error = 'Название обязательно.';
    } else {
        // Проверка уникальности OUI
        $exists = $database->has('phone_templates', [
            'oui' => $oui,
            'id[!]' => $id ?: 0
        ]);
        if ($exists) {
            $error = 'Шаблон с таким OUI уже существует.';
        } else {
            $data = [
                'name' => $name,
                'oui' => $oui,
                'template_content' => $template_content
            ];

            if ($id) {
                $database->update('phone_templates', $data, ['id' => $id]);
            } else {
                $database->insert('phone_templates', $data);
            }

            header('Location: templates.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'Редактировать' : 'Создать' ?> шаблон</title>

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
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= $id ? 'Редактировать шаблон' : 'Новый шаблон' ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="oui" class="form-label">OUI (первые 6 символов MAC)</label>
                            <input type="text" class="form-control" id="oui" name="oui" value="<?= htmlspecialchars($template['oui'] ?? '') ?>" maxlength="6" style="text-transform:uppercase" required placeholder="Например: 0004F2">
                            <div class="form-text">Шестнадцатеричный код производителя (без двоеточий).</div>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Название шаблона</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($template['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="template_content" class="form-label">Содержимое шаблона</label>
                            <textarea class="form-control" id="template_content" name="template_content" rows="15" style="font-family: monospace;"><?= htmlspecialchars($template['template_content'] ?? '') ?></textarea>
                            <div class="form-text">
                                Используйте %ключ% для подстановки значений из профиля.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="templates.php" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>