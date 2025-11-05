<?php
function checkAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }
    return true;
}

function getUserRole() {
    return $_SESSION['user_role'] ?? 'user';
}

function hasPermission($requiredRole) {
    $userRole = getUserRole();
    $rolesHierarchy = ['user' => 1, 'department_head' => 2, 'admin' => 3];
    
    return ($rolesHierarchy[$userRole] ?? 0) >= ($rolesHierarchy[$requiredRole] ?? 0);
}

function logout() {
    session_destroy();
    header('Location: ../login.php');
    exit();
}
?>