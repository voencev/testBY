<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attempt_id'])) {
    $attempt_id = $_POST['attempt_id'];
    
    // Логирование выхода из полноэкранного режима
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => 'fullscreen_exit',
        'details' => 'Пользователь вышел из полноэкранного режима'
    ];
    
    $stmt = $pdo->prepare("UPDATE test_attempts SET security_log = CONCAT(COALESCE(security_log, ''), ?) WHERE id = ?");
    $stmt->execute([json_encode($log_entry) . "\n", $attempt_id]);
    
    echo json_encode(['status' => 'logged']);
}
?>