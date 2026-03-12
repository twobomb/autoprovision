<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

// Заменяем ?? на isset() для совместимости с PHP 5
$field_key = isset($_GET['field_key']) ? $_GET['field_key'] : '';
if (empty($field_key)) {
    header('Location: profiles.php');
    exit;
}

// Получаем все шаблоны для выпадающего списка
$templates = $database->select('phone_templates', ['id', 'name', 'oui'], ['ORDER' => ['name' => 'ASC']]);

// Текущие правила для этого поля
$rules = $database->select('field_transform_rules', '*', ['field_key' => $field_key]);

// Группируем правила по шаблону для удобства
$rulesByTemplate = array();
foreach ($rules as $rule) {
    $rulesByTemplate[$rule['template_id']] = $rule;
}

// Обработка сохранения правил
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Удаляем старые правила для этого поля
    $database->delete('field_transform_rules', ['field_key' => $field_key]);

    // Сохраняем новые правила
    $postRules = isset($_POST['rules']) ? $_POST['rules'] : array();
    foreach ($postRules as $template_id => $ruleData) {
        $rule_type = isset($ruleData['type']) ? $ruleData['type'] : 'map';
        $rule_data = array();

        if ($rule_type === 'fixed') {
            // Фиксированное значение
            if (!empty($ruleData['fixed_value'])) {
                $rule_data = array('fixed' => $ruleData['fixed_value']);
            } else {
                continue; // пустое правило не сохраняем
            }
        } else {
            // map - собираем пары ключ=>значение
            $map = array();
            if (isset($ruleData['map_from']) && is_array($ruleData['map_from'])) {
                foreach ($ruleData['map_from'] as $index => $from) {
                    $to = isset($ruleData['map_to'][$index]) ? $ruleData['map_to'][$index] : '';
                    if ($from !== '' && $to !== '') {
                        $map[$from] = $to;
                    }
                }
            }
            if (!empty($map)) {
                $rule_data = $map;
            } else {
                continue; // пустой map не сохраняем
            }
        }

        $database->insert('field_transform_rules', array(
            'field_key' => $field_key,
            'template_id' => $template_id,
            'rule_type' => $rule_type,
            'rule_data' => json_encode($rule_data, JSON_UNESCAPED_UNICODE)
        ));
    }

    $_SESSION['message'] = 'Правила сохранены';
    header('Location: field_rules.php?field_key=' . urlencode($field_key));
    exit;
}

// Получаем profile_id для ссылок (если есть)
$profile_id = isset($_GET['profile_id']) ? $_GET['profile_id'] : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Правила преобразования для поля <?php echo htmlspecialchars($field_key); ?></title>
    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <link rel="stylesheet" href="/css/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
        .rule-row { margin-bottom: 10px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="profiles.php">Профили</a></li>
            <li class="breadcrumb-item"><a href="profile_fields.php?profile_id=<?php echo $profile_id; ?>">Поля профиля</a></li>
            <li class="breadcrumb-item active">Правила для <?php echo htmlspecialchars($field_key); ?></li>
        </ol>
    </nav>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['message']; ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Настройка правил преобразования для поля "<?php echo htmlspecialchars($field_key); ?>"</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Здесь вы можете задать, как значение поля будет изменяться в зависимости от шаблона телефона.
                Если для шаблона не задано правило, используется исходное значение поля.
            </p>

            <form method="post">
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>Шаблон (OUI)</th>
                        <th>Тип правила</th>
                        <th>Настройки</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $template):
                        $rule = isset($rulesByTemplate[$template['id']]) ? $rulesByTemplate[$template['id']] : null;
                        $ruleType = $rule ? $rule['rule_type'] : 'map';
                        $ruleData = $rule ? json_decode($rule['rule_data'], true) : array();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($template['name']); ?></strong><br>
                                <small class="text-muted">OUI: <?php echo htmlspecialchars($template['oui']); ?></small>
                            </td>
                            <td>
                                <select name="rules[<?php echo $template['id']; ?>][type]" class="form-select rule-type-select" data-template="<?php echo $template['id']; ?>">
                                    <option value="map" <?php if ($ruleType === 'map') echo 'selected'; ?>>Сопоставление (map)</option>
                                    <option value="fixed" <?php if ($ruleType === 'fixed') echo 'selected'; ?>>Фиксированное значение (fixed)</option>
                                </select>
                            </td>
                            <td>
                                <!-- Контейнер для настроек map -->
                                <div class="map-settings" id="map-settings-<?php echo $template['id']; ?>" style="<?php if ($ruleType !== 'map') echo 'display: none;'; ?>">
                                    <div class="mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary add-map-row" data-template="<?php echo $template['id']; ?>" style="display: flex;justify-content: center;align-items: center;">
                                            <i class="material-icons">add</i> Добавить соответствие
                                        </button>
                                    </div>
                                    <div class="map-rows" id="map-rows-<?php echo $template['id']; ?>">
                                        <?php if ($ruleType === 'map' && !empty($ruleData)): ?>
                                            <?php foreach ($ruleData as $from => $to): ?>
                                                <div class="row mb-2 rule-row">
                                                    <div class="col-5">
                                                        <input type="text" class="form-control" name="rules[<?php echo $template['id']; ?>][map_from][]" value="<?php echo htmlspecialchars($from); ?>" placeholder="Исходное значение">
                                                    </div>
                                                    <div class="col-5">
                                                        <input type="text" class="form-control" name="rules[<?php echo $template['id']; ?>][map_to][]" value="<?php echo htmlspecialchars($to); ?>" placeholder="Результирующее значение">
                                                    </div>
                                                    <div class="col-2">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-map-row"><i class="material-icons">delete</i></button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <!-- Одна пустая строка по умолчанию для map -->
                                            <div class="row mb-2 rule-row">
                                                <div class="col-5">
                                                    <input type="text" class="form-control" name="rules[<?php echo $template['id']; ?>][map_from][]" placeholder="Исходное значение">
                                                </div>
                                                <div class="col-5">
                                                    <input type="text" class="form-control" name="rules[<?php echo $template['id']; ?>][map_to][]" placeholder="Результирующее значение">
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-map-row"><i class="material-icons">delete</i></button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Контейнер для настроек fixed -->
                                <div class="fixed-settings" id="fixed-settings-<?php echo $template['id']; ?>" style="<?php if ($ruleType !== 'fixed') echo 'display: none;'; ?>">
                                    <div class="row">
                                        <div class="col-12">
                                            <input type="text" class="form-control" name="rules[<?php echo $template['id']; ?>][fixed_value]"
                                                   value="<?php if ($ruleType === 'fixed' && isset($ruleData['fixed'])) echo htmlspecialchars($ruleData['fixed']); ?>"
                                                   placeholder="Фиксированное значение">
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Сохранить все правила</button>
                    <a href="profile_fields.php?profile_id=<?php echo $profile_id; ?>" class="btn btn-secondary">Назад к полям</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/css/bootstrap-5.1.3-dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Переключение между типами правил
        $('.rule-type-select').change(function() {
            var templateId = $(this).data('template');
            var selected = $(this).val();
            if (selected === 'map') {
                $('#map-settings-' + templateId).show();
                $('#fixed-settings-' + templateId).hide();
            } else {
                $('#map-settings-' + templateId).hide();
                $('#fixed-settings-' + templateId).show();
            }
        });

        // Добавление строки map
        $('.add-map-row').click(function() {
            var templateId = $(this).data('template');
            var container = $('#map-rows-' + templateId);
            var newRow = `
                <div class="row mb-2 rule-row">
                    <div class="col-5">
                        <input type="text" class="form-control" name="rules[${templateId}][map_from][]" placeholder="Исходное значение">
                    </div>
                    <div class="col-5">
                        <input type="text" class="form-control" name="rules[${templateId}][map_to][]" placeholder="Результирующее значение">
                    </div>
                    <div class="col-2">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-map-row"><i class="material-icons">delete</i></button>
                    </div>
                </div>
            `;
            container.append(newRow);
        });

        // Удаление строки map (через делегирование)
        $(document).on('click', '.remove-map-row', function() {
            $(this).closest('.rule-row').remove();
        });
    });
</script>
</body>
</html>