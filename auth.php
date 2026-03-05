<?php
// config/auth.php
session_start();

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        header("Location: /birthday/admin/login.php");
        exit;
    }
}