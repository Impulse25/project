-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Июн 11 2026 г., 22:48
-- Версия сервера: 5.7.39
-- Версия PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `svgtk_analytics`
--

-- --------------------------------------------------------

--
-- Структура таблицы `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `percent` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `percent`) VALUES
(1, 1, 78),
(2, 2, 82),
(3, 3, 75),
(4, 4, 71),
(5, 5, 68),
(6, 6, 73),
(7, 7, 84),
(8, 8, 79),
(9, 9, 65),
(10, 10, 69),
(11, 11, 72),
(12, 12, 81),
(13, 13, 63),
(14, 14, 67),
(15, 15, 80),
(16, 16, 77),
(17, 17, 74),
(18, 18, 70),
(19, 19, 83),
(20, 20, 76),
(21, 21, 61),
(22, 22, 66),
(23, 23, 79),
(24, 24, 82),
(25, 25, 71),
(26, 26, 75),
(27, 27, 64),
(28, 28, 68),
(29, 29, 73),
(30, 30, 77),
(31, 31, 81),
(32, 32, 85),
(33, 33, 69),
(34, 34, 72),
(35, 35, 67),
(36, 36, 74),
(37, 37, 78),
(38, 38, 80),
(39, 39, 62),
(40, 40, 65),
(41, 41, 71),
(42, 42, 76),
(43, 43, 84),
(44, 44, 79),
(45, 45, 66),
(46, 46, 70),
(47, 47, 82),
(48, 48, 77);

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_attendance`
--

CREATE TABLE `lesson_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `group_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `status` enum('present','late','absent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lesson_attendance` (`student_id`,`subject_id`,`lesson_date`),
  KEY `student_id` (`student_id`),
  KEY `group_id` (`group_id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `grade` int(11) NOT NULL,
  `grade_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `subject_id`, `grade`, `grade_date`) VALUES
(1, 1, 55, 78, '2026-05-02'),
(2, 1, 56, 78, '2026-05-03'),
(3, 1, 57, 94, '2026-05-04'),
(4, 2, 55, 62, '2026-05-05'),
(5, 2, 56, 78, '2026-05-06'),
(6, 2, 57, 78, '2026-05-07'),
(7, 3, 55, 78, '2026-05-08'),
(8, 3, 56, 78, '2026-05-09'),
(9, 3, 57, 62, '2026-05-10'),
(10, 4, 55, 62, '2026-05-11'),
(11, 4, 56, 62, '2026-05-12'),
(12, 4, 57, 78, '2026-05-13'),
(13, 5, 55, 78, '2026-05-14'),
(14, 5, 56, 94, '2026-05-15'),
(15, 5, 57, 78, '2026-05-16'),
(16, 6, 55, 62, '2026-05-17'),
(17, 6, 56, 78, '2026-05-18'),
(18, 6, 57, 62, '2026-05-19'),
(19, 7, 55, 94, '2026-05-20'),
(20, 7, 56, 94, '2026-05-21'),
(21, 7, 57, 78, '2026-05-22'),
(22, 8, 55, 78, '2026-05-23'),
(23, 8, 56, 78, '2026-05-24'),
(24, 8, 57, 78, '2026-05-25'),
(25, 9, 58, 78, '2026-05-26'),
(26, 9, 59, 62, '2026-05-27'),
(27, 9, 60, 78, '2026-05-28'),
(28, 10, 58, 62, '2026-05-01'),
(29, 10, 59, 62, '2026-05-02'),
(30, 10, 60, 78, '2026-05-03'),
(31, 11, 58, 78, '2026-05-04'),
(32, 11, 59, 78, '2026-05-05'),
(33, 11, 60, 94, '2026-05-06'),
(34, 12, 58, 78, '2026-05-07'),
(35, 12, 59, 62, '2026-05-08'),
(36, 12, 60, 78, '2026-05-09'),
(37, 13, 58, 62, '2026-05-10'),
(38, 13, 59, 62, '2026-05-11'),
(39, 13, 60, 62, '2026-05-12'),
(40, 14, 58, 78, '2026-05-13'),
(41, 14, 59, 78, '2026-05-14'),
(42, 14, 60, 78, '2026-05-15'),
(43, 15, 58, 94, '2026-05-16'),
(44, 15, 59, 78, '2026-05-17'),
(45, 15, 60, 94, '2026-05-18'),
(46, 16, 58, 78, '2026-05-19'),
(47, 16, 59, 78, '2026-05-20'),
(48, 16, 60, 62, '2026-05-21'),
(49, 17, 61, 94, '2026-05-22'),
(50, 17, 62, 78, '2026-05-23'),
(51, 17, 63, 94, '2026-05-24'),
(52, 18, 61, 78, '2026-05-25'),
(53, 18, 62, 78, '2026-05-26'),
(54, 18, 63, 78, '2026-05-27'),
(55, 19, 61, 62, '2026-05-28'),
(56, 19, 62, 78, '2026-05-01'),
(57, 19, 63, 62, '2026-05-02'),
(58, 20, 61, 78, '2026-05-03'),
(59, 20, 62, 78, '2026-05-04'),
(60, 20, 63, 94, '2026-05-05'),
(61, 21, 61, 62, '2026-05-06'),
(62, 21, 62, 62, '2026-05-07'),
(63, 21, 63, 62, '2026-05-08'),
(64, 22, 61, 78, '2026-05-09'),
(65, 22, 62, 94, '2026-05-10'),
(66, 22, 63, 78, '2026-05-11'),
(67, 23, 61, 78, '2026-05-12'),
(68, 23, 62, 78, '2026-05-13'),
(69, 23, 63, 78, '2026-05-14'),
(70, 24, 61, 94, '2026-05-15'),
(71, 24, 62, 94, '2026-05-16'),
(72, 24, 63, 78, '2026-05-17'),
(73, 25, 64, 78, '2026-05-18'),
(74, 25, 65, 78, '2026-05-19'),
(75, 25, 66, 78, '2026-05-20'),
(76, 26, 64, 62, '2026-05-21'),
(77, 26, 65, 78, '2026-05-22'),
(78, 26, 66, 62, '2026-05-23'),
(79, 27, 64, 78, '2026-05-24'),
(80, 27, 65, 94, '2026-05-25'),
(81, 27, 66, 78, '2026-05-26'),
(82, 28, 64, 62, '2026-05-27'),
(83, 28, 65, 62, '2026-05-28'),
(84, 28, 66, 78, '2026-05-01'),
(85, 29, 64, 94, '2026-05-02'),
(86, 29, 65, 78, '2026-05-03'),
(87, 29, 66, 94, '2026-05-04'),
(88, 30, 64, 78, '2026-05-05'),
(89, 30, 65, 78, '2026-05-06'),
(90, 30, 66, 78, '2026-05-07'),
(91, 31, 64, 62, '2026-05-08'),
(92, 31, 65, 78, '2026-05-09'),
(93, 31, 66, 62, '2026-05-10'),
(94, 32, 64, 78, '2026-05-11'),
(95, 32, 65, 94, '2026-05-12'),
(96, 32, 66, 78, '2026-05-13'),
(97, 33, 67, 78, '2026-05-14'),
(98, 33, 68, 78, '2026-05-15'),
(99, 33, 69, 62, '2026-05-16'),
(100, 34, 67, 62, '2026-05-17'),
(101, 34, 68, 78, '2026-05-18'),
(102, 34, 69, 78, '2026-05-19'),
(103, 35, 67, 78, '2026-05-20'),
(104, 35, 68, 94, '2026-05-21'),
(105, 35, 69, 78, '2026-05-22'),
(106, 36, 67, 62, '2026-05-23'),
(107, 36, 68, 62, '2026-05-24'),
(108, 36, 69, 78, '2026-05-25'),
(109, 37, 67, 78, '2026-05-26'),
(110, 37, 68, 78, '2026-05-27'),
(111, 37, 69, 94, '2026-05-28'),
(112, 38, 67, 94, '2026-05-01'),
(113, 38, 68, 78, '2026-05-02'),
(114, 38, 69, 94, '2026-05-03'),
(115, 39, 67, 62, '2026-05-04'),
(116, 39, 68, 62, '2026-05-05'),
(117, 39, 69, 62, '2026-05-06'),
(118, 40, 67, 78, '2026-05-07'),
(119, 40, 68, 78, '2026-05-08'),
(120, 40, 69, 78, '2026-05-09'),
(121, 41, 70, 78, '2026-05-10'),
(122, 41, 71, 78, '2026-05-11'),
(123, 41, 72, 78, '2026-05-12'),
(124, 42, 70, 62, '2026-05-13'),
(125, 42, 71, 78, '2026-05-14'),
(126, 42, 72, 62, '2026-05-15'),
(127, 43, 70, 94, '2026-05-16'),
(128, 43, 71, 94, '2026-05-17'),
(129, 43, 72, 78, '2026-05-18'),
(130, 44, 70, 78, '2026-05-19'),
(131, 44, 71, 78, '2026-05-20'),
(132, 44, 72, 94, '2026-05-21'),
(133, 45, 70, 62, '2026-05-22'),
(134, 45, 71, 62, '2026-05-23'),
(135, 45, 72, 78, '2026-05-24'),
(136, 46, 70, 78, '2026-05-25'),
(137, 46, 71, 78, '2026-05-26'),
(138, 46, 72, 78, '2026-05-27'),
(139, 47, 70, 94, '2026-05-28'),
(140, 47, 71, 78, '2026-05-01'),
(141, 47, 72, 94, '2026-05-02'),
(142, 48, 70, 78, '2026-05-03'),
(143, 48, 71, 78, '2026-05-04'),
(144, 48, 72, 78, '2026-05-05');

-- --------------------------------------------------------

--
-- Структура таблицы `graduates`
--

CREATE TABLE `graduates` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `status` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `graduates`
--

INSERT INTO `graduates` (`id`, `group_id`, `status`) VALUES
(1, 4, 'Обучающийся на выпускном курсе'),
(2, 8, 'Обучающийся на выпускном курсе'),
(3, 12, 'Обучающийся на выпускном курсе'),
(4, 16, 'Обучающийся на выпускном курсе'),
(5, 20, 'Обучающийся на выпускном курсе'),
(6, 24, 'Обучающийся на выпускном курсе');

-- --------------------------------------------------------

--
-- Структура таблицы `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Проблемная','Требует контроля','Стабильная') COLLATE utf8mb4_unicode_ci DEFAULT 'Требует контроля',
  `curator_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `groups`
--

INSERT INTO `groups` (`id`, `name`, `status`, `curator_id`) VALUES
(1, 'ПВТ 9-25', 'Стабильная', NULL),
(2, 'ПВТ 9-24', 'Требует контроля', NULL),
(3, 'ПВТ 9-23', 'Стабильная', NULL),
(4, 'ПВТ 9-22', 'Стабильная', 1),
(5, 'ПШ 9-25', 'Требует контроля', NULL),
(6, 'ПШ 9-24', 'Стабильная', NULL),
(7, 'ПШ 9-23', 'Проблемная', NULL),
(8, 'ПШ 9-22', 'Стабильная', NULL),
(9, 'БУХ 9-25', 'Требует контроля', NULL),
(10, 'БУХ 9-24', 'Стабильная', NULL),
(11, 'БУХ 9-23', 'Проблемная', NULL),
(12, 'БУХ 9-22', 'Стабильная', NULL),
(13, 'ЭПП 9-25', 'Стабильная', NULL),
(14, 'ЭПП 9-24', 'Требует контроля', NULL),
(15, 'ЭПП 9-23', 'Проблемная', NULL),
(16, 'ЭПП 9-22', 'Стабильная', NULL),
(17, 'ТОМО 9-25', 'Требует контроля', NULL),
(18, 'ТОМО 9-24', 'Стабильная', NULL),
(19, 'ТОМО 9-23', 'Требует контроля', NULL),
(20, 'ТОМО 9-22', 'Стабильная', NULL),
(21, 'РТП 9-25', 'Требует контроля', NULL),
(22, 'РТП 9-24', 'Стабильная', NULL),
(23, 'РТП 9-23', 'Проблемная', NULL),
(24, 'РТП 11-23', 'Стабильная', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `students`
--

INSERT INTO `students` (`id`, `user_id`, `group_id`) VALUES
(1, 11, 4),
(2, 12, 4),
(3, 13, 1),
(4, 14, 1),
(5, 15, 2),
(6, 16, 2),
(7, 17, 3),
(8, 18, 3),
(9, 67, 5),
(10, 68, 5),
(11, 69, 6),
(12, 70, 6),
(13, 71, 7),
(14, 72, 7),
(15, 73, 8),
(16, 74, 8),
(17, 75, 9),
(18, 76, 9),
(19, 77, 10),
(20, 78, 10),
(21, 79, 11),
(22, 80, 11),
(23, 81, 12),
(24, 82, 12),
(25, 83, 13),
(26, 84, 13),
(27, 85, 14),
(28, 86, 14),
(29, 87, 15),
(30, 88, 15),
(31, 89, 16),
(32, 90, 16),
(33, 91, 17),
(34, 92, 17),
(35, 93, 18),
(36, 94, 18),
(37, 95, 19),
(38, 96, 19),
(39, 97, 20),
(40, 98, 20),
(41, 99, 21),
(42, 100, 21),
(43, 101, 22),
(44, 102, 22),
(45, 103, 23),
(46, 104, 23),
(47, 105, 24),
(48, 106, 24);

-- --------------------------------------------------------

--
-- Структура таблицы `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacher_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `teacher_id`) VALUES
(55, 'Программирование', 1),
(56, 'Базы данных', 2),
(57, 'Веб-разработка', 3),
(58, 'Технология швейного производства', 4),
(59, 'Конструирование одежды', 5),
(60, 'Материаловедение', 6),
(61, 'Бухгалтерский учет', 7),
(62, 'Налоги и аудит', 8),
(63, 'Финансовый анализ', 9),
(64, 'Экономика предприятия', 1),
(65, 'Маркетинг', 2),
(66, 'Менеджмент', 3),
(67, 'Технология машиностроения', 4),
(68, 'ЧПУ станки', 5),
(69, 'Инженерная графика', 6),
(70, 'Ремонт транспорта', 7),
(71, 'Устройство автомобилей', 8),
(72, 'Диагностика техники', 9);

-- --------------------------------------------------------

--
-- Структура таблицы `subject_groups`
--

CREATE TABLE `subject_groups` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subject_groups`
--

INSERT INTO `subject_groups` (`id`, `subject_id`, `group_id`) VALUES
(434, 55, 1),
(435, 55, 2),
(436, 55, 3),
(437, 55, 4),
(441, 56, 1),
(442, 56, 2),
(443, 56, 3),
(444, 56, 4),
(448, 57, 1),
(449, 57, 2),
(450, 57, 3),
(451, 57, 4),
(455, 58, 5),
(456, 58, 6),
(457, 58, 7),
(458, 58, 8),
(462, 59, 5),
(463, 59, 6),
(464, 59, 7),
(465, 59, 8),
(469, 60, 5),
(470, 60, 6),
(471, 60, 7),
(472, 60, 8),
(476, 61, 9),
(477, 61, 10),
(478, 61, 11),
(479, 61, 12),
(483, 62, 9),
(484, 62, 10),
(485, 62, 11),
(486, 62, 12),
(490, 63, 9),
(491, 63, 10),
(492, 63, 11),
(493, 63, 12),
(497, 64, 13),
(498, 64, 14),
(499, 64, 15),
(500, 64, 16),
(504, 65, 13),
(505, 65, 14),
(506, 65, 15),
(507, 65, 16),
(511, 66, 13),
(512, 66, 14),
(513, 66, 15),
(514, 66, 16),
(518, 67, 17),
(519, 67, 18),
(520, 67, 19),
(521, 67, 20),
(525, 68, 17),
(526, 68, 18),
(527, 68, 19),
(528, 68, 20),
(532, 69, 17),
(533, 69, 18),
(534, 69, 19),
(535, 69, 20),
(539, 70, 21),
(540, 70, 22),
(541, 70, 23),
(542, 70, 24),
(546, 71, 21),
(547, 71, 22),
(548, 71, 23),
(549, 71, 24),
(553, 72, 21),
(554, 72, 22),
(555, 72, 23),
(556, 72, 24),
(560, 55, 1),
(561, 55, 2),
(562, 55, 3),
(563, 55, 4),
(567, 56, 1),
(568, 56, 2),
(569, 56, 3),
(570, 56, 4),
(574, 57, 1),
(575, 57, 2),
(576, 57, 3),
(577, 57, 4),
(581, 58, 5),
(582, 58, 6),
(583, 58, 7),
(584, 58, 8),
(588, 59, 5),
(589, 59, 6),
(590, 59, 7),
(591, 59, 8),
(595, 60, 5),
(596, 60, 6),
(597, 60, 7),
(598, 60, 8),
(602, 61, 9),
(603, 61, 10),
(604, 61, 11),
(605, 61, 12),
(609, 62, 9),
(610, 62, 10),
(611, 62, 11),
(612, 62, 12),
(616, 63, 9),
(617, 63, 10),
(618, 63, 11),
(619, 63, 12),
(623, 64, 13),
(624, 64, 14),
(625, 64, 15),
(626, 64, 16),
(630, 65, 13),
(631, 65, 14),
(632, 65, 15),
(633, 65, 16),
(637, 66, 13),
(638, 66, 14),
(639, 66, 15),
(640, 66, 16),
(644, 67, 17),
(645, 67, 18),
(646, 67, 19),
(647, 67, 20),
(651, 68, 17),
(652, 68, 18),
(653, 68, 19),
(654, 68, 20),
(658, 69, 17),
(659, 69, 18),
(660, 69, 19),
(661, 69, 20),
(665, 70, 21),
(666, 70, 22),
(667, 70, 23),
(668, 70, 24),
(672, 71, 21),
(673, 71, 22),
(674, 71, 23),
(675, 71, 24),
(679, 72, 21),
(680, 72, 22),
(681, 72, 23),
(682, 72, 24);

-- --------------------------------------------------------

--
-- Структура таблицы `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`) VALUES
(1, 2),
(2, 3),
(3, 4),
(4, 5),
(5, 6),
(6, 7),
(7, 8),
(8, 9),
(9, 10);

-- --------------------------------------------------------

--
-- Структура таблицы `teacher_groups`
--

CREATE TABLE `teacher_groups` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `teacher_groups`
--

INSERT INTO `teacher_groups` (`id`, `teacher_id`, `group_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 4),
(5, 2, 1),
(6, 2, 2),
(7, 2, 3),
(8, 2, 4),
(9, 3, 1),
(10, 3, 2),
(11, 3, 3),
(12, 3, 4),
(13, 3, 5),
(14, 3, 6),
(15, 3, 7),
(16, 3, 8),
(17, 4, 5),
(18, 4, 6),
(19, 4, 7),
(20, 4, 8),
(21, 5, 5),
(22, 5, 6),
(23, 5, 7),
(24, 5, 8),
(25, 6, 9),
(26, 6, 10),
(27, 6, 11),
(28, 6, 12),
(29, 7, 9),
(30, 7, 10),
(31, 7, 11),
(32, 7, 12),
(33, 8, 9),
(34, 8, 10),
(35, 8, 11),
(36, 8, 12),
(37, 2, 13),
(38, 2, 14),
(39, 2, 15),
(40, 2, 16),
(41, 7, 13),
(42, 7, 14),
(43, 7, 15),
(44, 7, 16),
(45, 8, 13),
(46, 8, 14),
(47, 8, 15),
(48, 8, 16),
(49, 5, 17),
(50, 5, 18),
(51, 5, 19),
(52, 5, 20),
(53, 6, 17),
(54, 6, 18),
(55, 6, 19),
(56, 6, 20),
(57, 9, 17),
(58, 9, 18),
(59, 9, 19),
(60, 9, 20),
(61, 8, 21),
(62, 8, 22),
(63, 8, 23),
(64, 8, 24),
(65, 9, 21),
(66, 9, 22),
(67, 9, 23),
(68, 9, 24),
(69, 6, 21),
(70, 6, 22),
(71, 6, 23),
(72, 6, 24);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','teacher','student') COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `full_name`, `login`, `password`, `role`, `group_id`) VALUES
(1, 'Иванов С. П.', 'ivanov_sp', '123456', 'admin', NULL),
(2, 'Уракова М. С.', 'urakova_ms', '123456', 'teacher', NULL),
(3, 'Садыков А. А.', 'sadykov_aa', '123456', 'teacher', NULL),
(4, 'Кузнецова Е. В.', 'kuznetsova_ev', '123456', 'teacher', NULL),
(5, 'Искаков Н. Т.', 'iskakov_nt', '123456', 'teacher', NULL),
(6, 'Смирнова О. П.', 'smirnova_op', '123456', 'teacher', NULL),
(7, 'Ахметов Д. К.', 'akhmetov_dk', '123456', 'teacher', NULL),
(8, 'Каримова А. С.', 'karimova_as', '123456', 'teacher', NULL),
(9, 'Петров В. И.', 'petrov_vi', '123456', 'teacher', NULL),
(10, 'Султанов Б. М.', 'sultanov_bm', '123456', 'teacher', NULL),
(11, 'Пушкарев А. А.', 'pushkarev_aa', '123456', 'student', NULL),
(12, 'Абдрахманов А. К.', 'abdrakhmanov_ak', '123456', 'student', NULL),
(13, 'Сапаров Н. Р.', 'saparov_nr', '123456', 'student', NULL),
(14, 'Тулегенов Д. А.', 'tulegenov_da', '123456', 'student', NULL),
(15, 'Нурпеисова М. К.', 'nurpeisova_mk', '123456', 'student', NULL),
(16, 'Жумабеков А. Т.', 'zhumabekov_at', '123456', 'student', NULL),
(17, 'Кадырова А. А.', 'kadyrova_aa', '123456', 'student', NULL),
(18, 'Смагулов Е. Р.', 'smagulov_er', '123456', 'student', NULL),
(19, 'Пушкарев А. А.', 'pushkarev', 'da', 'student', 4),
(20, 'Иванов Д. С.', 'ivanov_ds', 'da', 'student', 4),
(21, 'Петров К. А.', 'petrov_ka', 'da', 'student', 1),
(22, 'Смирнов И. В.', 'smirnov_iv', 'da', 'student', 1),
(23, 'Султанов А. А.', 'sultanov_aa', 'da', 'student', 2),
(24, 'Кузнецов П. П.', 'kuznetsov_pp', 'da', 'student', 2),
(25, 'Ерланов Н. Н.', 'erlanov_nn', 'da', 'student', 3),
(26, 'Ким А. С.', 'kim_as', 'da', 'student', 3),
(27, 'Абдрахманов А. А.', 'abdrakhmanov', 'da', 'student', 5),
(28, 'Омаров Д. С.', 'omarov_ds', 'da', 'student', 5),
(29, 'Сериков А. А.', 'serikov_aa', 'da', 'student', 6),
(30, 'Жумабеков А. С.', 'zhumabekov_as', 'da', 'student', 6),
(31, 'Калиев М. С.', 'kaliev_ms', 'da', 'student', 7),
(32, 'Аманов А. А.', 'amanov_aa', 'da', 'student', 7),
(33, 'Бекетов Д. С.', 'beketov_ds', 'da', 'student', 8),
(34, 'Нурпеисов А. А.', 'nurpeisov_aa', 'da', 'student', 8),
(35, 'Сапаров А. А.', 'saparov_aa', 'da', 'student', 9),
(36, 'Тлеубергенов А. С.', 'tleubergenov', 'da', 'student', 9),
(37, 'Ахметов А. А.', 'akhmetov_aa', 'da', 'student', 10),
(38, 'Алимов Д. С.', 'alimov_ds', 'da', 'student', 10),
(39, 'Искаков А. А.', 'iskakov_aa', 'da', 'student', 11),
(40, 'Муканов А. С.', 'mukanov_as', 'da', 'student', 11),
(41, 'Байтасов А. А.', 'baytasov_aa', 'da', 'student', 12),
(42, 'Каримов А. С.', 'karimov_as', 'da', 'student', 12),
(43, 'Досжанов А. А.', 'doszhanov', 'da', 'student', 13),
(44, 'Акылов А. С.', 'akylov_as', 'da', 'student', 13),
(45, 'Калиев А. А.', 'kaliyev_aa', 'da', 'student', 14),
(46, 'Сеитов А. С.', 'seitov_as', 'da', 'student', 14),
(47, 'Рахимов А. А.', 'rakhimov_aa', 'da', 'student', 15),
(48, 'Мусин А. С.', 'musin_as', 'da', 'student', 15),
(49, 'Токтаров А. А.', 'toktarov_aa', 'da', 'student', 16),
(50, 'Есенов А. С.', 'yesenov_as', 'da', 'student', 16),
(51, 'Максутов А. А.', 'maksutov_aa', 'da', 'student', 17),
(52, 'Алпысбаев А. С.', 'alpysbayev', 'da', 'student', 17),
(53, 'Тасболатов А. А.', 'tasbolatov', 'da', 'student', 18),
(54, 'Ермеков А. С.', 'yermekov_as', 'da', 'student', 18),
(55, 'Куатов А. А.', 'kuatov_aa', 'da', 'student', 19),
(56, 'Жанибеков А. С.', 'zhanibekov', 'da', 'student', 19),
(57, 'Асанов А. А.', 'asanov_aa', 'da', 'student', 20),
(58, 'Сагынов А. С.', 'sagynov_as', 'da', 'student', 20),
(59, 'Айдосов А. А.', 'aidosov_aa', 'da', 'student', 21),
(60, 'Нурланов А. С.', 'nurlanov_as', 'da', 'student', 21),
(61, 'Рысбеков А. А.', 'rysbekov_aa', 'da', 'student', 22),
(62, 'Абдуллин А. С.', 'abdullin_as', 'da', 'student', 22),
(63, 'Аманбеков А. А.', 'amanbekov', 'da', 'student', 23),
(64, 'Серикбаев А. С.', 'serikbaev', 'da', 'student', 23),
(65, 'Даулетов А. А.', 'dauletov_aa', 'da', 'student', 24),
(66, 'Куанышов А. С.', 'kuanyshov', 'da', 'student', 24),
(67, 'Аманжол А. С.', 'student01', 'da', 'student', NULL),
(68, 'Сериков М. Т.', 'student02', 'da', 'student', NULL),
(69, 'Калиева Д. Р.', 'student03', 'da', 'student', NULL),
(70, 'Бекетов А. Н.', 'student04', 'da', 'student', NULL),
(71, 'Рахимова Г. С.', 'student05', 'da', 'student', NULL),
(72, 'Исмаилов Е. К.', 'student06', 'da', 'student', NULL),
(73, 'Нуржанова А. Т.', 'student07', 'da', 'student', NULL),
(74, 'Баймуханов С. Д.', 'student08', 'da', 'student', NULL),
(75, 'Кушпанова М. А.', 'student09', 'da', 'student', NULL),
(76, 'Токтаров Б. Н.', 'student10', 'da', 'student', NULL),
(77, 'Абдуллин А. А.', 'student11', 'da', 'student', NULL),
(78, 'Ержанов Д. К.', 'student12', 'da', 'student', NULL),
(79, 'Мусина А. С.', 'student13', 'da', 'student', NULL),
(80, 'Айдаров Т. М.', 'student14', 'da', 'student', NULL),
(81, 'Каратаева Е. Р.', 'student15', 'da', 'student', NULL),
(82, 'Сагындыков А. Б.', 'student16', 'da', 'student', NULL),
(83, 'Омарова Д. К.', 'student17', 'da', 'student', NULL),
(84, 'Бейсенов Н. А.', 'student18', 'da', 'student', NULL),
(85, 'Молдагалиева А. С.', 'student19', 'da', 'student', NULL),
(86, 'Жанибеков Е. Т.', 'student20', 'da', 'student', NULL),
(87, 'Сатпаева М. Р.', 'student21', 'da', 'student', NULL),
(88, 'Кудайбергенов А. К.', 'student22', 'da', 'student', NULL),
(89, 'Есенова А. А.', 'student23', 'da', 'student', NULL),
(90, 'Тасболатов Н. Д.', 'student24', 'da', 'student', NULL),
(91, 'Кенжетаев М. С.', 'student25', 'da', 'student', NULL),
(92, 'Нургалиева А. Т.', 'student26', 'da', 'student', NULL),
(93, 'Ахметжанов Д. Р.', 'student27', 'da', 'student', NULL),
(94, 'Кулжанова Г. К.', 'student28', 'da', 'student', NULL),
(95, 'Сарсенов Е. А.', 'student29', 'da', 'student', NULL),
(96, 'Жаксылыкова М. Н.', 'student30', 'da', 'student', NULL),
(97, 'Алимов Б. С.', 'student31', 'da', 'student', NULL),
(98, 'Еспенова А. Р.', 'student32', 'da', 'student', NULL),
(99, 'Дуйсебаев Н. Т.', 'student33', 'da', 'student', NULL),
(100, 'Нургалиева К. А.', 'student34', 'da', 'student', NULL),
(101, 'Бакытов М. Е.', 'student35', 'da', 'student', NULL),
(102, 'Сеитова А. Д.', 'student36', 'da', 'student', NULL),
(103, 'Аубакиров Д. С.', 'student37', 'da', 'student', NULL),
(104, 'Мухамеджанова А. К.', 'student38', 'da', 'student', NULL),
(105, 'Калиев Н. Р.', 'student39', 'da', 'student', NULL),
(106, 'Жолдасова М. С.', 'student40', 'da', 'student', NULL);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Индексы таблицы `graduates`
--
ALTER TABLE `graduates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

--
-- Индексы таблицы `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Индексы таблицы `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Индексы таблицы `subject_groups`
--
ALTER TABLE `subject_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Индексы таблицы `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `teacher_groups`
--
ALTER TABLE `teacher_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `group_id` (`group_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT для таблицы `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT для таблицы `graduates`
--
ALTER TABLE `graduates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT для таблицы `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT для таблицы `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT для таблицы `subject_groups`
--
ALTER TABLE `subject_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=683;

--
-- AUTO_INCREMENT для таблицы `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `teacher_groups`
--
ALTER TABLE `teacher_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Ограничения внешнего ключа таблицы `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Ограничения внешнего ключа таблицы `graduates`
--
ALTER TABLE `graduates`
  ADD CONSTRAINT `graduates_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`);

--
-- Ограничения внешнего ключа таблицы `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`);

--
-- Ограничения внешнего ключа таблицы `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`);

--
-- Ограничения внешнего ключа таблицы `subject_groups`
--
ALTER TABLE `subject_groups`
  ADD CONSTRAINT `subject_groups_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `subject_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`);

--
-- Ограничения внешнего ключа таблицы `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `teacher_groups`
--
ALTER TABLE `teacher_groups`
  ADD CONSTRAINT `teacher_groups_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`),
  ADD CONSTRAINT `teacher_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`);

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
