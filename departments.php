<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

// Обработка удаления
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Проверим, есть ли дочерние подразделения или телефоны, привязанные к этому
    $hasChildren = $database->has('departments', ['parent_id' => $id]);
    $hasPhones = $database->has('phones', ['department_id' => $id]);
    if ($hasChildren || $hasPhones) {
        $error = 'Невозможно удалить подразделение, так как у него есть дочерние подразделения или привязанные телефоны.';
    } else {
        $database->delete('departments', ['id' => $id]);
        header('Location: departments.php');
        exit;
    }
}
function renderDepartments($parent_id = 0, $level = 0) {
    global $database;
    $children = $database->select('departments', '*', [
        'parent_id' => $parent_id ?: null, // учитываем, что parent_id может быть NULL
        'ORDER' => ['sort_id' => 'ASC', 'name' => 'ASC']
    ]);
    foreach ($children as $dept) {
        $prefix = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $nameDisplay = $prefix . htmlspecialchars($dept['name']);
        // Вывод строки таблицы
        echo '<tr>';
        echo '<td>' . $nameDisplay . '</td>';
        echo '<td>';
        echo '<button class="btn btn-sm btn-outline-secondary edit-dept" data-id="' . $dept['id'] . '" data-name="' . htmlspecialchars($dept['name']) . '" data-parent="' . $dept['parent_id'] . '" data-sort="' . $dept['sort_id'] . '"><i class="material-icons">edit</i></button>';
        echo '<a href="departments.php?delete=' . $dept['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Удалить подразделение?\')"><i class="material-icons">delete</i></a>';
        echo '</td>';
        echo '</tr>';
        // Рекурсивно выводим дочерние
        renderDepartments($dept['id'], $level + 1);
    }
}

// Получаем все подразделения для отображения в плоском списке
$departments = $database->select('departments', '*', ['ORDER' => ['sort_id' => 'ASC', 'name' => 'ASC']]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подразделения</title>

    <link href="/css/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="/css/googleicons/icon.css">
    <link rel="stylesheet" href="/css/fontawesome-free-6.7.2-web/css/all.min.css">
    <link href="/css/Inter-4.1/web/inter.css" rel="stylesheet">

    <style>
        body { background: #f5f5f5; }
        .card { box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2); }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Подразделения</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal">
            <i class="material-icons align-middle">add</i> Добавить подразделение
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Название</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>

                <?php renderDepartments(0); // начинаем с корневых (parent_id = 0 или NULL) ?>
                <?php if (empty($database->select('departments', 'id', ['parent_id' => null])) && empty($database->select('departments', 'id', ['parent_id' => 0]))): ?>
                    <tr><td colspan="5" class="text-center text-muted">Нет подразделений</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования подразделения -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="department_save.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="deptModalTitle">Добавить подразделение</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="dept_id">
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Родительское подразделение</label>
                        <select class="form-select select2" id="parent_id" name="parent_id">
                            <option value="">-- Нет родителя --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="sort_id" class="form-label">Порядок сортировки</label>
                        <input type="number" class="form-control" id="sort_id" name="sort_id" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="/css/bootstrap-5.1.3-dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/jquery-3.6.0.min.js"></script>
<script src="/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%',
            dropdownParent: $('#departmentModal')
        });

        // Редактирование
        $('.edit-dept').click(function() {
            $('#dept_id').val($(this).data('id'));
            $('#name').val($(this).data('name'));
            $('#parent_id').val($(this).data('parent')).trigger('change');
            $('#sort_id').val($(this).data('sort'));
            $('#deptModalTitle').text('Редактировать подразделение');
            new bootstrap.Modal(document.getElementById('departmentModal')).show();
        });

        // При открытии на добавление очищаем форму
        $('[data-bs-target="#departmentModal"]').click(function() {
            $('#dept_id').val('');
            $('#name').val('');
            $('#parent_id').val('').trigger('change');
            $('#sort_id').val('0');
            $('#deptModalTitle').text('Добавить подразделение');
        });
    });
</script>
</body>
</html>