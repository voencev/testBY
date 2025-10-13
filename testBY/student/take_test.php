<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
requireStudent();

$test = null;
$attempt_id = null;
$success_message = '';

// Если передан код теста, начинаем новую попытку
if (isset($_GET['code'])) {
    $test_code = trim($_GET['code']);
    
    // Поиск теста по коду
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE test_code = ?");
    $stmt->execute([$test_code]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        die("Тест не найден");
    }
    
    // Проверяем, нет ли уже активной попытки
    $stmt = $pdo->prepare("SELECT id FROM test_attempts WHERE test_id = ? AND student_id = ? AND finished_at IS NULL");
    $stmt->execute([$test['id'], $_SESSION['user_id']]);
    $existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_attempt) {
        $attempt_id = $existing_attempt['id'];
    } else {
        // Создание новой попытки
        $stmt = $pdo->prepare("INSERT INTO test_attempts (test_id, student_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $test['id'], 
            $_SESSION['user_id'], 
            $_SERVER['REMOTE_ADDR'], 
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        $attempt_id = $pdo->lastInsertId();
    }
    
    $_SESSION['current_attempt'] = $attempt_id;
    
} elseif (isset($_GET['attempt_id'])) {
    // Продолжение существующей попытки
    $attempt_id = $_GET['attempt_id'];
    
    $stmt = $pdo->prepare("
        SELECT t.*, ta.started_at 
        FROM test_attempts ta 
        JOIN tests t ON ta.test_id = t.id 
        WHERE ta.id = ? AND ta.student_id = ? AND ta.finished_at IS NULL
    ");
    $stmt->execute([$attempt_id, $_SESSION['user_id']]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        die("Попытка тестирования не найдена или уже завершена");
    }
    
    $_SESSION['current_attempt'] = $attempt_id;
}

// ПЕРЕМЕЩАЕМ ЭТУ ПРОВЕРКУ ВЫШЕ
if (!$test) {
    die("Тест не найден");
}

// Получение вопросов теста
$stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ?");
$stmt->execute([$test['id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    die("В этом тесте пока нет вопросов. Обратитесь к преподавателю.");
}

// Если вопросы перемешиваются
if ($test['shuffle_questions']) {
    shuffle($questions);
}

// Получение ответов для каждого вопроса
foreach ($questions as &$question) {
    $stmt = $pdo->prepare("SELECT * FROM answers WHERE question_id = ?");
    $stmt->execute([$question['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Если ответы перемешиваются
    if ($test['shuffle_answers']) {
        shuffle($answers);
    }
    
    $question['answers'] = $answers;
}
unset($question);

// Получение уже сохраненных ответов студента (включая текстовые)
$saved_answers = [];
if ($attempt_id) {
    $stmt = $pdo->prepare("SELECT question_id, answer_id, answer_text FROM student_answers WHERE attempt_id = ?");
    $stmt->execute([$attempt_id]);
    $saved_answers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($saved_answers_data as $answer) {
        $question_id = $answer['question_id'];
        if (!isset($saved_answers[$question_id])) {
            $saved_answers[$question_id] = [];
        }
        $saved_answers[$question_id][] = $answer;
    }
}

if ($test['time_limit'] > 0) {
    $start_time = strtotime($test['started_at'] ?? date('Y-m-d H:i:s'));
    $current_time = time();
    $elapsed_time = ($current_time - $start_time) / 60;
    
    if ($elapsed_time >= $test['time_limit']) {
        // Автоматически завершить тест
        finishTest($attempt_id);
        redirect("test_results.php?attempt_id=$attempt_id");
    }
}

// Обработка отправки ответов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $attempt_id) {
    $submitted_answers = $_POST['answers'] ?? [];
    $answers_saved = false;
    
    foreach ($submitted_answers as $question_id => $answer) {
        // Сначала удаляем все существующие ответы на этот вопрос
        $stmt = $pdo->prepare("DELETE FROM student_answers WHERE attempt_id = ? AND question_id = ?");
        $stmt->execute([$attempt_id, $question_id]);
        
        // Получаем информацию о вопросе ДО обработки ответа
        $stmt = $pdo->prepare("SELECT question_type FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$question) continue;
        
        if (is_array($answer)) {
            // Множественный выбор
            foreach ($answer as $answer_id) {
                if (!empty($answer_id) && is_numeric($answer_id)) {
                    // Проверяем существование ответа перед вставкой
                    $checkStmt = $pdo->prepare("SELECT id FROM answers WHERE id = ? AND question_id = ?");
                    $checkStmt->execute([$answer_id, $question_id]);
                    
                    if ($checkStmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
                        if ($stmt->execute([$attempt_id, $question_id, $answer_id])) {
                            $answers_saved = true;
                        }
                    }
                }
            }
        } else {
            // Одиночный выбор или текстовый ответ
            if (is_numeric($answer) && !empty($answer)) {
                // Одиночный выбор - проверяем существование ответа
                $checkStmt = $pdo->prepare("SELECT id FROM answers WHERE id = ? AND question_id = ?");
                $checkStmt->execute([$answer, $question_id]);
                
                if ($checkStmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
                    if ($stmt->execute([$attempt_id, $question_id, $answer])) {
                        $answers_saved = true;
                    }
                }
            } else if (!empty(trim($answer))) {
                // Текстовый ответ - проверяем тип вопроса
                if ($question['question_type'] === 'text') {
                    $text_answer = trim($answer);
                    
                    // Сохраняем текстовый ответ напрямую в student_answers
                    $stmt = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id, answer_text) VALUES (?, ?, ?)");
                    if ($stmt->execute([$attempt_id, $question_id, $text_answer])) {
                        $answers_saved = true;
                    }
                } else {
                    // Это одиночный выбор с пустым значением
                    // Можно добавить логирование или пропустить
                }
            }
        }
        
    }
    
    // Проверяем, завершен ли тест
    if (isset($_POST['finish_test']) || isset($_POST['auto_finish'])) {
        finishTest($attempt_id);
        unset($_SESSION['current_attempt']);
        redirect("test_results.php?attempt_id=$attempt_id");
    }
    
    // Сообщение об успешном сохранении
    if ($answers_saved) {
        $success_message = "Ответы успешно сохранены";
        // Обновляем сохраненные ответы с учетом текстовых полей
        $stmt = $pdo->prepare("SELECT question_id, answer_id, answer_text FROM student_answers WHERE attempt_id = ?");
        $stmt->execute([$attempt_id]);
        $saved_answers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $saved_answers = [];
        foreach ($saved_answers_data as $answer) {
            $question_id = $answer['question_id'];
            if (!isset($saved_answers[$question_id])) {
                $saved_answers[$question_id] = [];
            }
            $saved_answers[$question_id][] = $answer;
        }
    }
}

// Функция завершения теста и подсчета результатов
function finishTest($attempt_id) {
    global $pdo;
    
    // Получаем информацию о попытке
    $stmt = $pdo->prepare("SELECT * FROM test_attempts WHERE id = ?");
    $stmt->execute([$attempt_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attempt) {
        return;
    }
    
    // Получаем вопросы теста
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ?");
    $stmt->execute([$attempt['test_id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_score = 0;
    $max_score = 0;
    
    // Проверяем ответы на каждый вопрос
    foreach ($questions as $question) {
        $max_score += $question['points'];
        
        // Получаем ответы студента на этот вопрос
        $stmt = $pdo->prepare("SELECT * FROM student_answers WHERE attempt_id = ? AND question_id = ?");
        $stmt->execute([$attempt_id, $question['id']]);
        $student_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Если на вопрос нет ответа, пропускаем
        if (empty($student_answers)) {
            continue;
        }
     
        // Проверяем правильность ответов в зависимости от типа вопроса
        if ($question['question_type'] === 'single') {
            // Одиночный выбор
            if (count($student_answers) === 1) {
                $answer_id = $student_answers[0]['answer_id'];
                if ($answer_id) {
                    $stmt = $pdo->prepare("SELECT is_correct FROM answers WHERE id = ?");
                    $stmt->execute([$answer_id]);
                    $answer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($answer && $answer['is_correct']) {
                        $total_score += $question['points'];
                        
                        // Отмечаем ответ как правильный
                        $stmt = $pdo->prepare("UPDATE student_answers SET is_correct = 1 WHERE attempt_id = ? AND question_id = ?");
                        $stmt->execute([$attempt_id, $question['id']]);
                    }
                }
            }
        } elseif ($question['question_type'] === 'multiple') {
            $correct_answers_count = 0;
            $student_correct_answers = 0;
            $student_wrong_answers = 0;
            
            // Получаем ВСЕ правильные ответы для вопроса
            $stmt = $pdo->prepare("SELECT id FROM answers WHERE question_id = ? AND is_correct = 1");
            $stmt->execute([$question['id']]);
            $correct_answers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $correct_answers_count = count($correct_answers);
            
            // Проверяем ответы студента
            foreach ($student_answers as $student_answer) {
                if ($student_answer['answer_id']) {
                    if (in_array($student_answer['answer_id'], $correct_answers)) {
                        $student_correct_answers++;
                    } else {
                        $student_wrong_answers++;
                    }
                }
            }
            
            // Начисляем баллы только если все правильные ответы отмечены и нет неправильных
            if ($student_correct_answers === $correct_answers_count && $student_wrong_answers === 0) {
                $total_score += $question['points'];
                
                // Отмечаем все ответы как правильные
                $stmt = $pdo->prepare("UPDATE student_answers SET is_correct = 1 WHERE attempt_id = ? AND question_id = ?");
                $stmt->execute([$attempt_id, $question['id']]);
            }
        }
        // Для текстовых вопросов оценка выставляется вручную учителем
        // Автоматически баллы не начисляются
    }
    
    // Обновляем результаты попытки
    $stmt = $pdo->prepare("UPDATE test_attempts SET finished_at = NOW(), score = ?, max_score = ? WHERE id = ?");
    $stmt->execute([$total_score, $max_score, $attempt_id]);
}

$security_settings = json_decode($test['security_settings'], true);

// Применение мер безопасности
if ($attempt_id) {
    applySecurityMeasures($attempt_id, $security_settings);
}

// ДОБАВЛЯЕМ ПОЛУЧЕНИЕ ДАННЫХ ПОЛЬЗОВАТЕЛЯ ДЛЯ ШАПКИ
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_full_name = $user ? $user['full_name'] : 'Студент';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прохождение теста: <?php echo htmlspecialchars($test['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS стили остаются без изменений */
        .platform-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            background: var(--white);
        }

        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            padding: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }

        .logo {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--white);
            padding: 24px 30px;
            background: rgba(255, 255, 255, 0.05);
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            padding: 0 15px;
            flex: 1;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            margin: 4px 0;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: var(--border-radius);
            font-size: 14px;
        }

        .nav-menu a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            transform: translateX(4px);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            background: var(--light);
            margin-left: 280px;
            min-height: 100vh;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .header-top h1 {
            color: var(--dark);
            font-size: 1.8em;
            font-weight: 700;
            background: linear-gradient(135deg, var(--dark), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info span {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            font-weight: 600;
            box-shadow: var(--shadow);
        }

        .content-card {
            background: var(--white);
            padding: 32px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            border: var(--border);
            position: relative;
            overflow: hidden;
        }

        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .test-info {
            background: var(--light);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            border-left: 4px solid var(--primary);
        }

        .test-info p {
            margin: 8px 0;
            color: var(--text);
            font-weight: 500;
        }

        #time-remaining {
            font-weight: 600;
            color: var(--primary);
        }

        #timer {
            font-weight: 700;
            color: var(--danger);
            font-size: 1.2em;
        }

        .question {
            background: var(--white);
            padding: 24px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            border: var(--border);
            transition: all 0.3s ease;
        }

        .question:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .question h3 {
            color: var(--dark);
            margin-bottom: 16px;
            font-size: 1.2em;
            font-weight: 600;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--light);
        }

        .question p {
            color: var(--text);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .answers {
            margin-top: 16px;
        }

        .answer-option {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            margin-bottom: 10px;
            background: var(--hover-bg);
            border-radius: var(--border-radius);
            border: 1px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .answer-option:hover {
            background: var(--light);
            border-color: var(--primary-light);
        }

        .answer-option input[type="radio"],
        .answer-option input[type="checkbox"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--white);
            font-family: inherit;
            resize: vertical;
            transition: all 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .test-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--light-gray);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 14px;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #ef4444);
            color: var(--white);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c, var(--danger));
            transform: translateY(-2px);
        }

        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            font-weight: 500;
        }

        .alert.success {
            background: var(--success);
            color: white;
        }

        img {
            max-width: 100%;
            height: auto;
            border-radius: var(--border-radius);
            margin: 16px 0;
            box-shadow: var(--shadow);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .nav-menu {
                flex-direction: row;
                overflow-x: auto;
                padding: 10px 15px;
            }

            .nav-menu a {
                white-space: nowrap;
                margin: 0 4px;
            }

            .test-actions {
                flex-direction: column;
            }

            .header-top {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">BelProf</div>
            <nav class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1><?php echo htmlspecialchars($test['title']); ?></h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user_full_name); ?></span>
                    <div class="avatar"><?php echo substr($user_full_name, 0, 2); ?></div>
                </div>
            </div>

            <div class="content-card">
                <div class="test-info">
                    <p>Время начала: <?php echo date('d.m.Y H:i', strtotime($test['started_at'] ?? 'now')); ?></p>
                    <?php if ($test['time_limit'] > 0): ?>
                        <p id="time-remaining">Осталось времени: <span id="timer"></span></p>
                    <?php endif; ?>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <form id="test-form" method="POST">
                    <input type="hidden" id="auto-finish" name="auto_finish" value="0">
                    
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question">
                            <h3>Вопрос <?php echo $index + 1; ?> (<?php echo $question['points']; ?> баллов)</h3>
                            <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <?php if ($question['image_url']): ?>
                                <img src="<?php echo $question['image_url']; ?>" alt="Изображение к вопросу">
                            <?php endif; ?>
                            
                            <div class="answers">
                                <?php if ($question['question_type'] === 'single'): ?>
                                    <!-- Одиночный выбор -->
                                    <?php foreach ($question['answers'] as $answer): ?>
                                        <label class="answer-option">
                                            <input type="radio" 
                                                   name="answers[<?php echo $question['id']; ?>]" 
                                                   value="<?php echo $answer['id']; ?>"
                                                   <?php 
                                                   if (isset($saved_answers[$question['id']])) {
                                                       foreach ($saved_answers[$question['id']] as $saved_answer) {
                                                           if ($saved_answer['answer_id'] == $answer['id']) {
                                                               echo 'checked';
                                                               break;
                                                           }
                                                       }
                                                   }
                                                   ?>>
                                            <?php echo htmlspecialchars($answer['answer_text']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    
                                <?php elseif ($question['question_type'] === 'multiple'): ?>
                                    <!-- Множественный выбор -->
                                    <?php foreach ($question['answers'] as $answer): ?>
                                        <label class="answer-option">
                                            <input type="checkbox" 
                                                   name="answers[<?php echo $question['id']; ?>][]" 
                                                   value="<?php echo $answer['id']; ?>"
                                                   <?php 
                                                   if (isset($saved_answers[$question['id']])) {
                                                       foreach ($saved_answers[$question['id']] as $saved_answer) {
                                                           if ($saved_answer['answer_id'] == $answer['id']) {
                                                               echo 'checked';
                                                               break;
                                                           }
                                                       }
                                                   }
                                                   ?>>
                                            <?php echo htmlspecialchars($answer['answer_text']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    
                                <?php elseif ($question['question_type'] === 'text'): ?>
                                    <!-- Текстовый ответ -->
                                    <textarea name="answers[<?php echo $question['id']; ?>]" 
                                              rows="4" 
                                              placeholder="Введите ваш ответ"><?php 
                                    if (isset($saved_answers[$question['id']])) {
                                        foreach ($saved_answers[$question['id']] as $saved_answer) {
                                            if (!empty($saved_answer['answer_text'])) {
                                                echo htmlspecialchars($saved_answer['answer_text']);
                                                break;
                                            }
                                        }
                                    }
                                    ?></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="test-actions">
                        
                        <button type="submit" name="finish_test" class="btn btn-danger" 
                                onclick="return confirm('Вы уверены, что хотите завершить тест? После завершения изменить ответы будет невозможно.')">
                            <i class="fas fa-flag-checkered"></i> Завершить тест
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php if ($test['time_limit'] > 0): ?>
        <script>
            // Таймер обратного отсчета
            const timeLimit = <?php echo $test['time_limit'] * 60; ?>; // в секундах
            const startTime = new Date('<?php echo $test['started_at'] ?? date('Y-m-d H:i:s'); ?>').getTime();
            const endTime = startTime + (timeLimit * 1000);
            
            function updateTimer() {
                const now = new Date().getTime();
                const remaining = endTime - now;
                
                if (remaining <= 0) {
                    document.getElementById('timer').textContent = '00:00';
                    document.getElementById('auto-finish').value = '1';
                    document.getElementById('test-form').submit();
                    return;
                }
                
                const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
                
                document.getElementById('timer').textContent = 
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
            
            setInterval(updateTimer, 1000);
            updateTimer();
        </script>
    <?php endif; ?>
    
    <script>
        // Автосохранение каждые 30 секунд
        setInterval(() => {
            const saveButton = document.querySelector('button[name="save_answers"]');
            if (saveButton) {
                saveButton.click();
            }
        }, 30000);
        
        // Предупреждение при закрытии страницы
        let formChanged = false;
        const form = document.getElementById('test-form');
        const inputs = form.querySelectorAll('input, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });
        
        window.addEventListener('beforeunload', function (e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'У вас есть несохраненные изменения. Вы уверены, что хотите покинуть страницу?';
            }
        });
        
        form.addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
</body>
</html>