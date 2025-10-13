<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attempt_id'])) {
    $attempt_id = $_POST['attempt_id'];
    $idle_time = $_POST['idle_time'] ?? 0;
    
    // Логирование автоматического завершения из-за бездействия
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => 'idle_timeout',
        'details' => "Тест автоматически завершен после $idle_time секунд бездействия"
    ];
    
    $stmt = $pdo->prepare("UPDATE test_attempts SET security_log = CONCAT(COALESCE(security_log, ''), ?) WHERE id = ?");
    $stmt->execute([json_encode($log_entry) . "\n", $attempt_id]);
    
    echo json_encode(['status' => 'logged']);
}
?>