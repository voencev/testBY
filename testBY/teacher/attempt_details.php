<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireTeacher();

if (!isset($_GET['attempt_id'])) {
    redirect('dashboard.php');
}

$attempt_id = $_GET['attempt_id'];

// Получение информации о попытке
$stmt = $pdo->prepare("
    SELECT ta.*, u.full_name, u.username, t.title, t.teacher_id 
    FROM test_attempts ta 
    JOIN users u ON ta.student_id = u.id 
    JOIN tests t ON ta.test_id = t.id 
    WHERE ta.id = ?
");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt || $attempt['teacher_id'] != $_SESSION['user_id']) {
    die('Попытка не найдена или у вас нет прав доступа');
}

/// Получение ответов студента
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

// Получаем текущие оценки для текстовых ответов
$text_scores = [];
$stmt = $pdo->prepare("
    SELECT question_id, manual_score 
    FROM student_answers 
    WHERE attempt_id = ? AND manual_score IS NOT NULL
");
$stmt->execute([$attempt_id]);
$scores_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($scores_data as $score) {
    $text_scores[$score['question_id']] = $score['manual_score'];
}

// Проверяем сообщение об успехе
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Функция для форматирования лога безопасности в понятный вид
function formatSecurityLog($log) {
    if (empty($log) || $log === 'null') {
        return '<div class="no-violations-message">
                    <div class="success-icon">✅</div>
                    <h3>Нарушений не обнаружено</h3>
                    <p>Студент прошел тест без нарушений правил</p>
                </div>';
    }
    
    // Если лог в формате JSON
    if (strpos($log, '{"timestamp"') !== false) {
        return parseJsonSecurityLog($log);
    }
    
    // Если лог в старом формате
    if (strpos($log, '[') !== false && strpos($log, ']') !== false) {
        return parseOldFormatSecurityLog($log);
    }
    
    // Если лог в новом формате с эмодзи
    if (strpos($log, '🕐') !== false) {
        return parseNewFormatSecurityLog($log);
    }
    
    // Если непонятный формат, показываем как есть
    return '<div class="security-event">' . nl2br(htmlspecialchars($log)) . '</div>';
}

// Функция для парсинга JSON формата лога
function parseJsonSecurityLog($log) {
    $events = [];
    $lines = explode("\n", trim($log));
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        // Пытаемся декодировать JSON
        $event = json_decode(trim($line), true);
        if (json_last_error() === JSON_ERROR_NONE && isset($event['timestamp'])) {
            $events[] = $event;
        }
    }
    
    if (empty($events)) {
        return '<div class="no-violations-message">Не удалось проанализировать лог безопасности</div>';
    }
    
    $html = '<div class="security-events">';
    foreach ($events as $event) {
        $time = date('d.m.Y H:i:s', strtotime($event['timestamp']));
        $description = '';
        $details = $event['details'] ?? '';
        
        switch ($event['event_type'] ?? '') {
            case 'tab_switch':
                $description = 'Студент переключился на другую вкладку браузера';
                break;
            case 'fullscreen_exit':
                $description = 'Студент вышел из полноэкранного режима';
                break;
            default:
                $description = $event['event_type'] ?? 'Неизвестное событие';
        }
        
        $html .= renderSecurityEvent($time, $description, $details);
    }
    $html .= '</div>';
    
    return $html;
}

// Функция для парсинга старого формата лога
function parseOldFormatSecurityLog($log) {
    $events = [];
    $lines = explode("\n", trim($log));
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        // Формат: [дата время] Событие
        if (preg_match('/\[([^\]]+)\]\s*(.+)/', $line, $matches)) {
            $events[] = [
                'time' => $matches[1],
                'description' => $matches[2]
            ];
        }
    }
    
    $html = '<div class="security-events">';
    foreach ($events as $event) {
        $html .= renderSecurityEvent($event['time'], $event['description']);
    }
    $html .= '</div>';
    
    return $html;
}

// Функция для парсинга нового формата с эмодзи
function parseNewFormatSecurityLog($log) {
    $lines = explode("\n", trim($log));
    $events = [];
    $current_event = [];
    
    foreach ($lines as $line) {
        if (strpos($line, '---') !== false) {
            if (!empty($current_event)) {
                $events[] = $current_event;
                $current_event = [];
            }
        } else {
            $current_event[] = $line;
        }
    }
    
    if (!empty($current_event)) {
        $events[] = $current_event;
    }
    
    $html = '<div class="security-events">';
    foreach ($events as $event) {
        $time = '';
        $description = '';
        $details = '';
        
        foreach ($event as $line) {
            if (strpos($line, '🕐') !== false) {
                $time = trim(str_replace('🕐', '', $line));
            } elseif (strpos($line, '📝') !== false) {
                $description = trim(str_replace('📝', '', $line));
            } elseif (strpos($line, '📋') !== false) {
                $details = trim(str_replace('📋 Дополнительно:', '', $line));
            }
        }
        
        if ($time && $description) {
            $html .= renderSecurityEvent($time, $description, $details);
        }
    }
    $html .= '</div>';
    
    return $html;
}

// Функция для отображения одного события безопасности
function renderSecurityEvent($time, $description, $details = '') {
    // Определяем уровень серьезности и иконку
    $icon = '⚠️';
    $severity = 'medium';
    $color = '#fff3cd';
    
    if (strpos($description, 'автоматически завершен') !== false) {
        $icon = '❌';
        $severity = 'high';
        $color = '#f8d7da';
    } elseif (strpos($description, 'инструменты разработчика') !== false) {
        $icon = '🔧';
        $severity = 'high';
        $color = '#f8d7da';
    } elseif (strpos($description, 'переключился на другую вкладку') !== false || strpos($description, 'Переключение на другую вкладку') !== false) {
        $icon = '🌐';
        $severity = 'medium';
        $color = '#fff3cd';
    } elseif (strpos($description, 'полноэкранного режима') !== false || strpos($description, 'Выход из полноэкранного режима') !== false) {
        $icon = '📱';
        $severity = 'low';
        $color = '#d1ecf1';
    } elseif (strpos($description, 'скопировать') !== false || strpos($description, 'вставить') !== false) {
        $icon = '📋';
        $severity = 'medium';
        $color = '#fff3cd';
    }
    
    $html = '<div class="security-event severity-' . $severity . '" style="background: ' . $color . ';">';
    $html .= '<div class="event-header">';
    $html .= '<span class="event-icon">' . $icon . '</span>';
    $html .= '<span class="event-time">' . htmlspecialchars($time) . '</span>';
    $html .= '</div>';
    $html .= '<div class="event-description">' . htmlspecialchars($description) . '</div>';
    
    if ($details) {
        $html .= '<div class="event-details">' . htmlspecialchars($details) . '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

// Функция для анализа и создания сводки по нарушениям
function createSecuritySummary($log, $violation_count) {
    if (empty($log) || $log === 'null') {
        return [
            'total' => 0,
            'by_type' => [],
            'summary' => 'Нарушений не зафиксировано'
        ];
    }
    
    $types = [
        'tab_switch' => ['count' => 0, 'name' => 'Переключения вкладок'],
        'fullscreen_exit' => ['count' => 0, 'name' => 'Выходы из полноэкранного режима'],
        'copy_paste' => ['count' => 0, 'name' => 'Попытки копирования/вставки'],
        'idle_timeout' => ['count' => 0, 'name' => 'Автозавершения из-за бездействия'],
        'dev_tools' => ['count' => 0, 'name' => 'Попытки открыть инструменты разработки'],
        'other' => ['count' => 0, 'name' => 'Другие нарушения']
    ];
    
    // Анализ по ключевым словам
    $log_lower = strtolower($log);
    
    if (strpos($log_lower, 'переключился') !== false || strpos($log_lower, 'переключение') !== false || strpos($log, 'tab_switch') !== false) {
        // Подсчитываем количество переключений
        $types['tab_switch']['count'] = substr_count($log_lower, 'переключился') + 
                                       substr_count($log_lower, 'переключение') +
                                       substr_count($log, 'tab_switch');
    }
    
    if (strpos($log_lower, 'полноэкранн') !== false || strpos($log_lower, 'fullscreen_exit') !== false) {
        $types['fullscreen_exit']['count'] = substr_count($log_lower, 'полноэкранн') + 
                                            substr_count($log, 'fullscreen_exit');
    }
    
    if (strpos($log_lower, 'скопировать') !== false || strpos($log_lower, 'вставить') !== false) {
        $types['copy_paste']['count'] = substr_count($log_lower, 'скопировать') + 
                                       substr_count($log_lower, 'вставить');
    }
    
    if (strpos($log_lower, 'автоматически завершен') !== false) {
        $types['idle_timeout']['count'] = substr_count($log_lower, 'автоматически завершен');
    }
    
    if (strpos($log_lower, 'инструменты разработчика') !== false) {
        $types['dev_tools']['count'] = substr_count($log_lower, 'инструменты разработчика');
    }
    
    // Если не нашли конкретных нарушений, но violation_count > 0
    $total_counted = array_sum(array_column($types, 'count'));
    if ($total_counted == 0 && $violation_count > 0) {
        $types['other']['count'] = $violation_count;
    } elseif ($total_counted > 0) {
        // Используем посчитанное количество вместо violation_count для точности
        $violation_count = $total_counted;
    }
    
    // Создаем текстовую сводку
    $summary_parts = [];
    foreach ($types as $type) {
        if ($type['count'] > 0) {
            $summary_parts[] = $type['name'] . ': ' . $type['count'];
        }
    }
    
    $summary = $summary_parts ? implode(', ', $summary_parts) : 'Незначительные нарушения';
    
    return [
        'total' => $violation_count,
        'by_type' => $types,
        'summary' => $summary
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Детали попытки - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php
    session_start();
    // Получаем сохраненные данные преподавателя
    $first_name = $_SESSION['teacher_first_name'] ?? 'Иван';
    $last_name = $_SESSION['teacher_last_name'] ?? 'Петров';
    $full_name = $first_name . ' ' . $last_name;
    $avatar = mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1);
    ?>
    
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                <a href="test_editor.php"><i class="fas fa-plus"></i> Создать тест</a>
                <a href="test_results.php"><i class="fas fa-chart-bar"></i> Статистика</a>
                <a href="students.php"><i class="fas fa-users"></i> Студенты</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Детали попытки: <?php echo htmlspecialchars($attempt['full_name']); ?></h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <div class="results-container">
                <?php if ($success_message): ?>
                    <div class="alert success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Информация о попытке</h3>
                        <a href="test_results.php?id=<?php echo $attempt['test_id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Назад к результатам
                        </a>
                    </div>
                    <div class="content-card">
                        <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div class="info-item">
                                <strong>Студент:</strong> <?php echo htmlspecialchars($attempt['full_name']); ?>
                            </div>
                            <div class="info-item">
                                <strong>Тест:</strong> <?php echo htmlspecialchars($attempt['title']); ?>
                            </div>
                            <div class="info-item">
                                <strong>Начало:</strong> <?php echo date('d.m.Y H:i:s', strtotime($attempt['started_at'])); ?>
                            </div>
                            <div class="info-item">
                                <strong>Завершение:</strong> 
                                <?php echo $attempt['finished_at'] ? date('d.m.Y H:i:s', strtotime($attempt['finished_at'])) : 'Не завершено'; ?>
                            </div>
                            <div class="info-item">
                                <strong>Результат:</strong> 
                                <span class="score <?php 
                                    $percentage = ($attempt['score'] / $attempt['max_score']) * 100;
                                    if ($percentage >= 90) echo 'perfect';
                                    elseif ($percentage >= 75) echo 'excellent';
                                    elseif ($percentage >= 60) echo 'good';
                                    else echo 'average';
                                ?>">
                                    <?php echo $attempt['score']; ?> / <?php echo $attempt['max_score']; ?> баллов
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>IP-адрес:</strong> <?php echo htmlspecialchars($attempt['ip_address']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Ответы студента</h3>
                    </div>
                    
                    <?php if (empty($answers_by_question)): ?>
                        <div class="content-card text-center">
                            <p>Ответы не найдены.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($answers_by_question as $question_id => $question_data): ?>
                            <div class="content-card question-review">
                                <h4>Вопрос</h4>
                                <p><?php echo nl2br(htmlspecialchars($question_data['question_text'])); ?></p>
                                <div class="question-meta" style="display: flex; gap: 20px; margin: 15px 0; color: var(--text-light);">
                                    <span><strong>Тип:</strong> 
                                        <?php 
                                        $type_names = [
                                            'single' => 'Одиночный выбор',
                                            'multiple' => 'Множественный выбор', 
                                            'text' => 'Текстовый ответ'
                                        ];
                                        echo $type_names[$question_data['question_type']];
                                        ?>
                                    </span>
                                    <span><strong>Баллы:</strong> <?php echo $question_data['points']; ?></span>
                                </div>
                                
                                <div class="student-answer">
                                    <h5>Ответ студента:</h5>
                                    <?php if ($question_data['question_type'] === 'text'): ?>
                                        <?php 
                                        $text_answer = '';
                                        $current_score = $text_scores[$question_id] ?? 0;
                                        
                                        if (!empty($question_data['answers'])) {
                                            $first_answer = $question_data['answers'][0];
                                            $text_answer = $first_answer['student_answer_text'] ?? 
                                                          $first_answer['answer_text'] ?? 
                                                          '';
                                        }
                                        ?>
                                        
                                        <div class="text-answer-container" style="margin: 15px 0;">
                                            <?php if (!empty($text_answer)): ?>
                                                <div class="text-answer-content" style="padding: 15px; background: var(--hover-bg); border-radius: var(--border-radius); border: var(--border);">
                                                    <?php echo nl2br(htmlspecialchars($text_answer)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="no-answer" style="padding: 20px; text-align: center; color: var(--gray); font-style: italic;">
                                                    <i class="fas fa-times-circle"></i> Студент не предоставил ответ на этот вопрос
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Форма для ручной оценки текстового ответа -->
                                        <div class="text-answer-grading">
                                            <form method="POST" action="grade_text_answer.php" class="grading-form">
                                                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                                                <input type="hidden" name="question_id" value="<?php echo $question_id; ?>">
                                                <div class="form-group" style="display: flex; align-items: center; gap: 15px; margin: 20px 0;">
                                                    <label for="manual_score_<?php echo $question_id; ?>" style="margin: 0; font-weight: 600;">
                                                        Оценка:
                                                    </label>
                                                    <input type="number" id="manual_score_<?php echo $question_id; ?>" 
                                                           name="manual_score" min="0" max="<?php echo $question_data['points']; ?>" 
                                                           value="<?php echo $current_score; ?>" 
                                                           style="width: 80px; padding: 8px 12px; border: 1px solid var(--light-gray); border-radius: var(--border-radius);">
                                                    <span style="color: var(--text-light);">из <?php echo $question_data['points']; ?> баллов</span>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 15px;">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-save"></i> Сохранить оценку
                                                    </button>
                                                    <?php if ($current_score > 0): ?>
                                                        <span style="color: var(--success); font-weight: 600;">
                                                            <i class="fas fa-check"></i> Текущая оценка: <?php echo $current_score; ?> баллов
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
                                        
                                    <?php else: ?>
                                        <div class="choice-answers" style="margin: 15px 0;">
                                            <ul style="list-style: none; padding: 0;">
                                                <?php foreach ($question_data['answers'] as $answer): ?>
                                                    <li style="padding: 12px; margin-bottom: 8px; background: var(--hover-bg); border-radius: var(--border-radius); border: 1px solid transparent; transition: all 0.3s ease;"
                                                        class="<?php echo $answer['is_correct'] ? 'correct-answer' : ''; ?>">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <?php if ($answer['is_correct']): ?>
                                                                <span style="color: var(--success);">
                                                                    <i class="fas fa-check-circle"></i>
                                                                </span>
                                                            <?php else: ?>
                                                                <span style="color: var(--gray);">
                                                                    <i class="far fa-circle"></i>
                                                                </span>
                                                            <?php endif; ?>
                                                            <span><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                                            <?php if ($answer['answer_is_correct']): ?>
                                                                <span class="correct-badge">
                                                                    <i class="fas fa-star"></i> Правильный ответ
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php
                $security_summary = createSecuritySummary($attempt['security_log'] ?? '', $attempt['violation_count'] ?? 0);
                ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-shield-alt"></i> Мониторинг соблюдения правил</h3>
                    </div>
                    
                    <div class="content-card">
                        <div class="security-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <div class="overview-card" style="background: linear-gradient(135deg, var(--primary-light), var(--primary)); color: white; padding: 20px; border-radius: var(--border-radius); text-align: center;">
                                <div class="overview-icon" style="font-size: 2em; margin-bottom: 10px;">📊</div>
                                <div class="overview-content">
                                    <div class="overview-number" style="font-size: 2em; font-weight: 700;"><?php echo $security_summary['total']; ?></div>
                                    <div class="overview-label">всего нарушений</div>
                                </div>
                            </div>
                            
                            <div class="overview-card" style="background: <?php 
                                if ($security_summary['total'] == 0) echo 'linear-gradient(135deg, var(--success), #10b981)';
                                elseif ($security_summary['total'] <= 2) echo 'linear-gradient(135deg, var(--warning), #f59e0b)';
                                else echo 'linear-gradient(135deg, var(--danger), #ef4444)';
                            ?>; color: white; padding: 20px; border-radius: var(--border-radius); text-align: center;">
                                <div class="overview-icon" style="font-size: 2em; margin-bottom: 10px;">
                                    <?php if ($security_summary['total'] == 0): ?>
                                        ✅
                                    <?php elseif ($security_summary['total'] <= 2): ?>
                                        ⚠️
                                    <?php else: ?>
                                        ❌
                                    <?php endif; ?>
                                </div>
                                <div class="overview-content">
                                    <div class="overview-text" style="font-size: 1.2em; font-weight: 600;">
                                        <?php if ($security_summary['total'] == 0): ?>
                                            Отлично
                                        <?php elseif ($security_summary['total'] <= 2): ?>
                                            Нормально
                                        <?php else: ?>
                                            Много нарушений
                                        <?php endif; ?>
                                    </div>
                                    <div class="overview-label">уровень</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="security-breakdown">
                            <h4><i class="fas fa-list"></i> Типы нарушений</h4>
                            <div class="breakdown-list" style="display: grid; gap: 10px; margin: 15px 0;">
                                <?php foreach ($security_summary['by_type'] as $type): ?>
                                    <?php if ($type['count'] > 0): ?>
                                        <div class="breakdown-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--hover-bg); border-radius: var(--border-radius);">
                                            <span class="breakdown-name"><?php echo $type['name']; ?></span>
                                            <span class="breakdown-count" style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600;"><?php echo $type['count']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <?php if ($security_summary['total'] == 0): ?>
                                    <div class="breakdown-item no-violations" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--success); color: white; border-radius: var(--border-radius);">
                                        <span class="breakdown-name">Нарушений не зафиксировано</span>
                                        <span class="breakdown-count">✅</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="content-card">
                        <h4><i class="fas fa-clipboard-list"></i> Журнал событий</h4>
                        <div class="events-container">
                            <?php echo formatSecurityLog($attempt['security_log'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>