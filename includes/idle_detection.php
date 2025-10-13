<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idle_time'])) {
    $idle_time = intval($_POST['idle_time']);
    
    // Если время бездействия превышает 5 минут, залогировать это
    if ($idle_time > 300) {
        // Здесь можно добавить логику для получения attempt_id из сессии
        if (isset($_SESSION['current_attempt'])) {
            $attempt_id = $_SESSION['current_attempt'];
            
            $log_entry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'event_type' => 'idle_detection',
                'details' => "Обнаружено бездействие в течение $idle_time секунд"
            ];
            
            $stmt = $pdo->prepare("UPDATE test_attempts SET security_log = CONCAT(COALESCE(security_log, ''), ?) WHERE id = ?");
            $stmt->execute([json_encode($log_entry) . "\n", $attempt_id]);
        }
    }
}
?>