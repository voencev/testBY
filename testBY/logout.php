<?php
require_once 'includes/config.php';

// Уничтожение сессии
session_destroy();

// Перенаправление на главную страницу
header('Location: index.php');
exit;
?>