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

// Получение вопросов теста
$stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY id");
$stmt->execute([$test_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение ответов для каждого вопроса
foreach ($questions as &$question) {
    $stmt = $pdo->prepare("SELECT * FROM answers WHERE question_id = ?");
    $stmt->execute([$question['id']]);
    $question['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($question);

// Получение данных вопроса для редактирования
$editing_question = null;
if (isset($_GET['edit_question'])) {
    $edit_question_id = $_GET['edit_question'];
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ? AND test_id = ?");
    $stmt->execute([$edit_question_id, $test_id]);
    $editing_question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($editing_question) {
        $stmt = $pdo->prepare("SELECT * FROM answers WHERE question_id = ?");
        $stmt->execute([$edit_question_id]);
        $editing_question['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Обработка добавления нового вопроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $points = intval($_POST['points']);
    
    if (!empty($question_text)) {
        $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text, question_type, points) VALUES (?, ?, ?, ?)");
        $stmt->execute([$test_id, $question_text, $question_type, $points]);
        
        $question_id = $pdo->lastInsertId();
        
        // Добавление ответов только для вопросов с выбором
        if ($question_type !== 'text') {
            $answers = $_POST['answers'] ?? [];
            $correct_answers = isset($_POST['correct_answers']) ? $_POST['correct_answers'] : [];
            
            foreach ($answers as $index => $answer_text) {
                if (!empty(trim($answer_text))) {
                    $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, trim($answer_text), $is_correct]);
                }
            }
        }
        
        $_SESSION['question_added'] = true;
        redirect("test_editor.php?id=$test_id");
    } else {
        $error_message = "Текст вопроса не может быть пустым";
    }
}

// Обработка обновления вопроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
    $question_id = $_POST['question_id'];
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $points = intval($_POST['points']);
    
    if (!empty($question_text)) {
        // Обновляем вопрос
        $stmt = $pdo->prepare("UPDATE questions SET question_text = ?, question_type = ?, points = ? WHERE id = ? AND test_id = ?");
        $stmt->execute([$question_text, $question_type, $points, $question_id, $test_id]);
        
        // Удаляем старые ответы (если тип изменился)
        $stmt = $pdo->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$question_id]);
        
        // Добавляем новые ответы только для вопросов с выбором
        if ($question_type !== 'text') {
            $answers = $_POST['answers'] ?? [];
            $correct_answers = isset($_POST['correct_answers']) ? $_POST['correct_answers'] : [];
            
            foreach ($answers as $index => $answer_text) {
                if (!empty(trim($answer_text))) {
                    $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, trim($answer_text), $is_correct]);
                }
            }
        }
        
        $_SESSION['question_updated'] = true;
        redirect("test_editor.php?id=$test_id");
    } else {
        $error_message = "Текст вопроса не может быть пустым";
    }
}

// Обработка сохранения и возврата
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_and_return'])) {
    redirect("dashboard.php");
}

// Обработка удаления вопроса
if (isset($_GET['delete_question'])) {
    $question_id = $_GET['delete_question'];
    
    // Проверка, что вопрос принадлежит тесту учителя
    $stmt = $pdo->prepare("SELECT q.id FROM questions q JOIN tests t ON q.test_id = t.id WHERE q.id = ? AND t.teacher_id = ?");
    $stmt->execute([$question_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
    }
    
    redirect("test_editor.php?id=$test_id");
}

// Отмена редактирования
if (isset($_GET['cancel_edit'])) {
    redirect("test_editor.php?id=$test_id");
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

// Подсчет общего количества баллов
$total_points = 0;
foreach ($questions as $question) {
    $total_points += $question['points'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактор теста: <?php echo htmlspecialchars($test['title']); ?> - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="platform-container">
        <div class="sidebar">
            <div class="logo">testBY</div>
            <nav class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Главная</a>
                <a href="create_test.php"><i class="fas fa-plus-circle"></i> Создать тест</a>
                <a href="test_stats.php"><i class="fas fa-chart-bar"></i> Статистика</a>
                <a href="students.php"><i class="fas fa-users"></i> Студенты</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header-top">
                <h1>Редактор теста: <?php echo htmlspecialchars($test['title']); ?></h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo $avatar; ?></div>
                </div>
            </div>

            <?php if (isset($_SESSION['question_added'])): ?>
                <div class="alert alert-success">
                    <strong>Успешно!</strong> Вопрос добавлен в тест.
                    <?php unset($_SESSION['question_added']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['question_updated'])): ?>
                <div class="alert alert-success">
                    <strong>Успешно!</strong> Вопрос обновлен.
                    <?php unset($_SESSION['question_updated']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <strong>Ошибка!</strong> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="test-editor-container">
                <!-- Основная информация о тесте -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Основная информация</h3>
                    </div>
                    <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                        <div class="card">
                            <h4><i class="fas fa-hashtag"></i> Код теста</h4>
                            <div class="test-code"><?php echo $test['test_code']; ?></div>
                        </div>
                        <div class="card">
                            <h4><i class="fas fa-align-left"></i> Описание</h4>
                            <p><?php echo htmlspecialchars($test['description']); ?></p>
                        </div>
                        <div class="card">
                            <h4><i class="fas fa-question-circle"></i> Вопросов</h4>
                            <div class="question-count"><?php echo count($questions); ?></div>
                        </div>
                        <div class="card">
                            <h4><i class="fas fa-star"></i> Всего баллов</h4>
                            <div class="total-points"><?php echo $total_points; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Список вопросов -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-list-ol"></i> Вопросы теста (<?php echo count($questions); ?>)</h3>
                        <div class="test-stats">
                            <span class="score excellent"><?php echo count($questions); ?> вопросов</span>
                            <span class="score good"><?php echo $total_points; ?> баллов</span>
                        </div>
                    </div>
                    
                    <?php if (empty($questions)): ?>
                        <div class="no-tests">
                            <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 15px;"></i>
                            <p>В тесте пока нет вопросов. Добавьте первый вопрос с помощью формы ниже.</p>
                        </div>
                    <?php else: ?>
                        <div class="questions-list">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-item">
                                    <div class="question-header">
                                        <div class="question-info">
                                            <h4>Вопрос <?php echo $index + 1; ?></h4>
                                            <span class="score average"><?php echo $question['points']; ?> баллов</span>
                                        </div>
                                        <div class="question-actions">
                                            <span class="status-badge <?php echo $question['question_type']; ?>">
                                                <?php 
                                                $type_names = [
                                                    'single' => 'Одиночный выбор',
                                                    'multiple' => 'Множественный выбор', 
                                                    'text' => 'Текстовый ответ'
                                                ];
                                                echo $type_names[$question['question_type']];
                                                ?>
                                            </span>
                                            <div class="test-actions">
                                                <a href="?id=<?php echo $test_id; ?>&edit_question=<?php echo $question['id']; ?>" 
                                                   class="btn-action" 
                                                   onclick="loadQuestionForEdit(
                                                       '<?php echo $question['id']; ?>',
                                                       `<?php echo addslashes($question['question_text']); ?>`,
                                                       '<?php echo $question['question_type']; ?>',
                                                       '<?php echo $question['points']; ?>',
                                                       <?php echo json_encode($question['answers']); ?>
                                                   ); return false;"
                                                   title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?id=<?php echo $test_id; ?>&delete_question=<?php echo $question['id']; ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Вы уверены, что хотите удалить этот вопрос?')"
                                                   title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="question-text">
                                        <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                                    </div>
                                    
                                    <?php if ($question['question_type'] !== 'text' && !empty($question['answers'])): ?>
                                        <div class="answers-list">
                                            <strong>Варианты ответов:</strong>
                                            <ul>
                                                <?php foreach ($question['answers'] as $answer): ?>
                                                    <li class="answer-item <?php echo $answer['is_correct'] ? 'correct-answer' : ''; ?>">
                                                        <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                        <?php if ($answer['is_correct']): ?>
                                                            <span class="correct-badge">✓ Правильный</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php elseif ($question['question_type'] === 'text'): ?>
                                        <div class="written-answer-preview">
                                            <strong>Тип ответа:</strong>
                                            <p><em>Текстовый ответ - студент вводит ответ самостоятельно</em></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Форма добавления/редактирования вопроса -->
                <div class="content-card" id="edit-form">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-plus-circle"></i>
                            <?php if ($editing_question): ?>
                                Редактирование вопроса
                                <a href="?id=<?php echo $test_id; ?>&cancel_edit=1" class="btn btn-danger" style="float: right;">
                                    <i class="fas fa-times"></i> Отмена
                                </a>
                            <?php else: ?>
                                Добавить новый вопрос
                            <?php endif; ?>
                        </h3>
                    </div>
                    
                    <form method="POST" onsubmit="return validateQuestionForm()" class="test-form">
                        <?php if ($editing_question): ?>
                            <input type="hidden" name="question_id" value="<?php echo $editing_question['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="question_text"><i class="fas fa-question-circle"></i> Текст вопроса:</label>
                            <textarea id="question_text" name="question_text" rows="3" required 
                                      placeholder="Введите текст вопроса"><?php 
                                echo $editing_question ? htmlspecialchars($editing_question['question_text']) : ''; 
                            ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="question_type"><i class="fas fa-list-alt"></i> Тип вопроса:</label>
                                <select id="question_type" name="question_type" onchange="toggleAnswerFields()" required>
                                    <option value="single" <?php echo ($editing_question && $editing_question['question_type'] === 'single') ? 'selected' : ''; ?>>Одиночный выбор</option>
                                    <option value="multiple" <?php echo ($editing_question && $editing_question['question_type'] === 'multiple') ? 'selected' : ''; ?>>Множественный выбор</option>
                                    <option value="text" <?php echo ($editing_question && $editing_question['question_type'] === 'text') ? 'selected' : ''; ?>>Текстовый ответ</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="points_field"><i class="fas fa-star"></i> Баллы за вопрос:</label>
                                <input type="number" id="points_field" name="points" 
                                       value="<?php echo $editing_question ? $editing_question['points'] : '1'; ?>" 
                                       min="1" required>
                            </div>
                        </div>
                        
                        <div id="answer_fields" class="security-settings">
                            <div class="options-header">
                                <h4><i class="fas fa-list-ul"></i> Варианты ответов</h4>
                                <small>Отметьте правильные ответы галочкой</small>
                            </div>
                            
                            <div class="answers-container" id="answers_container">
                                <?php if ($editing_question && $editing_question['question_type'] !== 'text' && !empty($editing_question['answers'])): ?>
                                    <?php foreach ($editing_question['answers'] as $index => $answer): ?>
                                        <div class="answer-item">
                                            <div class="answer-checkbox">
                                                <input type="checkbox" name="correct_answers[]" value="<?php echo $index; ?>" 
                                                       id="correct<?php echo $index; ?>"
                                                       <?php echo $answer['is_correct'] ? 'checked' : ''; ?>>
                                                <label for="correct<?php echo $index; ?>" class="checkbox-label"></label>
                                            </div>
                                            <div class="answer-input">
                                                <input type="text" name="answers[]" 
                                                       value="<?php echo htmlspecialchars($answer['answer_text']); ?>" 
                                                       placeholder="Введите вариант ответа">
                                            </div>
                                            <div class="answer-actions">
                                                <button type="button" class="btn-remove-answer" onclick="removeAnswer(this)" title="Удалить ответ">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="answer-item">
                                        <div class="answer-checkbox">
                                            <input type="checkbox" name="correct_answers[]" value="0" id="correct0">
                                            <label for="correct0" class="checkbox-label"></label>
                                        </div>
                                        <div class="answer-input">
                                            <input type="text" name="answers[]" placeholder="Введите вариант ответа">
                                        </div>
                                        <div class="answer-actions">
                                            <button type="button" class="btn-remove-answer" onclick="removeAnswer(this)" title="Удалить ответ">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="answer-item">
                                        <div class="answer-checkbox">
                                            <input type="checkbox" name="correct_answers[]" value="1" id="correct1">
                                            <label for="correct1" class="checkbox-label"></label>
                                        </div>
                                        <div class="answer-input">
                                            <input type="text" name="answers[]" placeholder="Введите вариант ответа">
                                        </div>
                                        <div class="answer-actions">
                                            <button type="button" class="btn-remove-answer" onclick="removeAnswer(this)" title="Удалить ответ">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="answers-controls">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="addAnswerField()">
                                    <i class="fas fa-plus"></i> Добавить вариант ответа
                                </button>
                                <small>Для вопросов с множественным выбором можно отметить несколько правильных ответов</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <?php if ($editing_question): ?>
                                <button type="submit" name="update_question" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Обновить вопрос
                                </button>
                            <?php else: ?>
                                <button type="submit" name="add_question" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i> Добавить вопрос
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Кнопка сохранения и возврата -->
                <div class="content-card">
                    <div class="form-actions">
                        <form id="save-return-form" method="POST" style="display: inline;">
                            <button type="button" onclick="confirmSaveAndReturn()" class="btn btn-success btn-large">
                                <i class="fas fa-check-circle"></i> Сохранить и вернуться к списку тестов
                            </button>
                            <input type="hidden" name="save_and_return" value="1">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let answerCount = 2;

        function addAnswerField(answerText = '', isCorrect = false, index = null) {
            const container = document.getElementById('answers_container');
            const newIndex = index !== null ? index : answerCount;
            const newAnswer = document.createElement('div');
            newAnswer.className = 'answer-item';
            newAnswer.innerHTML = `
                <div class="answer-checkbox">
                    <input type="checkbox" name="correct_answers[]" value="${newIndex}" id="correct${newIndex}" ${isCorrect ? 'checked' : ''}>
                    <label for="correct${newIndex}" class="checkbox-label"></label>
                </div>
                <div class="answer-input">
                    <input type="text" name="answers[]" placeholder="Введите вариант ответа" value="${answerText}">
                </div>
                <div class="answer-actions">
                    <button type="button" class="btn-remove-answer" onclick="removeAnswer(this)" title="Удалить ответ">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(newAnswer);
            answerCount++;
        }

        function removeAnswer(button) {
            const answerItems = document.querySelectorAll('.answer-item');
            if (answerItems.length > 1) {
                button.closest('.answer-item').remove();
                updateAnswerValues();
            } else {
                alert('Должен остаться хотя бы один вариант ответа');
            }
        }

        function updateAnswerValues() {
            const checkboxes = document.querySelectorAll('input[name="correct_answers[]"]');
            checkboxes.forEach((checkbox, index) => {
                checkbox.value = index;
                checkbox.id = 'correct' + index;
                checkbox.nextElementSibling.setAttribute('for', 'correct' + index);
            });
            answerCount = checkboxes.length;
        }

        function toggleAnswerFields() {
            const questionType = document.getElementById('question_type').value;
            const answerFields = document.getElementById('answer_fields');
            const pointsField = document.getElementById('points_field');
            
            if (questionType === 'text') {
                answerFields.style.display = 'none';
                // Убираем обязательность полей для ответов при текстовом вопросе
                const answerInputs = document.querySelectorAll('input[name="answers[]"]');
                answerInputs.forEach(input => {
                    input.removeAttribute('required');
                });
                if (!<?php echo $editing_question ? 'true' : 'false'; ?>) {
                    pointsField.value = 5;
                }
            } else {
                answerFields.style.display = 'block';
                // Добавляем обязательность полей для ответов при вопросах с выбором
                const answerInputs = document.querySelectorAll('input[name="answers[]"]');
                answerInputs.forEach(input => {
                    input.setAttribute('required', 'required');
                });
                if (!<?php echo $editing_question ? 'true' : 'false'; ?>) {
                    pointsField.value = 1;
                }
            }
        }

        function validateQuestionForm() {
            const questionType = document.getElementById('question_type').value;
            const questionText = document.getElementById('question_text').value.trim();
            const points = document.getElementById('points_field').value;
            
            if (!questionText) {
                alert('Введите текст вопроса');
                return false;
            }
            
            if (points < 1) {
                alert('Баллы за вопрос должны быть не менее 1');
                return false;
            }
            
            // Для текстовых вопросов дополнительные проверки не нужны
            if (questionType === 'text') {
                return true;
            }
            
            // Проверки только для вопросов с выбором
            const answers = document.querySelectorAll('input[name="answers[]"]');
            let hasAnswers = false;
            let hasCorrectAnswer = false;
            
            answers.forEach(input => {
                if (input.value.trim()) {
                    hasAnswers = true;
                }
            });
            
            const correctAnswers = document.querySelectorAll('input[name="correct_answers[]"]:checked');
            hasCorrectAnswer = correctAnswers.length > 0;
            
            if (!hasAnswers) {
                alert('Добавьте хотя бы один вариант ответа');
                return false;
            }
            
            if (!hasCorrectAnswer) {
                alert('Выберите хотя бы один правильный ответ');
                return false;
            }
            
            return true;
        }

        function loadQuestionForEdit(questionId, questionText, questionType, points, answers) {
            document.getElementById('question_text').value = questionText;
            document.getElementById('question_type').value = questionType;
            document.getElementById('points_field').value = points;
            
            const answersContainer = document.getElementById('answers_container');
            answersContainer.innerHTML = '';
            
            if (questionType !== 'text' && answers) {
                answers.forEach((answer, index) => {
                    addAnswerField(answer.answer_text, answer.is_correct, index);
                });
            } else {
                addAnswerField();
                addAnswerField();
            }
            
            toggleAnswerFields();
            document.getElementById('edit-form').scrollIntoView({ behavior: 'smooth' });
        }

        function confirmSaveAndReturn() {
            if (confirm('Сохранить изменения и вернуться к списку тестов?')) {
                document.getElementById('save-return-form').submit();
            }
        }

        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            toggleAnswerFields();
            updateAnswerValues();
            
            <?php if ($editing_question): ?>
                loadQuestionForEdit(
                    '<?php echo $editing_question['id']; ?>',
                    `<?php echo addslashes($editing_question['question_text']); ?>`,
                    '<?php echo $editing_question['question_type']; ?>',
                    '<?php echo $editing_question['points']; ?>',
                    <?php echo json_encode($editing_question['answers']); ?>
                );
            <?php endif; ?>
        });
    </script>
</body>
</html>