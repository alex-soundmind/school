<?php
require_once 'config.php';
session_start();

// --- ОБЩИЕ ПЕРЕМЕННЫЕ ---
$is_logged_in = isset($_SESSION['user']);
$action = $_GET['action'] ?? 'list'; // 'list', 'create', 'edit', 'delete'
$table = $_GET['table'] ?? 'trams';
$id = $_GET['id'] ?? null;

// --- СЛОВАРЬ ТАБЛИЦ ---
$tables = [
    'students' => 'Студенты',
    'teachers' => 'Преподаватели',
    'courses' => 'Курсы',
    'grades' => 'Успеваемость',
    'users' => 'Пользователи'
];

if (!isset($tables[$table])) {
    die('<p class="error">Неверная таблица</p>');
}

// --- ПОЛУЧЕНИЕ ИНФОРМАЦИИ О СТОЛБЦАХ И PK ---
try {
    $stmt = $pdo->query("SELECT * FROM $table LIMIT 0");
    $columns = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        $columns[] = $meta['name'];
    }
    $pk = $columns[0] ?? 'id';
} catch (PDOException $e) {
    die('<p class="error">Ошибка получения структуры таблицы.</p>');
}

// --- ЛОГИКА ОБРАБОТКИ ДАННЫХ (POST-ЗАПРОСЫ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {
    $data = [];
    foreach ($columns as $col) {
        if ($col === $pk && empty($_POST[$pk])) continue;
        if (isset($_POST[$col])) {
            $data[$col] = $_POST[$col] === '' ? null : $_POST[$col];
        }
    }
    
    try {
        if ($action === 'create') {
            unset($data[$pk]); // Убираем первичный ключ при создании
            $cols = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
            $stmt->execute(array_values($data));
        } elseif ($action === 'edit' && $id) {
            $set_clauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $stmt = $pdo->prepare("UPDATE $table SET $set_clauses WHERE $pk = ?");
            $stmt->execute([...array_values($data), $id]);
        }
    } catch (PDOException $e) {
        // Можно добавить более детальную обработку ошибок
        die('<p class="error">Ошибка сохранения данных: ' . $e->getMessage() . '</p>');
    }
    
    header("Location: index.php?table=$table");
    exit;
}

// --- ЛОГИКА УДАЛЕНИЯ (GET-ЗАПРОС) ---
if ($action === 'delete' && $id && $is_logged_in) {
    try {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $pk = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        die('<p class="error">Ошибка удаления: ' . $e->getMessage() . '</p>');
    }
    
    header("Location: index.php?table=$table");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лингвистическая школа: <?= $tables[$table] ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <nav>
            <?php foreach ($tables as $tbl_name => $tbl_title): ?>
                <a href="?table=<?= $tbl_name ?>" class="<?= $table === $tbl_name ? 'active' : '' ?>"><?= $tbl_title ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="container">
        <?php if ($action === 'list'): ?>
            <h2><?= $tables[$table] ?></h2>
            <?php
            $stmt = $pdo->query("SELECT * FROM $table ORDER BY $pk");
            $rows = $stmt->fetchAll();

            if (!$rows): ?>
                <p>В этой таблице пока нет данных.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col):
                                if ($table === 'users' && $col === 'password' && !$is_logged_in) continue;
                            ?>
                                <th><?= translate($col) ?></th>
                            <?php endforeach; ?>
                            <?php if ($is_logged_in): ?><th>Действия</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($row as $key => $val):
                                    if ($table === 'users' && $key === 'password' && !$is_logged_in) continue;
                                ?>
                                    <td><?= htmlspecialchars((string)$val, ENT_QUOTES) ?></td>
                                <?php endforeach; ?>

                                <?php if ($is_logged_in): ?>
                                    <td class="actions">
                                        <a href="?table=<?= $table ?>&action=edit&id=<?= $row[$pk] ?>" class="edit">✏️</a>
                                        <a href="?table=<?= $table ?>&action=delete&id=<?= $row[$pk] ?>" class="delete" onclick="return confirm('Вы уверены, что хотите удалить эту запись?')">❌</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php if ($is_logged_in): ?>
                <a href="?table=<?= $table ?>&action=create" class="btn-add"><button>Добавить новую запись</button></a>
            <?php endif; ?>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <?php
            if (!$is_logged_in) die('Доступ запрещен.');

            $values = [];
            if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("SELECT * FROM $table WHERE $pk = ?");
                $stmt->execute([$id]);
                $values = $stmt->fetch();
                if (!$values) die('Запись не найдена.');
            }
            ?>
            <h2><?= $action === 'create' ? 'Добавление записи' : 'Редактирование записи' ?></h2>
            <form method="post" action="?table=<?= $table ?>&action=<?= $action ?><?= $id ? '&id='.$id : '' ?>">
                <?php foreach ($columns as $col):
                    if ($col === $pk) continue; // Не показываем поле с первичным ключом
                    $val = $values[$col] ?? '';
                    $label = translate($col);
                    
                    // Автоопределение типа поля
                    $type = 'text';
                    if (str_contains($col, '_date')) $type = 'date';
                    elseif (str_contains($col, '_time')) $type = 'time';
                    elseif (in_array($col, ['capacity', 'manufacture_year'])) $type = 'number';
                    elseif (str_contains($col, 'email')) $type = 'email';
                    elseif (str_contains($col, 'password')) $type = 'password';
                    
                    if (str_contains($col, 'description')): ?>
                        <label for="<?= $col ?>"><?= $label ?></label>
                        <textarea id="<?= $col ?>" name="<?= $col ?>" required><?= htmlspecialchars($val) ?></textarea>
                    <?php else: ?>
                        <label for="<?= $col ?>"><?= $label ?></label>
                        <input type="<?= $type ?>" id="<?= $col ?>" name="<?= $col ?>" value="<?= htmlspecialchars($val) ?>" required>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="form-actions">
                    <input type="submit" value="Сохранить">
                    <a href="?table=<?= $table ?>"><button type="button" class="danger">Отмена</button></a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <footer>
        <?php if (!$is_logged_in): ?>
            <a href="auth.php?mode=login">Войти</a> | <a href="auth.php?mode=register">Регистрация</a>
        <?php else: ?>
            Пользователь: <b><?= htmlspecialchars($_SESSION['user']['name']) ?></b> | <a href="logout.php">Выйти</a>
        <?php endif; ?>
    </footer>
</body>
</html>