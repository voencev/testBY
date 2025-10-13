<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireTeacher();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

// Получаем данные из формы
$attempt_id = $_POST['attempt_id'] ?? null;
$question_id = $_POST['question_id'] ?? null;
$manual_score = $_POST['manual_score'] ?? 0;

if (!$attempt_id || !$question_id) {
    die('Неверные параметры запроса');
}

// Проверяем права доступа к попытке
$stmt = $pdo->prepare("
    SELECT ta.*, t.teacher_id, q.points as max_points 
    FROM test_attempts ta 
    JOIN tests t ON ta.test_id = t.id 
    JOIN questions q ON q.id = ?
    WHERE ta.id = ? AND t.teacher_id = ?
");
$stmt->execute([$question_id, $attempt_id, $_SESSION['user_id']]);
$access_check = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$access_check) {
    die('У вас нет прав для оценки этого ответа');
}

// Проверяем, что оценка не превышает максимальную
$max_points = $access_check['max_points'];
if ($manual_score > $max_points) {
    $manual_score = $max_points;
}
if ($manual_score < 0) {
    $manual_score = 0;
}

// Обновляем оценку в таблице student_answers
$stmt = $pdo->prepare("
    UPDATE student_answers 
    SET manual_score = ?, is_correct = ? 
    WHERE attempt_id = ? AND question_id = ?
");
$is_correct = ($manual_score > 0) ? 1 : 0;
$stmt->execute([$manual_score, $is_correct, $attempt_id, $question_id]);

// Пересчитываем общий балл за тест
recalculateTotalScore($attempt_id);

// Возвращаем обратно к деталям попытки
$_SESSION['success_message'] = "Оценка успешно сохранена";
redirect("attempt_details.php?attempt_id=$attempt_id");

// Функция для пересчета общего балла
function recalculateTotalScore($attempt_id) {
    global $pdo;
    
    // Получаем все ответы с ручными оценками
    $stmt = $pdo->prepare("
        SELECT sa.manual_score, sa.is_correct, q.points as max_points 
        FROM student_answers sa 
        JOIN questions q ON sa.question_id = q.id 
        WHERE sa.attempt_id = ?
    ");
    $stmt->execute([$attempt_id]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_score = 0;
    $max_score = 0;
    
    foreach ($answers as $answer) {
        $max_score += $answer['max_points'];
        
        // Если есть ручная оценка, используем её, иначе используем автоматическую
        if ($answer['manual_score'] !== null) {
            $total_score += $answer['manual_score'];
        } elseif ($answer['is_correct']) {
            $total_score += $answer['max_points'];
        }
    }
    
    // Обновляем общий результат
    $stmt = $pdo->prepare("UPDATE test_attempts SET score = ?, max_score = ? WHERE id = ?");
    $stmt->execute([$total_score, $max_score, $attempt_id]);
}
?>