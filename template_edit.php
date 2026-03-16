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
    $default_template_content = $_POST['default_template_content'] ?? '';
    $default_template_is_enabled = $_POST['default_template_is_enabled'] ?1:0;

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
                'template_content' => $template_content,
                'default_template_content' => $default_template_content,
                'default_template_is_enabled' => $default_template_is_enabled,
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
    <style>
        /* Стилизуем switch под Android (Material Design) */
        .android-switch.form-check {
            padding-left: 3.5rem;
        }
        .android-switch .form-check-input {
            width: 2.8rem;
            height: 1.4rem;
            margin-left: -3.5rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3.5' fill='%23fff'/%3e%3c/svg%3e");
            background-color: #b0b0b0;
            border: none;
            border-radius: 2rem;
            background-position: left center;
            background-size: auto 100%;
            background-repeat: no-repeat;
            transition: background-color 0.2s, background-position 0.2s;
            cursor: pointer;
        }
        .android-switch .form-check-input:checked {
            background-color: #32ee11; /* фирменный фиолетовый Android */
            background-position: right center;
        }
        .android-switch .form-check-input:focus {
            box-shadow: 0 0 0 0.2rem rgba(98, 0, 238, 0.25);
            border-color: transparent;
        }
        .android-switch .form-check-input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        textarea.readonly-disabled {
            background-color: #e9ecef;  /* серый фон как у disabled */
            opacity: 1;
            cursor: not-allowed;
            pointer-events: none;       /* запрет кликов (опционально) */
        }
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
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                            <a href="templates.php" class="btn btn-secondary">Отмена</a>
                        </div>

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
                            <label for="template_content" class="form-label">Содержимое основного шаблона</label>
                            <textarea class="form-control" id="template_content" name="template_content" rows="15" style="font-family: monospace;"><?= htmlspecialchars($template['template_content'] ?? '') ?></textarea>
                            <div class="form-text">
                                Используйте %ключ% для подстановки значений из профиля.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="default_template_content" class="form-label">Содержимое шаблона по умолчанию. Отдаётся телефону (как есть) который не добавлен в список по MAC, но имеет подходящий oui для данного шаблона. </label>
                            <div class="form-check form-switch android-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="default_template_is_enabled" name="default_template_is_enabled" <?= $template['default_template_is_enabled']?"checked":"" ?>>
                                <label class="form-check-label" for="default_template_is_enabled">Активировать шаблон по умолчанию</label>
                            </div>
                            <textarea class="form-control" id="default_template_content" name="default_template_content" rows="15" style="font-family: monospace;"><?= htmlspecialchars($template['default_template_content'] ?? '') ?></textarea>
                            <div class="form-text">
                                <b><span style="color:red;">Использование ключей %ключ% НЕДОПУСТИМО</span>. Шаблон по умолчанию отдаётся телефону как есть, ровно такой же как указан в этом поле!</b>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        let sw = document.querySelector("#default_template_is_enabled");
        let content = document.querySelector("#default_template_content");

        if (sw && content) {
            sw.addEventListener("input", function (ev) {
                // При включённом чекбоксе (checked = true) делаем поле недоступным
                if (!ev.currentTarget.checked) {
                    // Вместо disabled используем readonly + класс
                    content.readOnly = true;
                    content.classList.add("readonly-disabled");
                } else {
                    content.readOnly = false;
                    content.classList.remove("readonly-disabled");
                }
            });

            // Устанавливаем начальное состояние (если чекбокс сразу включён)
            sw.dispatchEvent(new Event("input"));
        }
    });
</script>
</body>
</html>