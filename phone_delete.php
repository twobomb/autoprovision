<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

$id = $_GET['id'] ?? 0;
if ($id) {
    // Удаляем переопределения, затем телефон
    $database->delete('phone_overrides', ['phone_id' => $id]);
    $database->delete('phones', ['id' => $id]);
}
header('Location: phones.php');