<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireTeacher();

// Получение тестов учителя
$stmt = $pdo->prepare("SELECT * FROM tests WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем данные преподавателя
$full_name = $_SESSION['full_name'] ?? 'Преподаватель';
$avatar = '';
if (!empty($full_name)) {
    $names = explode(' ', $full_name);
    $avatar = mb_substr($names[0] ?? '', 0, 1) . mb_substr($names[1] ?? '', 0, 1);
} else {
    $avatar = 'П';
}

// Подсчет статистики
$total_tests = count($tests);
$total_questions = 0;
$total_students = 0;

// Подсчитываем общее количество вопросов
foreach ($tests as $test) {
    // Получаем количество вопросов для каждого теста
    $stmt = $pdo->prepare("SELECT COUNT(*) as question_count FROM questions WHERE test_id = ?");
    $stmt->execute([$test['id']]);
    $question_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_questions += $question_data['question_count'] ?? 0;
}

// Рассчитываем процент для прогресс-круга (основано на количестве тестов)
$progress_percent = $total_tests > 0 ? min(($total_tests / 10) * 100, 100) : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет преподавателя - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Главная</a>
                <a href="create_test.php"><i class="fas fa-plus-circle"></i> Создать тест</a>
                <a href="test_stats.php"><i class="fas fa-chart-bar"></i> Статистика</a>
                <a href="students.php"><i class="fas fa-users"></i> Студенты</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Личный кабинет преподавателя</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h3>Мои тесты (<?php echo $total_tests; ?>)</h3>
                        <a href="create_test.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Создать тест
                        </a>
                    </div>
                    
                    <div class="tests-list">
                        <?php if (empty($tests)): ?>
                            <div class="no-tests">
                                <p>У вас пока нет созданных тестов.</p>
                                <a href="create_test.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i> Создать первый тест
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tests as $test): 
                                // Получаем количество вопросов для этого теста
                                $stmt = $pdo->prepare("SELECT COUNT(*) as question_count FROM questions WHERE test_id = ?");
                                $stmt->execute([$test['id']]);
                                $question_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                $question_count = $question_data['question_count'] ?? 0;
                                
                                // Определяем цвет карточки в зависимости от предмета
                                $subject_class = 'test-math';
                                $subject = strtolower($test['subject'] ?? '');
                                if (strpos($subject, 'история') !== false || strpos($subject, 'history') !== false) {
                                    $subject_class = 'test-history';
                                } elseif (strpos($subject, 'физика') !== false || strpos($subject, 'physics') !== false) {
                                    $subject_class = 'test-physics';
                                }
                            ?>
                            <div class="test-card-item <?php echo $subject_class; ?>">
                                <div class="test-info">
                                    <h4><?php echo htmlspecialchars($test['title']); ?></h4>
                                    <p><strong>Код теста:</strong> 
                                        <code style="background: rgba(255,255,255,0.3); padding: 2px 6px; border-radius: 4px; font-weight: bold;">
                                            <?php echo htmlspecialchars($test['test_code'] ?? 'НЕТ КОДА'); ?>
                                        </code>
                                    </p>
                                    <p><strong>Предмет:</strong> <?php echo htmlspecialchars($test['subject'] ?? 'Не указан'); ?></p>
                                    <p><strong>Описание:</strong> <?php echo htmlspecialchars($test['description']); ?></p>
                                    <p>
                                        <strong>Вопросов:</strong> <?php echo $question_count; ?> | 
                                        <strong>Макс. балл:</strong> <?php echo $test['max_score'] ?? ($question_count * 5); ?>
                                    </p>
                                    <p><strong>Создан:</strong> <?php echo date('d.m.Y H:i', strtotime($test['created_at'])); ?></p>
                                </div>
                                <div class="test-actions">
                                    <a href="test_editor.php?id=<?php echo $test['id']; ?>" class="btn-start">
                                        <i class="fas fa-edit"></i> Редактировать
                                    </a>
                                    <a href="test_results.php?id=<?php echo $test['id']; ?>" class="btn-start">
                                        <i class="fas fa-chart-bar"></i> Результаты
                                    </a>
                                    <button class="btn-start btn-copy" onclick="copyTestCode('<?php echo $test['test_code'] ?? ''; ?>')" title="Копировать код теста">
                                        <i class="fas fa-copy"></i> Код
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card progress-card">
                    <h3>Статистика</h3>
                    <div class="progress-circle" style="--progress: <?php echo $progress_percent; ?>%">
                        <span><?php echo $total_tests; ?></span>
                    </div>
                    <p><strong>Всего тестов:</strong> <?php echo $total_tests; ?></p>
                    <p><strong>Всего вопросов:</strong> <?php echo $total_questions; ?></p>
                    <p><strong>Активность:</strong> 
                        <?php 
                        if ($total_tests > 5) {
                            echo 'Высокая';
                        } elseif ($total_tests > 2) {
                            echo 'Средняя';
                        } else {
                            echo 'Начальный уровень';
                        }
                        ?>
                    </p>
                </div>

                <div class="card">
                    <h3>Последняя активность</h3>
                    <div class="activity-list">
                        <?php if (empty($tests)): ?>
                            <p>Активность отсутствует</p>
                        <?php else: ?>
                            <?php 
                            // Показываем последние 4 теста
                            $recent_tests = array_slice($tests, 0, 4);
                            foreach ($recent_tests as $test): 
                            ?>
                            <p>
                                Создан тест "<?php echo htmlspecialchars(mb_substr($test['title'], 0, 20) . (mb_strlen($test['title']) > 20 ? '...' : '')); ?>" 
                                <span><?php echo date('d.m.Y', strtotime($test['created_at'])); ?></span>
                            </p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h3>Быстрые действия</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
                        <a href="create_test.php" class="btn btn-primary btn-block">
                            <i class="fas fa-plus-circle"></i> Создать новый тест
                        </a>
                        <?php if ($total_tests > 0): ?>
                        <a href="test_stats.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-chart-bar"></i> Просмотреть статистику
                        </a>
                        <a href="students.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-users"></i> Управление студентами
                        </a>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-block" onclick="showNotification('Сначала создайте тест', 'info')">
                            <i class="fas fa-chart-bar"></i> Просмотреть статистику
                        </button>
                        <button class="btn btn-secondary btn-block" onclick="showNotification('Сначала создайте тест', 'info')">
                            <i class="fas fa-users"></i> Управление студентами
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .no-tests {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .no-tests p {
            margin-bottom: 20px;
            font-size: 1.1em;
        }

        .btn-copy {
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .btn-copy:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .tests-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
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
            background: conic-gradient(var(--primary) var(--progress, 0%), var(--light) 0%);
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
    </style>

    <script>
        function copyTestCode(code) {
            if (!code) {
                showNotification('Код теста отсутствует', 'error');
                return;
            }
            
            navigator.clipboard.writeText(code).then(function() {
                showNotification('Код теста скопирован: ' + code, 'success');
            }, function() {
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('Код теста скопирован: ' + code, 'success');
            });
        }

        function showNotification(message, type = 'info') {
            // Создаем уведомление
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
                color: white;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
                font-weight: 500;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            // Анимация появления
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Автоматическое скрытие
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Добавляем обработчики для карточек тестов
        document.addEventListener('DOMContentLoaded', function() {
            const testCards = document.querySelectorAll('.test-card-item');
            testCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('.test-actions')) {
                        const editLink = this.querySelector('a[href*="test_editor"]');
                        if (editLink) {
                            window.location.href = editLink.href;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>