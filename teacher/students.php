<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление студентами - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php
    session_start();
    
    // Подключение к базе данных
    $host = '127.0.0.1:3306';
    $dbname = 'testby';
    $username = 'root'; // замените на вашего пользователя
    $password = ''; // замените на ваш пароль
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Ошибка подключения к базе данных: " . $e->getMessage());
    }
    
    // Получаем данные преподавателя
    $full_name = $_SESSION['full_name'] ?? 'Преподаватель';
    $avatar = '';
    if (!empty($full_name)) {
        $names = explode(' ', $full_name);
        $avatar = mb_substr($names[0] ?? '', 0, 1) . mb_substr($names[1] ?? '', 0, 1);
    } else {
        $avatar = 'П';
    }
    

    // Получаем всех студентов из базы данных
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY full_name");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем статистику по студентам
    $student_stats = [];
    foreach ($students as $student) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(ta.id) as total_attempts,
                AVG(ta.score / ta.max_score * 100) as avg_score,
                MAX(ta.finished_at) as last_activity
            FROM test_attempts ta 
            WHERE ta.student_id = ?
        ");
        $stmt->execute([$student['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $student_stats[$student['id']] = [
            'total_attempts' => $stats['total_attempts'] ?? 0,
            'avg_score' => $stats['avg_score'] ? round($stats['avg_score'], 1) : 0,
            'last_activity' => $stats['last_activity'] ? date('d.m.Y H:i', strtotime($stats['last_activity'])) : 'Нет активности'
        ];
    }

    // Обработка добавления нового студента
    if ($_POST && isset($_POST['add_student'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, 'student')");
            $stmt->execute([$username, $password, $email, $full_name]);
            
            $_SESSION['student_added'] = true;
            header("Location: students.php");
            exit();
        } catch(PDOException $e) {
            $error_message = "Ошибка при добавлении студента: " . $e->getMessage();
        }
    }

    // Обработка удаления студента
    if (isset($_GET['delete_id'])) {
        $delete_id = $_GET['delete_id'];
        
        try {
            // Проверяем, есть ли у студента попытки тестов
            $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM test_attempts WHERE student_id = ?");
            $stmt->execute([$delete_id]);
            $attempt_count = $stmt->fetch(PDO::FETCH_ASSOC)['attempt_count'];
            
            if ($attempt_count > 0) {
                $_SESSION['delete_error'] = "Нельзя удалить студента, у которого есть попытки тестов";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
                $stmt->execute([$delete_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['student_deleted'] = true;
                }
            }
            
            header("Location: students.php");
            exit();
        } catch(PDOException $e) {
            $_SESSION['delete_error'] = "Ошибка при удалении студента: " . $e->getMessage();
            header("Location: students.php");
            exit();
        }
    }

    // Сообщения об успешных операциях
    $success_message = '';
    $error_message = '';
    
    if (isset($_SESSION['student_added']) && $_SESSION['student_added']) {
        $success_message = 'Студент успешно добавлен!';
        unset($_SESSION['student_added']);
    }
    if (isset($_SESSION['student_deleted']) && $_SESSION['student_deleted']) {
        $success_message = 'Студент успешно удален!';
        unset($_SESSION['student_deleted']);
    }
    if (isset($_SESSION['delete_error'])) {
        $error_message = $_SESSION['delete_error'];
        unset($_SESSION['delete_error']);
    }
    ?>
    
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                <a href="create_test.php"><i class="fas fa-plus-circle"></i> Создать тест</a>
                <a href="test_stats.php"><i class="fas fa-chart-bar"></i> Статистика</a>
                <a href="students.php" class="active"><i class="fas fa-users"></i> Студенты</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Управление студентами</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <div class="content-card">
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>

                <div class="card-header">
                    <h3>Список студентов</h3>
                    <div class="filters">
                        <input type="text" id="searchInput" placeholder="Поиск студента..." class="search-input">
                        <button class="btn btn-primary" onclick="openAddStudentModal()">
                            <i class="fas fa-plus"></i> Добавить студента
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="results-table" id="studentsTable">
                        <thead>
                            <tr>
                                <th>ФИО</th>
                                <th>Логин</th>
                                <th>Email</th>
                                <th>Попытки тестов</th>
                                <th>Средний балл</th>
                                <th>Последняя активность</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $student): ?>
                                    <?php $stats = $student_stats[$student['id']] ?? ['total_attempts' => 0, 'avg_score' => 0, 'last_activity' => 'Нет активности']; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo $stats['total_attempts']; ?></td>
                                        <td>
                                            <?php 
                                            $progress_class = '';
                                            $avg_score = $stats['avg_score'];
                                            if ($avg_score >= 90) $progress_class = 'excellent';
                                            elseif ($avg_score >= 75) $progress_class = 'good';
                                            elseif ($avg_score >= 60) $progress_class = 'average';
                                            else $progress_class = 'poor';
                                            ?>
                                            <span class="score <?php echo $progress_class; ?>">
                                                <?php echo $avg_score > 0 ? $avg_score . '%' : 'Нет данных'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $stats['last_activity']; ?></td>
                                        <td>
                                            
                                            <button class="btn-action btn-danger" title="Удалить" onclick="deleteStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px;">
                                        <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                                        <p>Студенты не найдены</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно добавления студента -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Добавить нового студента</h3>
                <span class="close" onclick="closeAddStudentModal()">&times;</span>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="add_student" value="1">
                <div class="form-group">
                    <label for="full_name">ФИО студента:</label>
                    <input type="text" id="full_name" name="full_name" required placeholder="Введите ФИО студента">
                </div>
                <div class="form-group">
                    <label for="username">Логин:</label>
                    <input type="text" id="username" name="username" required placeholder="Введите логин">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required placeholder="student@edu.ru">
                </div>
                <div class="form-group">
                    <label for="password">Пароль:</label>
                    <input type="password" id="password" name="password" required placeholder="Введите пароль">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddStudentModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить студента</button>
                </div>
            </form>
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

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        /* Стили для модального окна */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #2d3748;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #000;
        }

        .modal-form {
            padding: 20px;
        }

        .score.poor {
            background-color: #e74c3c;
        }

        .score.average {
            background-color: #f39c12;
        }

        .score.good {
            background-color: #27ae60;
        }

        .score.excellent {
            background-color: #2ecc71;
        }
    </style>

    <script>
        // Функции для модального окна
        function openAddStudentModal() {
            document.getElementById('addStudentModal').style.display = 'block';
        }

        function closeAddStudentModal() {
            document.getElementById('addStudentModal').style.display = 'none';
        }

        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('addStudentModal');
            if (event.target == modal) {
                closeAddStudentModal();
            }
        }

        // Функция удаления студента
        function deleteStudent(studentId) {
            if (confirm('Вы уверены, что хотите удалить этого студента?')) {
                window.location.href = 'students.php?delete_id=' + studentId;
            }
        }


        // Поиск студентов
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('studentsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) { // -1 чтобы исключить колонку действий
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(searchText)) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
    </script>
</body>
</html>