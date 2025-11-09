<?php
// config.php

// Настройки подключения к базе данных
$db   = 'school';
$host = 'dpg-d4847mbipnbc73d8hlm0-a.singapore-postgres.render.com';
$user = 'user';
$pass = 'RFa4bfOpswyRFBK3cZvaU9okEORsFxYO';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // В реальном приложении здесь лучше логировать ошибку, а не выводить ее пользователю
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Функция для перевода названий столбцов
function translate($column) {
    static $map = [
        // Students
        'student_id' => 'ID студента',
        'full_name' => 'ФИО',
        'contact_info' => 'Контактная информация',
        'language_level' => 'Уровень языка',
        'learning_history' => 'История обучения',

        // Teachers
        'teacher_id' => 'ID преподавателя',
        'qualification' => 'Квалификация',
        'experience' => 'Опыт',
        'courses_taught' => 'Преподаваемые курсы',

        // Courses
        'course_id' => 'ID курса',
        'title' => 'Название',
        'description' => 'Описание',
        'level' => 'Уровень',

        // Grades
        'grade_id' => 'ID оценки',
        'exam_date' => 'Дата экзамена',
        'result' => 'Результат',
        'comment' => 'Комментарий',

        // Users
        'user_id' => 'ID пользователя',
        'name' => 'ФИО',
        'email' => 'E-mail',
        'password' => 'Пароль',
    ];
    return $map[$column] ?? ucfirst(str_replace('_', ' ', $column));
}
?>
