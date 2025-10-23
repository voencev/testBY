<?php
session_start();

// Подключение к БД
require_once '../includes/config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Получаем данные пользователя
$stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Если пользователь не студент - редирект
if ($user['role'] !== 'student') {
    header("Location: teacher_dashboard.php");
    exit();
}

// Функция для получения инициалов
function getInitials($full_name) {
    $words = explode(' ', $full_name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= mb_substr($word, 0, 1, 'UTF-8');
    }
    return mb_strtoupper($initials, 'UTF-8');
}

// Проверяем наличие attempt_id
if (!isset($_GET['attempt_id'])) {
    header("Location: dashboard.php");
    exit();
}

$attempt_id = $_GET['attempt_id'];

// Проверка прав доступа к результатам
$stmt = $pdo->prepare("
    SELECT ta.*, t.title, t.show_results 
    FROM test_attempts ta 
    JOIN tests t ON ta.test_id = t.id 
    WHERE ta.id = ? AND ta.student_id = ?
");
$stmt->execute([$attempt_id, $user_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    die('Попытка не найдена или у вас нет прав доступа');
}

if (!$attempt['finished_at']) {
    die('Тест еще не завершен');
}

// Получение ответов студента
$stmt = $pdo->prepare("
    SELECT 
        sa.*, 
        q.question_text, 
        q.question_type, 
        q.points, 
        a.answer_text, 
        a.is_correct as answer_is_correct,
        COALESCE(sa.answer_text, '') as student_answer_text
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.id
    LEFT JOIN answers a ON sa.answer_id = a.id
    WHERE sa.attempt_id = ?
    ORDER BY sa.question_id
");
$stmt->execute([$attempt_id]);
$student_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Группировка ответов по вопросам
$answers_by_question = [];
foreach ($student_answers as $answer) {
    $question_id = $answer['question_id'];
    if (!isset($answers_by_question[$question_id])) {
        $answers_by_question[$question_id] = [
            'question_text' => $answer['question_text'],
            'question_type' => $answer['question_type'],
            'points' => $answer['points'],
            'answers' => []
        ];
    }
    $answers_by_question[$question_id]['answers'][] = $answer;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты теста: <?php echo htmlspecialchars($attempt['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Результаты теста: <?php echo htmlspecialchars($attempt['title']); ?></h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                    <div class="avatar"><?php echo getInitials($user['full_name']); ?></div>
                </div>
            </div>

            <div class="content-card">
                <div class="attempt-summary">
                    <h2>Результат выполнения</h2>
                    <div class="summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                        <div class="card" style="text-align: center; padding: 20px;">
                            <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Начало теста</div>
                            <div style="font-size: 16px; font-weight: 600; color: var(--dark);">
                                <?php echo date('d.m.Y H:i', strtotime($attempt['started_at'])); ?>
                            </div>
                        </div>
                        <div class="card" style="text-align: center; padding: 20px;">
                            <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Завершение теста</div>
                            <div style="font-size: 16px; font-weight: 600; color: var(--dark);">
                                <?php echo date('d.m.Y H:i', strtotime($attempt['finished_at'])); ?>
                            </div>
                        </div>
                        <div class="card" style="text-align: center; padding: 20px;">
                            <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Набрано баллов</div>
                            <div style="font-size: 16px; font-weight: 600; color: var(--dark);">
                                <?php echo $attempt['score']; ?> из <?php echo $attempt['max_score']; ?>
                            </div>
                        </div>
                        <div class="card" style="text-align: center; padding: 20px;">
                            <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Процент выполнения</div>
                            <div style="font-size: 16px; font-weight: 600; color: var(--primary);">
                                <?php echo round(($attempt['score'] / $attempt['max_score']) * 100, 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($attempt['show_results']): ?>
                <div class="content-card">
                    <h2>Детальные результаты</h2>
                    
                    <?php foreach ($answers_by_question as $question_id => $question_data): ?>
                        <div class="card" style="margin-bottom: 24px; padding: 24px;">
                            <h3 style="color: var(--dark); margin-bottom: 16px; font-size: 1.2em; font-weight: 600;">
                                Вопрос <?php echo array_search($question_id, array_keys($answers_by_question)) + 1; ?>
                            </h3>
                            <p style="color: var(--text); margin-bottom: 16px; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($question_data['question_text'])); ?>
                            </p>
                            <p style="color: var(--text-light); font-size: 14px; margin-bottom: 20px;">
                                <strong>Баллы:</strong> <?php echo $question_data['points']; ?>
                            </p>
                            
                            <div class="answer-review" style="background: var(--light); padding: 20px; border-radius: var(--border-radius);">
                                <h4 style="color: var(--dark); margin-bottom: 12px; font-size: 1.1em;">Ваш ответ:</h4>
                                
                                <?php if ($question_data['question_type'] === 'text'): ?>
                                    <?php $text_answer = $question_data['answers'][0]['student_answer_text'] ?? ''; ?>
                                    <textarea readonly rows="4" style="width: 100%; padding: 12px; border: 1px solid var(--light-gray); border-radius: var(--border-radius); background: var(--white); font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($text_answer); ?></textarea>
                                    <p style="color: var(--text-light); font-size: 14px; margin-top: 8px; font-style: italic;">
                                        Текстовые ответы проверяются учителем вручную
                                    </p>
                                <?php else: ?>
                                    <ul style="list-style: none; padding: 0; margin: 0;">
                                        <?php foreach ($question_data['answers'] as $answer): ?>
                                            <li style="padding: 12px; margin-bottom: 8px; background: var(--white); border-radius: var(--border-radius); border: 1px solid var(--light-gray); display: flex; align-items: center; justify-content: space-between;">
                                                <span style="flex: 1;">
                                                    <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                </span>
                                                <span style="margin-left: 12px;">
                                                    <?php if ($answer['is_correct'] && $answer['answer_is_correct']): ?>
                                                        <span style="color: var(--success); font-weight: 600;">✓ Правильно</span>
                                                    <?php elseif ($answer['is_correct'] && !$answer['answer_is_correct']): ?>
                                                        <span style="color: var(--danger); font-weight: 600;">✗ Неправильно</span>
                                                    <?php elseif (!$answer['is_correct'] && $answer['answer_is_correct']): ?>
                                                        <span style="color: var(--warning); font-weight: 600;">ⓘ Правильный ответ, но не выбран</span>
                                                    <?php endif; ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="content-card">
                    <div class="alert info" style="background: linear-gradient(135deg, #dbeafe, #e0f2fe); padding: 24px; border-radius: var(--border-radius); border: 1px solid #bfdbfe; text-align: center;">
                        <p style="color: var(--dark); margin: 0; font-size: 16px; font-weight: 500;">
                            Подробные результаты этого теста не показываются студентам. Обратитесь к преподавателю для получения обратной связи.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 30px;">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Назад в личный кабинет
                </a>
            </div>
        </div>
    </div>
</body>
</html>