<?php
require __DIR__ . '/../config/auth.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');

    if ($u === 'admin' && $p === 'admin123') {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header("Location: /birthday/admin/dashboard.php");
        exit;
    } else {
        $error = "Wrong username or password";
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Login</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui;
            background: #0a0a12;
            color: #fff;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center
        }

        .card {
            width: 380px;
            background: #121226;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, .45)
        }

        input {
            width: 100%;
            padding: 12px 12px;
            margin: 8px 0;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .06);
            color: #fff
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #ff4fd8, #8b5cf6);
            color: #fff;
            font-weight: 800;
            cursor: pointer
        }

        .muted {
            color: #b9b9c8;
            font-size: 12px
        }

        .err {
            margin-top: 10px;
            color: #ffb4b4;
            font-size: 13px
        }
    </style>
</head>

<body>
    <form class="card" method="post" autocomplete="off">
        <h2 style="margin:0 0 6px">Admin Login</h2>
        <div class="muted">Use: admin / admin123</div>

        <input name="username" placeholder="Username" required>
        <input name="password" type="password" placeholder="Password" required>

        <button type="submit">Login</button>

            <?php if ($error): ?>
            <div class="err">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
        <?php endif; ?>
    </form>
</body>

</html>