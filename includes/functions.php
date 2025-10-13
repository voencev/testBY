<?php
// Функции для работы с пользователями
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

// Генерация случайного кода теста
function generateTestCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Проверка прав доступа
function requireTeacher() {
    if (!isLoggedIn() || getUserRole() !== 'teacher') {
        redirect('../index.php');
    }
}

function requireStudent() {
    if (!isLoggedIn() || getUserRole() !== 'student') {
        redirect('../index.php');
    }
}
?>