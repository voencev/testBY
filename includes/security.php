<?php
// Функции для защиты от списывания

// Логирование действий пользователя во время теста
function logSecurityEvent($attempt_id, $event_type, $details = '') {
    global $pdo;
    
    $event_descriptions = [
        'tab_switch' => 'Переключение на другую вкладку',
        'fullscreen_exit' => 'Выход из полноэкранного режима',
        'idle_timeout' => 'Автозавершение из-за бездействия',
        'copy_attempt' => 'Попытка копирования текста',
        'paste_attempt' => 'Попытка вставки текста',
        'right_click' => 'Использование правой кнопки мыши',
        'dev_tools' => 'Попытка открыть инструменты разработчика'
    ];
    
    $description = $event_descriptions[$event_type] ?? $event_type;
    $log_message = date('d.m.Y H:i:s') . " - {$description}";
    if (!empty($details)) {
        $log_message .= " ({$details})";
    }
    $log_message .= "\n";
    
    $stmt = $pdo->prepare("UPDATE test_attempts SET security_log = CONCAT(COALESCE(security_log, ''), ?), violation_count = violation_count + 1 WHERE id = ?");
    $stmt->execute([$log_message, $attempt_id]);
}

// Проверка на переключение вкладок
function detectTabSwitch($attempt_id) {
    echo "<script>
    let tabSwitchCount = 0;
    
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            tabSwitchCount++;
            
            // Логируем переключение вкладки
            fetch('../includes/tab_switch.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'attempt_id=' + $attempt_id + '&count=' + tabSwitchCount
            });
            
            // Показываем предупреждение
            const warning = document.getElementById('tab-switch-warning');
            if (warning) {
                warning.style.display = 'block';
                setTimeout(() => {
                    warning.style.display = 'none';
                }, 5000);
            }
            
            // Блокируем форму на 10 секунд после 3-го нарушения
            if (tabSwitchCount >= 3) {
                const form = document.getElementById('test-form');
                const inputs = form.querySelectorAll('input, textarea, button');
                inputs.forEach(input => input.disabled = true);
                
                setTimeout(() => {
                    inputs.forEach(input => input.disabled = false);
                }, 10000);
            }
        }
    });
    </script>";
    
    // Добавляем элемент для отображения предупреждения
    echo '<div id="tab-switch-warning" style="display: none; background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; margin: 10px 0; border-radius: 4px;">
            ⚠️ Обнаружено переключение вкладки! Это действие зафиксировано в системе.
          </div>';
}

// Запрет копирования текста
function disableCopyPaste() {
    echo "<script>
    document.addEventListener('copy', function(e) {
        e.preventDefault();
        showSecurityWarning('Копирование текста запрещено');
        logSecurityEvent('copy_attempt');
        return false;
    });
    
    document.addEventListener('cut', function(e) {
        e.preventDefault();
        showSecurityWarning('Вырезание текста запрещено');
        logSecurityEvent('copy_attempt');
        return false;
    });
    
    document.addEventListener('paste', function(e) {
        e.preventDefault();
        showSecurityWarning('Вставка текста запрещена');
        logSecurityEvent('paste_attempt');
        return false;
    });
    
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        showSecurityWarning('Правый клик заблокирован');
        logSecurityEvent('right_click');
        return false;
    });
    
    function showSecurityWarning(message) {
        // Создаем или находим элемент для предупреждений
        let warningDiv = document.getElementById('security-warnings');
        if (!warningDiv) {
            warningDiv = document.createElement('div');
            warningDiv.id = 'security-warnings';
            warningDiv.style.cssText = 'position: fixed; top: 10px; right: 10px; z-index: 10000; max-width: 300px;';
            document.body.appendChild(warningDiv);
        }
        
        const warning = document.createElement('div');
        warning.style.cssText = 'background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 5px 0; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
        warning.innerHTML = '❌ ' + message;
        
        warningDiv.appendChild(warning);
        
        // Удаляем предупреждение через 3 секунды
        setTimeout(() => {
            if (warning.parentNode) {
                warning.parentNode.removeChild(warning);
            }
        }, 3000);
    }
    
    function logSecurityEvent(type) {
        // Отправляем событие на сервер
        fetch('../includes/log_security_event.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'event_type=' + type
        });
    }
    </script>";
}

// Требование полноэкранного режима
function requireFullscreen($attempt_id) {
    echo "<script>
    let fullscreenExitCount = 0;
    
    function enterFullscreen() {
        const elem = document.documentElement;
        if (elem.requestFullscreen) {
            return elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            return elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            return elem.msRequestFullscreen();
        }
        return Promise.reject();
    }
    
    function exitFullscreen() {
        if (document.exitFullscreen) {
            return document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            return document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            return document.msExitFullscreen();
        }
        return Promise.reject();
    }
    
    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
    }
    
    function showFullscreenWarning() {
        const warning = document.getElementById('fullscreen-warning');
        if (warning) {
            warning.style.display = 'block';
        }
    }
    
    function hideFullscreenWarning() {
        const warning = document.getElementById('fullscreen-warning');
        if (warning) {
            warning.style.display = 'none';
        }
    }
    
    // Проверяем полноэкранный режим при загрузке
    document.addEventListener('DOMContentLoaded', function() {
        if (!isFullscreen()) {
            showFullscreenWarning();
            
            // Пытаемся войти в полноэкранный режим
            enterFullscreen().then(() => {
                hideFullscreenWarning();
            }).catch(err => {
                console.log('Fullscreen error:', err);
                // Если автоматический вход не удался, показываем инструкцию
                showFullscreenWarning();
            });
        }
    });
    
    // Отслеживаем выход из полноэкранного режима
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('msfullscreenchange', handleFullscreenChange);
    
    function handleFullscreenChange() {
        if (!isFullscreen()) {
            fullscreenExitCount++;
            
            // Логируем выход из полноэкранного режима
            fetch('../includes/fullscreen_exit.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'attempt_id=' + $attempt_id + '&count=' + fullscreenExitCount
            });
            
            showFullscreenWarning();
            
            // Пытаемся вернуться в полноэкранный режим
            setTimeout(() => {
                if (!isFullscreen()) {
                    enterFullscreen().then(() => {
                        hideFullscreenWarning();
                    }).catch(err => {
                        console.log('Fullscreen re-entry failed:', err);
                    });
                }
            }, 1000);
        } else {
            hideFullscreenWarning();
        }
    }
    
    // Блокируем клавишу F11
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F11') {
            e.preventDefault();
            showSecurityWarning('Клавиша F11 заблокирована');
            return false;
        }
    });
    </script>";
    
    // Добавляем элемент для отображения предупреждения о полноэкранном режиме
    echo '<div id="fullscreen-warning" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); color: white; z-index: 9999; display: flex; justify-content: center; align-items: center; flex-direction: column;">
            <div style="background: white; color: #333; padding: 30px; border-radius: 8px; text-align: center; max-width: 500px;">
                <h2 style="color: #e74c3c; margin-bottom: 20px;">⚠️ Требуется полноэкранный режим</h2>
                <p style="margin-bottom: 20px;">Для продолжения теста необходимо перейти в полноэкранный режим.</p>
                <p style="margin-bottom: 20px; font-size: 14px; color: #666;">
                    Нажмите F11 или используйте меню браузера для включения полноэкранного режима.
                </p>
                <button onclick="enterFullscreen().then(() => hideFullscreenWarning())" 
                        style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    Войти в полноэкранный режим
                </button>
            </div>
          </div>';
}

// Защита от автоматических ботов и обнаружение бездействия
function addAntiBotMeasures($attempt_id) {
    echo "<script>
    // Таймер для отслеживания времени бездействия
    let idleTime = 0;
    let warningShown = false;
    let activityDetected = true;
    
    const idleInterval = setInterval(() => {
        if (!activityDetected) {
            idleTime++;
        } else {
            activityDetected = false;
        }
        
        // Предупреждение после 30 секунд бездействия
        if (idleTime > 30 && !warningShown) {
            showSecurityWarning('Обнаружено бездействие. Продолжите тест в течение 30 секунд.');
            warningShown = true;
        }
        
        // Автозавершение после 60 секунд бездействия
        if (idleTime > 60) {
            // Логируем автоматическое завершение
            fetch('../includes/idle_timeout.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'attempt_id=' + $attempt_id + '&idle_time=' + idleTime
            });
            
            alert('❌ Тест автоматически завершен из-за длительного бездействия (более 60 секунд)');
            document.getElementById('auto-finish').value = '1';
            document.getElementById('test-form').submit();
            clearInterval(idleInterval);
        }
    }, 1000);
    
    // Сброс таймера при активности пользователя
    function resetIdleTime() {
        idleTime = 0;
        warningShown = false;
        activityDetected = true;
    }
    
    document.addEventListener('mousemove', resetIdleTime);
    document.addEventListener('keypress', resetIdleTime);
    document.addEventListener('click', resetIdleTime);
    document.addEventListener('scroll', resetIdleTime);
    document.addEventListener('mousedown', resetIdleTime);
    document.addEventListener('touchstart', resetIdleTime);
    
    // Блокировка клавиш разработчика
    document.addEventListener('keydown', function(e) {
        // F12 - Developer Tools
        if (e.key === 'F12') {
            e.preventDefault();
            showSecurityWarning('Доступ к инструментам разработчика заблокирован');
            logSecurityEvent('dev_tools');
            return false;
        }
        
        // Ctrl+Shift+I - Developer Tools
        if (e.ctrlKey && e.shiftKey && e.key === 'I') {
            e.preventDefault();
            showSecurityWarning('Доступ к инструментам разработчика заблокирован');
            logSecurityEvent('dev_tools');
            return false;
        }
        
        // Ctrl+Shift+C - Developer Tools
        if (e.ctrlKey && e.shiftKey && e.key === 'C') {
            e.preventDefault();
            showSecurityWarning('Доступ к инструментам разработчика заблокирован');
            logSecurityEvent('dev_tools');
            return false;
        }
        
        // Ctrl+U - View Source
        if (e.ctrlKey && e.key === 'u') {
            e.preventDefault();
            showSecurityWarning('Просмотр исходного кода заблокирован');
            logSecurityEvent('dev_tools');
            return false;
        }
    });
    </script>";
}

// Основная функция применения мер безопасности
function applySecurityMeasures($attempt_id, $security_settings) {
    if ($security_settings['disable_copy_paste'] ?? false) {
        disableCopyPaste();
    }
    
    if ($security_settings['detect_tab_switch'] ?? false) {
        detectTabSwitch($attempt_id);
    }
    
    if ($security_settings['fullscreen_required'] ?? false) {
        requireFullscreen($attempt_id);
    }
    
    if ($security_settings['idle_detection'] ?? false) {
        addAntiBotMeasures($attempt_id);
    }
}
?>