<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isTreasurer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'treasurer';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../index.php");
        exit();
    }
}

function requireTreasurer() {
    if (!isTreasurer()) {
        header("Location: ../pages/dashboard.php");
        exit();
    }
}
?>