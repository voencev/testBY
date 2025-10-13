<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireTeacher();

// Получаем данные преподавателя
$full_name = $_SESSION['full_name'] ?? 'Преподаватель';
$avatar = '';
if (!empty($full_name)) {
    $names = explode(' ', $full_name);
    $avatar = mb_substr($names[0] ?? '', 0, 1) . mb_substr($names[1] ?? '', 0, 1);
} else {
    $avatar = 'П';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $time_limit = $_POST['time_limit'];
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $shuffle_answers = isset($_POST['shuffle_answers']) ? 1 : 0;
    $show_results = isset($_POST['show_results']) ? 1 : 0;
    
    // Настройки безопасности
    $security_settings = [
        'disable_copy_paste' => isset($_POST['disable_copy_paste']),
        'detect_tab_switch' => isset($_POST['detect_tab_switch']),
        'fullscreen_required' => isset($_POST['fullscreen_required']),
        'idle_detection' => isset($_POST['idle_detection'])
    ];
    
    // Генерация уникального кода теста
    $test_code = generateTestCode();
    
    try {
        // Используем только существующие столбцы из вашей базы данных
        $stmt = $pdo->prepare("INSERT INTO tests (title, description, teacher_id, test_code, time_limit, shuffle_questions, shuffle_answers, show_results, security_settings) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $title, $description, $_SESSION['user_id'], 
            $test_code, $time_limit, $shuffle_questions, 
            $shuffle_answers, $show_results, json_encode($security_settings)
        ]);
        
        $test_id = $pdo->lastInsertId();
        
        // Сохраняем код теста для показа
        $_SESSION['last_test_code'] = $test_code;
        
        // Перенаправление на редактор теста
        redirect("test_editor.php?id=$test_id");
        
    } catch (PDOException $e) {
        // Логируем ошибку
        error_log("Database error in create_test.php: " . $e->getMessage());
        $_SESSION['error'] = "Ошибка при создании теста. Пожалуйста, попробуйте еще раз.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание теста - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                <a href="create_test.php" class="active"><i class="fas fa-plus-circle"></i> Создать тест</a>
                <a href="test_stats.php"><i class="fas fa-chart-bar"></i> Статистика</a>
                <a href="students.php"><i class="fas fa-users"></i> Студенты</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Создание нового теста</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <div class="content-card">
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <strong>Ошибка!</strong> <?php echo $_SESSION['error']; ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['last_test_code'])): ?>
                <div class="alert alert-success">
                    <strong>Тест создан успешно!</strong> Код теста: 
                    <code style="background: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold; margin: 0 8px;">
                        <?php echo $_SESSION['last_test_code']; ?>
                    </code>
                    <button onclick="copyToClipboard('<?php echo $_SESSION['last_test_code']; ?>')" class="btn btn-sm" style="margin-left: 10px;">
                        <i class="fas fa-copy"></i> Копировать код
                    </button>
                    <?php unset($_SESSION['last_test_code']); ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="test-form" id="createTestForm">
                    <div class="form-group">
                        <label for="title"><i class="fas fa-heading"></i> Название теста:</label>
                        <input type="text" id="title" name="title" required 
                               placeholder="Введите название теста"
                               value="<?php echo $_POST['title'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Описание теста:</label>
                        <textarea id="description" name="description" rows="3" 
                                  placeholder="Опишите тест для студентов"><?php echo $_POST['description'] ?? ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="time_limit"><i class="fas fa-clock"></i> Ограничение по времени (минут):</label>
                            <input type="number" id="time_limit" name="time_limit" min="0" 
                                   placeholder="0 = без ограничения"
                                   value="<?php echo $_POST['time_limit'] ?? 0; ?>">
                        </div>
                    </div>

                    <h3><i class="fas fa-cogs"></i> Настройки теста</h3>
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="shuffle_questions" value="1" 
                                   <?php echo isset($_POST['shuffle_questions']) ? 'checked' : 'checked'; ?>>
                            Перемешивать вопросы
                        </label>
                        
                        <label>
                            <input type="checkbox" name="shuffle_answers" value="1" 
                                   <?php echo isset($_POST['shuffle_answers']) ? 'checked' : 'checked'; ?>>
                            Перемешивать ответы
                        </label>
                        
                        <label>
                            <input type="checkbox" name="show_results" value="1" 
                                   <?php echo isset($_POST['show_results']) || !isset($_POST['title']) ? 'checked' : ''; ?>>
                            Показывать результаты после завершения
                        </label>
                    </div>
                    
                    <h3><i class="fas fa-shield-alt"></i> Настройки безопасности</h3>
                    <div class="security-settings">
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="disable_copy_paste" value="1" 
                                       <?php echo isset($_POST['disable_copy_paste']) || !isset($_POST['title']) ? 'checked' : ''; ?>>
                                Запретить копирование/вставку
                            </label>
                            
                            <label>
                                <input type="checkbox" name="detect_tab_switch" value="1" 
                                       <?php echo isset($_POST['detect_tab_switch']) || !isset($_POST['title']) ? 'checked' : ''; ?>>
                                Обнаруживать переключение вкладок
                            </label>
                            
                            <label>
                                <input type="checkbox" name="fullscreen_required" value="1" 
                                       <?php echo isset($_POST['fullscreen_required']) ? 'checked' : ''; ?>>
                                Требовать полноэкранный режим
                            </label>
                            
                            <label>
                                <input type="checkbox" name="idle_detection" value="1" 
                                       <?php echo isset($_POST['idle_detection']) || !isset($_POST['title']) ? 'checked' : ''; ?>>
                                Обнаруживать бездействие
                            </label>
                        </div>
                        
                        <div class="security-recommendations">
                            <h4><i class="fas fa-lightbulb"></i> Рекомендации по защите от списывания:</h4>
                            <ul>
                                <li>Используйте вопросы с открытым ответом вместо множественного выбора</li>
                                <li>Создавайте индивидуальные варианты заданий</li>
                                <li>Ограничивайте время выполнения теста</li>
                                <li>Включайте вопросы, требующие развернутого ответа</li>
                                <li>Используйте случайный порядок вопросов и ответов</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Назад к списку тестов
                        </a>
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-plus-circle"></i> Создать тест и перейти к добавлению вопросов
                        </button>
                    </div>
                </form>
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
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .security-settings {
            background: var(--light);
            padding: 28px;
            border-radius: var(--border-radius);
            margin: 24px 0;
            border-left: 4px solid var(--primary);
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--light-gray);
        }

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                gap: 16px;
            }
            
            .form-actions .btn {
                width: 100%;
            }
        }
    </style>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('Код теста скопирован: ' + text, 'success');
            }, function() {
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('Код теста скопирован: ' + text, 'success');
            });
        }

        function showNotification(message, type = 'info') {
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
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Валидация формы
        document.getElementById('createTestForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            
            if (!title) {
                e.preventDefault();
                showNotification('Пожалуйста, введите название теста', 'error');
                document.getElementById('title').focus();
                return false;
            }

            // Показываем сообщение о загрузке
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Создание теста...';
            submitBtn.disabled = true;
            
            // Восстанавливаем кнопку через 5 секунд на случай ошибки
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Автофокус на поле названия
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('title').focus();
            
            // Добавляем подсказки для полей
            const fields = [
                { id: 'title', hint: 'Краткое и понятное название теста' },
                { id: 'time_limit', hint: '0 - без ограничения времени' }
            ];
            
            fields.forEach(field => {
                const element = document.getElementById(field.id);
                if (element) {
                    element.title = field.hint;
                }
            });
        });

        // Динамическое обновление рекомендаций безопасности
        document.addEventListener('DOMContentLoaded', function() {
            const securityCheckboxes = document.querySelectorAll('input[name^="disable_"], input[name^="detect_"], input[name="fullscreen_required"], input[name="idle_detection"]');
            
            securityCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSecurityLevel);
            });
            
            function updateSecurityLevel() {
                const enabledCount = Array.from(securityCheckboxes).filter(cb => cb.checked).length;
                const securityLevel = document.createElement('div');
                securityLevel.className = 'security-level';
                securityLevel.style.cssText = 'margin-top: 15px; padding: 10px; border-radius: 4px; font-weight: 500;';
                
                let existingLevel = document.querySelector('.security-level');
                if (existingLevel) {
                    existingLevel.remove();
                }
                
                if (enabledCount >= 3) {
                    securityLevel.style.backgroundColor = '#d4edda';
                    securityLevel.style.color = '#155724';
                    securityLevel.innerHTML = '<i class="fas fa-shield-alt"></i> Высокий уровень безопасности';
                } else if (enabledCount >= 2) {
                    securityLevel.style.backgroundColor = '#fff3cd';
                    securityLevel.style.color = '#856404';
                    securityLevel.innerHTML = '<i class="fas fa-shield"></i> Средний уровень безопасности';
                } else {
                    securityLevel.style.backgroundColor = '#f8d7da';
                    securityLevel.style.color = '#721c24';
                    securityLevel.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Низкий уровень безопасности';
                }
                
                document.querySelector('.security-settings').appendChild(securityLevel);
            }
            
            // Инициализируем уровень безопасности
            updateSecurityLevel();
        });
    </script>
</body>
</html>