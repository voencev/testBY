<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_type'])) {
    $event_type = $_POST['event_type'];
    $attempt_id = $_SESSION['current_attempt'] ?? null;
    
    // Валидация типа события
    $allowed_events = ['tab_switch', 'fullscreen_exit', 'copy_attempt', 'paste_attempt', 'right_click', 'dev_tools'];
    
    if ($attempt_id && in_array($event_type, $allowed_events)) {
        logSecurityEvent($attempt_id, $event_type);
    }
    
    echo json_encode(['status' => 'logged']);
}
?>