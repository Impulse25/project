<?php

session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['login']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE login = ?
    ");

    $stmt->execute([$login]);

    $user = $stmt->fetch();

    if ($user && $user['password'] === $password) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        header("Location: index.php");
        exit;

    }

    $error = "Неверный логин или пароль";
}

?>

<!DOCTYPE html>
<html lang="ru">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>СВГТК Портал</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <h1 class="login-title">
            СВГТК Портал
        </h1>

        <p class="login-subtitle">
            Аналитика и отчётность
        </p>

        <?php if($error): ?>

            <div class="error-box">
                <?= $error ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-group">

                <label class="form-label">
                    Логин
                </label>

                <input
                    type="text"
                    name="login"
                    class="form-control"
                    required>

            </div>

            <div class="form-group">

                <label class="form-label">
                    Пароль
                </label>

                <input
                    type="password"
                    name="password"
                    class="form-control"
                    required>

            </div>

            <button
                type="submit"
                class="btn btn-primary login-btn">

                Войти

            </button>

        </form>

    </div>

</div>

</body>
</html>