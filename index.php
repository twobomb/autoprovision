<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

$totalPhones = $database->count('phones');
$totalTemplates = $database->count('phone_templates');
$totalLogsToday = $database->count('logs', ['created_at[>=]' => date('Y-m-d 00:00:00')]);


// Определяем базовый URL текущего сайта
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$provisionUrl = $baseUrl . '/provision.php';
// Ссылка для автопровижинга (замените на актуальную)
$autoProvisionUrl = 'https://автопровижинг.пример/ссылка';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
        .stat-card {
            transition: box-shadow 0.3s;
            cursor: pointer;
        }
        .stat-card:hover {
            box-shadow: 0 8px 10px 1px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12), 0 5px 5px -3px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h4 class="mb-4">Панель управления</h4>

    <div class="row">
        <div class="col-md-4 mb-4">
            <a href="phones.php" class="text-decoration-none text-dark">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="material-icons" style="font-size: 48px;">phone_android</i>
                        <h3><?= $totalPhones ?></h3>
                        <p class="text-muted">Телефонов</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="templates.php" class="text-decoration-none text-dark">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="material-icons" style="font-size: 48px;">description</i>
                        <h3><?= $totalTemplates ?></h3>
                        <p class="text-muted">Шаблонов</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="logs.php" class="text-decoration-none text-dark">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="material-icons" style="font-size: 48px;">history</i>
                        <h3><?= $totalLogsToday ?></h3>
                        <p class="text-muted">Обращений сегодня</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Блок ссылок для копирования -->
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <i class="material-icons align-middle me-1">link</i> Ссылки для настройки
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Ссылка на автопрожинг для ручного указания в телефоне:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="provisionLink" value="<?= htmlspecialchars($provisionUrl) ?>" readonly>
                        <button class="btn btn-outline-secondary copy-btn" type="button" data-clipboard-target="#provisionLink">Копировать</button>
                    </div>
                    <small class="text-muted">Нажмите на поле или кнопку, чтобы скопировать</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/css/bootstrap-5.1.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Функция копирования текста в буфер обмена
        function copyText(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Скопировано!');
                }).catch(function(err) {
                    console.error('Ошибка копирования: ', err);
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    alert('Скопировано!');
                } else {
                    alert('Не удалось скопировать.');
                }
            } catch (err) {
                alert('Ошибка копирования.');
            }
            document.body.removeChild(textarea);
        }

        // Обработчики для кнопок копирования
        var copyButtons = document.querySelectorAll('.copy-btn');
        copyButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                var targetId = this.getAttribute('data-clipboard-target');
                var targetInput = document.querySelector(targetId);
                if (targetInput) {
                    copyText(targetInput.value);
                }
            });
        });

        // Копирование по клику на поле ввода
        var inputs = document.querySelectorAll('#autoProvisionLink, #provisionLink');
        inputs.forEach(function(input) {
            input.addEventListener('click', function() {
                this.select();
                copyText(this.value);
            });
        });
    });
</script>
</body>
</html>