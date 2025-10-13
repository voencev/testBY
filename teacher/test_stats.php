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
                            <span class="stat-value">8</span>
                            <span class="stat-label">Всего тестов</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">156</span>
                            <span class="stat-label">Всего студентов</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">78%</span>
                            <span class="stat-label">Средний балл</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">92%</span>
                            <span class="stat-label">Успеваемость</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>Топ студентов</h3>
                    <div class="results-list">
                        <p>Смирнова М.И. <span>96%</span></p>
                        <p>Петров А.С. <span>94%</span></p>
                        <p>Иванова Е.П. <span>92%</span></p>
                        <p>Козлов Д.В. <span>89%</span></p>
                        <p>Сидоров А.О. <span>87%</span></p>
                    </div>
                </div>

                <div class="card">
                    <h3>Активность по дням</h3>
                    <div class="activity-list">
                        <p>Понедельник <span>45 тестов</span></p>
                        <p>Вторник <span>38 тестов</span></p>
                        <p>Среда <span>52 теста</span></p>
                        <p>Четверг <span>41 тест</span></p>
                        <p>Пятница <span>29 тестов</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
