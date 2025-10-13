<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attempt_id'])) {
    $attempt_id = $_POST['attempt_id'];
    $timestamp = $_POST['timestamp'] ?? time();
    
    // Логирование переключения вкладки
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => 'tab_switch',
        'details' => "Обнаружено переключение вкладки в " . date('H:i:s', $timestamp/1000)
    ];
    
    $stmt = $pdo->prepare("UPDATE test_attempts SET security_log = CONCAT(COALESCE(security_log, ''), ?) WHERE id = ?");
    $stmt->execute([json_encode($log_entry) . "\n", $attempt_id]);
    
    // Можно также увеличивать счетчик нарушений
    echo json_encode(['status' => 'logged']);
}
?>