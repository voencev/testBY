-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Окт 13 2025 г., 12:46
-- Версия сервера: 8.0.30
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `testby`
--

-- --------------------------------------------------------

--
-- Структура таблицы `answers`
--

CREATE TABLE `answers` (
  `id` int NOT NULL,
  `question_id` int NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `answers`
--

INSERT INTO `answers` (`id`, `question_id`, `answer_text`, `is_correct`) VALUES
(64, 25, 'Троянские программы', 0),
(65, 25, 'Сетевые вирусы', 1),
(66, 25, 'Компьютерные вирусы', 0),
(67, 26, 'репликанты', 1),
(68, 26, 'руткиты', 1),
(69, 26, 'эксплойты', 1),
(70, 26, 'утилиты', 0),
(71, 26, 'драйверы', 0),
(72, 26, 'бэкдоры', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `questions`
--

CREATE TABLE `questions` (
  `id` int NOT NULL,
  `test_id` int NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('single','multiple','text') DEFAULT 'single',
  `points` int DEFAULT '1',
  `image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `questions`
--

INSERT INTO `questions` (`id`, `test_id`, `question_text`, `question_type`, `points`, `image_url`) VALUES
(24, 8, 'Напишите характеристику вредоносных программ 1 класса', 'text', 1, NULL),
(25, 8, 'Определите тип вредоносных программ, которые используют для размножения средства сетевых операционных систем', 'single', 1, NULL),
(26, 8, 'Что из перечисленного относится к компьютерным вирусам?', 'multiple', 1, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int NOT NULL,
  `attempt_id` int NOT NULL,
  `question_id` int NOT NULL,
  `answer_id` int DEFAULT NULL,
  `answer_text` text,
  `is_correct` tinyint(1) DEFAULT '0',
  `manual_score` decimal(5,2) DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `student_answers`
--

INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `answer_id`, `answer_text`, `is_correct`, `manual_score`, `answered_at`) VALUES
(114, 49, 25, 64, NULL, 0, NULL, '2025-10-13 09:40:21'),
(115, 49, 26, 72, NULL, 1, NULL, '2025-10-13 09:40:21'),
(116, 49, 26, 68, NULL, 1, NULL, '2025-10-13 09:40:21'),
(117, 49, 26, 67, NULL, 1, NULL, '2025-10-13 09:40:21'),
(118, 49, 26, 69, NULL, 1, NULL, '2025-10-13 09:40:21'),
(119, 49, 24, NULL, 'Учатся они в первом классе :D', 0, '0.00', '2025-10-13 09:40:21');

-- --------------------------------------------------------

--
-- Структура таблицы `tests`
--

CREATE TABLE `tests` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `teacher_id` int NOT NULL,
  `test_code` varchar(10) NOT NULL,
  `time_limit` int DEFAULT '0',
  `shuffle_questions` tinyint(1) DEFAULT '0',
  `shuffle_answers` tinyint(1) DEFAULT '0',
  `show_results` tinyint(1) DEFAULT '1',
  `security_settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `tests`
--

INSERT INTO `tests` (`id`, `title`, `description`, `teacher_id`, `test_code`, `time_limit`, `shuffle_questions`, `shuffle_answers`, `show_results`, `security_settings`, `created_at`, `updated_at`) VALUES
(8, 'Вредоносное ПО', 'Для ВС-41 ОКБ', 15, '5URYT5', 10, 1, 1, 1, '{\"idle_detection\": true, \"detect_tab_switch\": true, \"disable_copy_paste\": true, \"fullscreen_required\": true}', '2025-10-13 09:28:56', '2025-10-13 09:28:56');

-- --------------------------------------------------------

--
-- Структура таблицы `test_attempts`
--

CREATE TABLE `test_attempts` (
  `id` int NOT NULL,
  `test_id` int NOT NULL,
  `student_id` int NOT NULL,
  `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` timestamp NULL DEFAULT NULL,
  `score` decimal(8,2) DEFAULT '0.00',
  `max_score` decimal(8,2) DEFAULT '0.00',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `security_log` text,
  `violation_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `test_attempts`
--

INSERT INTO `test_attempts` (`id`, `test_id`, `student_id`, `started_at`, `finished_at`, `score`, `max_score`, `ip_address`, `user_agent`, `security_log`, `violation_count`) VALUES
(49, 8, 14, '2025-10-13 09:39:31', '2025-10-13 09:40:21', '4.00', '6.00', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0', '{\"timestamp\":\"2025-10-13 12:40:12\",\"event_type\":\"tab_switch\",\"details\":\"\\u041e\\u0431\\u043d\\u0430\\u0440\\u0443\\u0436\\u0435\\u043d\\u043e \\u043f\\u0435\\u0440\\u0435\\u043a\\u043b\\u044e\\u0447\\u0435\\u043d\\u0438\\u0435 \\u0432\\u043a\\u043b\\u0430\\u0434\\u043a\\u0438 \\u0432 11:59:08\"}\n{\"timestamp\":\"2025-10-13 12:40:22\",\"event_type\":\"tab_switch\",\"details\":\"\\u041e\\u0431\\u043d\\u0430\\u0440\\u0443\\u0436\\u0435\\u043d\\u043e \\u043f\\u0435\\u0440\\u0435\\u043a\\u043b\\u044e\\u0447\\u0435\\u043d\\u0438\\u0435 \\u0432\\u043a\\u043b\\u0430\\u0434\\u043a\\u0438 \\u0432 11:59:08\"}\n', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('teacher','student') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `full_name`, `created_at`) VALUES
(14, 'ivan001', '$2y$10$zfDbzhkeGfUVhBA4xa3PkOQPnVwoZLyPut7QK6maGoFZSDEISf2pS', 'ivan@example.com', 'student', 'Иванов Иван Иванович', '2025-10-13 09:20:58'),
(15, 'glagol', '$2y$10$bdS0SytUfpJpYEvn2OKza.qa.Zj88hICNvxdxaASXpwI.DEvRJzEG', 'glagol@example.com', 'teacher', 'Петренко Лидия Глагольевна', '2025-10-13 09:21:43');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Индексы таблицы `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`);

--
-- Индексы таблицы `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `answer_id` (`answer_id`),
  ADD KEY `idx_attempt_question` (`attempt_id`,`question_id`);

--
-- Индексы таблицы `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_code` (`test_code`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Индексы таблицы `test_attempts`
--
ALTER TABLE `test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_test_student` (`test_id`,`student_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT для таблицы `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT для таблицы `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT для таблицы `tests`
--
ALTER TABLE `tests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `test_attempts`
--
ALTER TABLE `test_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_3` FOREIGN KEY (`answer_id`) REFERENCES `answers` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `tests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `test_attempts`
--
ALTER TABLE `test_attempts`
  ADD CONSTRAINT `test_attempts_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
