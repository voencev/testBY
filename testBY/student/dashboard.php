<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireStudent();

// Получение попыток тестирования студента
$stmt = $pdo->prepare("
    SELECT ta.*, t.title, t.test_code 
    FROM test_attempts ta 
    JOIN tests t ON ta.test_id = t.id 
    WHERE ta.student_id = ? 
    ORDER BY ta.started_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет студента - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php
    session_start();
    
    // Получаем данные преподавателя
$full_name = $_SESSION['full_name'] ?? 'Студент';
$avatar = '';
if (!empty($full_name)) {
    $names = explode(' ', $full_name);
    $avatar = mb_substr($names[0] ?? '', 0, 1) . mb_substr($names[1] ?? '', 0, 1);
} else {
    $avatar = 'П';
}
    ?>
    
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Главная</a>
              
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Личный кабинет студента</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <div class="enter-test-section">
                <div class="content-card">
                    <h3>Войти в тест по коду</h3>
                    <form action="take_test.php" method="GET" class="enter-test-form">
                        <div class="form-group">
                            <label for="test_code">Код теста:</label>
                            <input type="text" id="test_code" name="code" required placeholder="Введите код теста">
                        </div>
                        <button type="submit" class="btn btn-primary">Начать тест</button>
                    </form>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card" style="grid-column: span 2;">
                    <h3>История тестирования</h3>
                    
                    <?php if (empty($attempts)): ?>
                        <div class="no-tests">
                            <p>Вы еще не проходили тесты.</p>
                            <p>Введите код теста выше, чтобы начать.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="results-table">
                                <thead>
                                    <tr>
                                        <th>Тест</th>
                                        <th>Код теста</th>
                                        <th>Дата начала</th>
                                        <th>Результат</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attempts as $attempt): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                            <td><?php echo $attempt['test_code']; ?></td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($attempt['started_at'])); ?></td>
                                            <td>
                                                <?php if ($attempt['finished_at']): ?>
                                                    <span class="score <?php 
                                                        $percentage = ($attempt['score'] / $attempt['max_score']) * 100;
                                                        if ($percentage >= 90) echo 'perfect';
                                                        elseif ($percentage >= 75) echo 'excellent';
                                                        elseif ($percentage >= 60) echo 'good';
                                                        else echo 'average';
                                                    ?>">
                                                        <?php echo $attempt['score']; ?> / <?php echo $attempt['max_score']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray);">Не завершено</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attempt['finished_at']): ?>
                                                    <span class="cheating-badge no-cheating">Завершено</span>
                                                <?php else: ?>
                                                    <a href="take_test.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn-start">
                                                        <i class="fas fa-play"></i> Продолжить
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card progress-card">
                    <h3>Ваш прогресс</h3>
                    <?php
                    // Рассчитываем общий прогресс
                    $completed_attempts = array_filter($attempts, function($attempt) {
                        return $attempt['finished_at'] !== null;
                    });
                    
                    $total_score = 0;
                    $max_total_score = 0;
                    
                    foreach ($completed_attempts as $attempt) {
                        $total_score += $attempt['score'];
                        $max_total_score += $attempt['max_score'];
                    }
                    
                    $average_score = $max_total_score > 0 ? ($total_score / $max_total_score) * 100 : 0;
                    ?>
                    <div class="progress-circle" style="background: conic-gradient(var(--primary) <?php echo $average_score; ?>%, var(--light) <?php echo $average_score; ?>%);">
                        <span><?php echo round($average_score); ?>%</span>
                    </div>
                    <p>Общий балл: <?php echo round($average_score); ?>/100</p>
                    <p>Завершено тестов: <?php echo count($completed_attempts); ?></p>
                </div>

                <div class="card">
                    <h3>Последние результаты</h3>
                    <div class="results-list">
                        <?php if (empty($completed_attempts)): ?>
                            <p>Пока нет завершенных тестов</p>
                        <?php else: ?>
                            <?php 
                            // Сортируем по дате завершения (последние первые)
                            usort($completed_attempts, function($a, $b) {
                                return strtotime($b['finished_at']) - strtotime($a['finished_at']);
                            });
                            
                            // Показываем последние 3 теста
                            $recent_attempts = array_slice($completed_attempts, 0, 3);
                            foreach ($recent_attempts as $attempt): 
                                $percentage = ($attempt['score'] / $attempt['max_score']) * 100;
                            ?>
                                <p>
                                    <?php echo htmlspecialchars($attempt['title']); ?> 
                                    <span style="background: <?php 
                                        if ($percentage >= 90) echo 'linear-gradient(135deg, #f0abfc, #c084fc)';
                                        elseif ($percentage >= 75) echo 'var(--success)';
                                        elseif ($percentage >= 60) echo 'var(--primary-light)';
                                        else echo 'var(--warning)';
                                    ?>; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.9em;">
                                        <?php echo round($percentage); ?>%
                                    </span>
                                </p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .no-tests {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }

        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 20px auto;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary);
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            background: var(--white);
            border-radius: 50%;
        }

        .progress-circle span {
            position: relative;
            z-index: 1;
        }

        .progress-card {
            text-align: center;
        }

        .progress-card p {
            font-size: 1.1em;
            color: var(--text-light);
            margin-top: 12px;
            font-weight: 500;
        }

        .results-list p {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--light-gray);
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .results-list p:hover {
            background: var(--hover-bg);
            border-radius: 6px;
            padding: 14px 12px;
            margin: 0 -12px;
        }

        .results-list p:last-child {
            border-bottom: none;
        }
    </style>

    <script>
        // Обработка формы входа по коду теста
        document.querySelector('.enter-test-form').addEventListener('submit', function(e) {
            const testCode = document.getElementById('test_code').value.trim();
            if (!testCode) {
                e.preventDefault();
                alert('Пожалуйста, введите код теста');
                return false;
            }
        });
    </script>
</body>
</html>