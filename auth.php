<?php
session_start();

define('ADMIN_LOGIN', 'admin');
define('ADMIN_PASSWORD', 'AdminAutoprovision25!');

function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function authenticate($login, $password) {
    if ($login === ADMIN_LOGIN && $password === ADMIN_PASSWORD) {
        $_SESSION['authenticated'] = true;
        return true;
    }
    return false;
}

function logout() {
    unset($_SESSION['authenticated']);
    session_destroy();
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}