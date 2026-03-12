<?php

require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $parent_id = $_POST['parent_id'] ?: null;
    $sort_id = $_POST['sort_id'] ?? 0;

    if (empty($name)) {
        // Простейшая обработка, можно с сообщением
        header('Location: departments.php?error=empty_name');
        exit;
    }

    // Проверим, чтобы parent_id не ссылался на себя
    if ($id && $parent_id == $id) {
        header('Location: departments.php?error=self_parent');
        exit;
    }

    $data = [
        'name' => $name,
        'parent_id' => $parent_id,
        'sort_id' => $sort_id
    ];

    if ($id) {
        $database->update('departments', $data, ['id' => $id]);
    } else {
        $database->insert('departments', $data);
    }

    header('Location: departments.php');
    exit;
}