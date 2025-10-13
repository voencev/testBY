<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Обработка входа в систему
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $error = '';
    
    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        // Поиск пользователя (без указания роли)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Успешный вход
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Перенаправление в зависимости от роли из БД
            if ($user['role'] === 'teacher') {
                redirect('teacher/dashboard.php');
            } else {
                redirect('student/dashboard.php');
            }
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    }
}

// Если пользователь уже авторизован, перенаправляем
if (isLoggedIn()) {
    if (getUserRole() === 'teacher') {
        redirect('teacher/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - testBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<style>
    .password-wrapper {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--primary);
    z-index: 10;
}
</style>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1>testBY</h1>
                </div>
                <h2><i class="fas fa-sign-in-alt"></i> Вход в систему</h2>
                <p>Введите ваши учетные данные для доступа к платформе</p>
            </div>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form class="auth-form" method="POST" action="login.php">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Имя пользователя</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Введите имя пользователя"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
    <label for="password"><i class="fas fa-lock"></i> Пароль</label>
    <div class="password-wrapper">
        <input type="password" id="password" name="password" required 
               placeholder="Введите ваш пароль">
        <span class="password-toggle">
            <i class="fas fa-eye"></i>
        </span>
    </div>
</div>
                
                
                
                <button type="submit" class="btn btn-primary btn-block btn-large">
                    <i class="fas fa-sign-in-alt"></i> Войти в систему
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
                <p><a href="index.php">← Вернуться на главную</a></p>
            </div>
        </div>
    </div>

    <script>
        // Дополнительная клиентская валидация
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const role = document.getElementById('role').value;
            
            if (!username || !password || !role) {
                e.preventDefault();
                alert('Пожалуйста, заполните все поля');
                return false;
            }
        });

        /// Показать/скрыть пароль
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');
    const passwordIcon = passwordToggle.querySelector('i');
    
    passwordToggle.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        passwordIcon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    });
});
    </script>
</body>
</html>