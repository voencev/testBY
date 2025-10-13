<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireTeacher();

if (!isset($_GET['id'])) {
    redirect('dashboard.php');
}

$test_id = $_GET['id'];

// Проверка прав доступа к тесту
$stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? AND teacher_id = ?");
$stmt->execute([$test_id, $_SESSION['user_id']]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die('Тест не найден или у вас нет прав доступа');
}

// Получение попыток прохождения теста
$stmt = $pdo->prepare("
    SELECT ta.*, u.full_name, u.username 
    FROM test_attempts ta 
    JOIN users u ON ta.student_id = u.id 
    WHERE ta.test_id = ? 
    ORDER BY ta.started_at DESC
");
$stmt->execute([$test_id]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_attempts,
        AVG(ta.score) as avg_score,
        MAX(ta.score) as max_score,
        MIN(ta.score) as min_score
    FROM test_attempts ta 
    WHERE ta.test_id = ? AND ta.finished_at IS NOT NULL
");
$stmt->execute([$test_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Получение вопросов для детального просмотра
$stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ?");
$stmt->execute([$test_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка удаления пустых попыток
if (isset($_POST['delete_empty_attempts'])) {
    $stmt = $pdo->prepare("
        DELETE ta FROM test_attempts ta 
        LEFT JOIN student_answers sa ON ta.id = sa.attempt_id 
        WHERE ta.test_id = ? AND ta.finished_at IS NULL AND sa.id IS NULL
    ");
    $stmt->execute([$test_id]);
    $deleted_count = $stmt->rowCount();
    
    if ($deleted_count > 0) {
        $success_message = "Удалено $deleted_count пустых попыток";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты теста - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php
    session_start();
    // Получаем данные преподавателя
$full_name = $_SESSION['full_name'] ?? 'Преподаватель';
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
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                <a href="test_editor.php"><i class="fas fa-plus"></i> Создать тест</a>
                <a href="test_results.php"><i class="fas fa-chart-bar"></i> Статистика</a>
                <a href="students.php"><i class="fas fa-users"></i> Студенты</a>
              
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Результаты теста: <?php echo htmlspecialchars($test['title']); ?></h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <div class="results-container">
                <div class="card">
                    <div class="card-header">
                        <h3>Статистика теста</h3>
                        
                    </div>
                    <div class="test-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['total_attempts'] ?? 0; ?></span>
                            <span class="stat-label">Всего попыток</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo round($stats['avg_score'] ?? 0, 1); ?>%</span>
                            <span class="stat-label">Средний балл</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['max_score'] ?? 0; ?></span>
                            <span class="stat-label">Максимум баллов</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['min_score'] ?? 0; ?></span>
                            <span class="stat-label">Минимум баллов</span>
                        </div>
                    </div>
                </div>

                

                <div class="card">
                    <div class="card-header">
                        <h3>Результаты студентов</h3>
                        <div class="filters">
                            <input type="text" placeholder="Поиск по ФИО..." class="search-input">
                            <select class="filter-select">
                                <option value="">Все результаты</option>
                                <option value="finished">Завершенные</option>
                                <option value="in_progress">В процессе</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>ФИО студента</th>
                                    <th>Начало теста</th>
                                    <th>Завершение теста</th>
                                    <th>IP адрес</th>
                                    <th>Балл</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attempts)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px;">
                                            Тест еще никто не проходил.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($attempts as $attempt): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($attempt['full_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($attempt['username']); ?></small>
                                            </td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($attempt['started_at'])); ?></td>
                                            <td>
                                                <?php if ($attempt['finished_at']): ?>
                                                    <?php echo date('d.m.Y H:i', strtotime($attempt['finished_at'])); ?>
                                                <?php else: ?>
                                                    <span style="color: orange;">Не завершено</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                            <td>
                                                <?php if ($attempt['finished_at']): ?>
                                                    <?php 
                                                    $percentage = round(($attempt['score'] / $attempt['max_score']) * 100, 1);
                                                    $score_class = 'score ';
                                                    if ($percentage >= 90) $score_class .= 'perfect';
                                                    elseif ($percentage >= 75) $score_class .= 'excellent';
                                                    elseif ($percentage >= 60) $score_class .= 'good';
                                                    else $score_class .= 'average';
                                                    ?>
                                                    <span class="<?php echo $score_class; ?>">
                                                        <?php echo $attempt['score']; ?>/<?php echo $attempt['max_score']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attempt['finished_at']): ?>
                                                    <span class="cheating-badge no-cheating">Завершено</span>
                                                <?php else: ?>
                                                    <span class="cheating-badge cheating">В процессе</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="attempt_details.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn-action" title="Подробнее">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <div class="pagination">
                            <button class="btn-pagination" disabled>Назад</button>
                            <span class="page-info">Страница 1 из 1</span>
                            <button class="btn-pagination" disabled>Вперед</button>
                        </div>
                        <div class="results-count">
                            Показано <?php echo count($attempts); ?> из <?php echo count($attempts); ?> записей
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>