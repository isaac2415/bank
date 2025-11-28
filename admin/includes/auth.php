<?php
session_start();

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}
?>