<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика тестов - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php
    session_start();
    require_once '../includes/config.php';
    
    // Проверяем авторизацию и роль преподавателя
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
        header('Location: ../login.php');
        exit();
    }
    
    // Получаем данные преподавателя
    $teacher_id = $_SESSION['user_id'];
    $full_name = $_SESSION['full_name'] ?? 'Преподаватель';
    $avatar = '';
    if (!empty($full_name)) {
        $names = explode(' ', $full_name);
        $avatar = mb_substr($names[0] ?? '', 0, 1) . mb_substr($names[1] ?? '', 0, 1);
    } else {
        $avatar = 'П';
    }

    // Получаем общую статистику
    try {
        // Общее количество тестов
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tests WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Количество уникальных студентов
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ta.student_id) as total 
            FROM test_attempts ta 
            JOIN tests t ON ta.test_id = t.id 
            WHERE t.teacher_id = ?
        ");
        $stmt->execute([$teacher_id]);
        $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Общее количество попыток
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM test_attempts ta 
            JOIN tests t ON ta.test_id = t.id 
            WHERE t.teacher_id = ?
        ");
        $stmt->execute([$teacher_id]);
        $total_attempts = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Средний балл и успеваемость
        $stmt = $pdo->prepare("
            SELECT 
                AVG(ta.score) as avg_score,
                AVG(ta.max_score) as avg_max_score,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN ta.score / ta.max_score >= 0.6 THEN 1 ELSE 0 END) as successful_attempts
            FROM test_attempts ta 
            JOIN tests t ON ta.test_id = t.id 
            WHERE t.teacher_id = ? AND ta.finished_at IS NOT NULL AND ta.max_score > 0
        ");
        $stmt->execute([$teacher_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $average_score = 0;
        $success_rate = 0;
        
        if ($result && $result['total_attempts'] > 0) {
            $average_score = round($result['avg_score'], 1);
            $success_rate = round(($result['successful_attempts'] / $result['total_attempts']) * 100, 1);
        }

        // Статистика по тестам
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.title,
                t.test_code,
                t.created_at,
                COUNT(ta.id) as attempt_count,
                AVG(ta.score) as avg_score,
                AVG(ta.max_score) as avg_max_score,
                COUNT(DISTINCT ta.student_id) as unique_students
            FROM tests t 
            LEFT JOIN test_attempts ta ON t.id = ta.test_id 
            WHERE t.teacher_id = ?
            GROUP BY t.id, t.title, t.test_code, t.created_at
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$teacher_id]);
        $tests_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Последние попытки
        $stmt = $pdo->prepare("
            SELECT 
                ta.score,
                ta.max_score,
                ta.finished_at,
                t.title as test_title,
                u.full_name as student_name
            FROM test_attempts ta 
            JOIN tests t ON ta.test_id = t.id 
            JOIN users u ON ta.student_id = u.id 
            WHERE t.teacher_id = ? AND ta.finished_at IS NOT NULL
            ORDER BY ta.finished_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$teacher_id]);
        $recent_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Ошибка получения статистики: " . $e->getMessage());
        $total_tests = 0;
        $total_students = 0;
        $total_attempts = 0;
        $average_score = 0;
        $success_rate = 0;
        $tests_stats = [];
        $recent_attempts = [];
    }
    ?>
    
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                <a href="create_test.php"><i class="fas fa-plus-circle"></i> Создать тест</a>
                <a href="test_stats.php" class="active"><i class="fas fa-chart-bar"></i> Статистика</a>
                <a href="students.php"><i class="fas fa-users"></i> Студенты</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Статистика тестов</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h3>Общая статистика</h3>
                    </div>
                    <div class="test-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $total_tests; ?></span>
                            <span class="stat-label">Всего тестов</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $total_students; ?></span>
                            <span class="stat-label">Всего студентов</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $total_attempts; ?></span>
                            <span class="stat-label">Всего попыток</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $average_score; ?></span>
                            <span class="stat-label">Средний балл</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $success_rate; ?>%</span>
                            <span class="stat-label">Успеваемость</span>
                        </div>
                    </div>
                </div>

                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h3>Статистика по тестам</h3>
                    </div>
                    <div class="card-content">
                        <?php if (count($tests_stats) > 0): ?>
                            <div class="tests-list">
                                <?php foreach ($tests_stats as $test): ?>
                                    <div class="test-row">
                                        <div class="test-info">
                                            <h4><?php echo htmlspecialchars($test['title']); ?></h4>
                                            <div class="test-meta">
                                                <span>Код: <strong><?php echo $test['test_code']; ?></strong></span>
                                                <span>Создан: <?php echo date('d.m.Y', strtotime($test['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="test-numbers">
                                            <div class="test-stat">
                                                <span class="stat-number"><?php echo $test['attempt_count']; ?></span>
                                                <span class="stat-label">попыток</span>
                                            </div>
                                            <div class="test-stat">
                                                <span class="stat-number"><?php echo $test['unique_students']; ?></span>
                                                <span class="stat-label">студентов</span>
                                            </div>
                                            <div class="test-stat">
                                                <?php if ($test['attempt_count'] > 0 && $test['avg_max_score'] > 0): ?>
                                                    <?php 
                                                    $avg_percentage = round(($test['avg_score'] / $test['avg_max_score']) * 100);
                                                    $score_display = round($test['avg_score'], 1);
                                                    ?>
                                                    <span class="stat-number"><?php echo $avg_percentage; ?>%</span>
                                                    <span class="stat-label">средний балл (<?php echo $score_display; ?>)</span>
                                                <?php else: ?>
                                                    <span class="stat-number">-</span>
                                                    <span class="stat-label">нет данных</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-bar"></i>
                                <p>У вас пока нет тестов</p>
                                <a href="create_test.php" class="btn btn-primary">Создать первый тест</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Последние попытки</h3>
                    </div>
                    <div class="card-content">
                        <?php if (count($recent_attempts) > 0): ?>
                            <div class="attempts-list">
                                <?php foreach ($recent_attempts as $attempt): ?>
                                    <div class="attempt-item">
                                        <div class="attempt-info">
                                            <div class="student-name"><?php echo htmlspecialchars($attempt['student_name']); ?></div>
                                            <div class="test-name"><?php echo htmlspecialchars($attempt['test_title']); ?></div>
                                            <div class="attempt-date"><?php echo date('d.m.Y H:i', strtotime($attempt['finished_at'])); ?></div>
                                        </div>
                                        <div class="attempt-result">
                                            <?php 
                                            $percentage = $attempt['max_score'] > 0 ? round(($attempt['score'] / $attempt['max_score']) * 100) : 0;
                                            $score_class = $percentage >= 60 ? 'score-good' : 'score-bad';
                                            ?>
                                            <span class="score <?php echo $score_class; ?>"><?php echo $percentage; ?>%</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>Нет завершенных попыток</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .test-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .stat-value {
            display: block;
            font-size: 2em;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .tests-list {
            padding: 10px 0;
        }
        
        .test-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        .test-row:hover {
            background-color: #f8f9fa;
        }
        
        .test-row:last-child {
            border-bottom: none;
        }
        
        .test-info h4 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 1.1em;
        }
        
        .test-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #666;
        }
        
        .test-numbers {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .test-stat {
            text-align: center;
            min-width: 80px;
        }
        
        .stat-number {
            display: block;
            font-size: 1.3em;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 2px;
        }
        
        .attempts-list {
            padding: 10px 0;
        }
        
        .attempt-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .attempt-item:last-child {
            border-bottom: none;
        }
        
        .student-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .test-name {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 3px;
        }
        
        .attempt-date {
            font-size: 0.8em;
            color: #999;
        }
        
        .score {
            font-weight: bold;
            font-size: 1.1em;
            padding: 5px 10px;
            border-radius: 15px;
        }
        
        .score-good {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .score-bad {
            background: #ffebee;
            color: #c62828;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .empty-state p {
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        
        @media (max-width: 768px) {
            .test-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .test-numbers {
                width: 100%;
                justify-content: space-between;
            }
            
            .test-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</body>
</html>