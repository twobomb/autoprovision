<?php
require_once 'auth.php';
requireAuth();
require_once 'config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$profile_id = $input['profile_id'] ?? 0;
$order = $input['order'] ?? [];

if ($profile_id && $order) {
    foreach ($order as $index => $field_id) {
        $database->update('profile_fields', ['sort_order' => $index], ['id' => $field_id, 'profile_id' => $profile_id]);
    }
}