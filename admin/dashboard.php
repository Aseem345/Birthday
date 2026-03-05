<?php
require __DIR__ . '/../config/auth.php';
require_admin();

/**
 * Auto base path (works for /birthday/admin/dashboard.php OR any folder name)
 * Example:
 *   SCRIPT_NAME = /birthday/admin/dashboard.php
 *   BASE = /birthday
 */
$BASE = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($BASE === '')
    $BASE = '/';
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui;
            background: #0a0a12;
            color: #fff
        }

        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .06);
            color: #fff;
            text-decoration: none
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 16px
        }

        .card {
            padding: 16px;
            border-radius: 18px;
            background: #121226;
            border: 1px solid rgba(255, 255, 255, .12)
        }

        .muted {
            color: #b9b9c8;
            font-size: 13px
        }

        @media(max-width:900px) {
            .grid {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <h2 style="margin:0">Admin Dashboard</h2>
            <div style="display:flex;gap:10px">
                <!-- Open site -->
                <a class="btn" href="<?= htmlspecialchars($BASE) ?>/" target="_blank">Open Website</a>

                <!-- Logout -->
                <a class="btn" href="<?= htmlspecialchars($BASE) ?>/admin/logout.php">Logout</a>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h3 style="margin:0 0 6px">Sections</h3>
                <div class="muted">Hero / Letter / Certificate update</div>
                <div style="margin-top:10px">
                    <a class="btn" href="<?= htmlspecialchars($BASE) ?>/admin/sections.php">Manage</a>
                </div>
            </div>

            <div class="card">
                <h3 style="margin:0 0 6px">Scrapbook Photos</h3>
                <div class="muted">Multiple photos/videos add/edit/delete + reorder</div>
                <div style="margin-top:10px">
                    <a class="btn" href="<?= htmlspecialchars($BASE) ?>/admin/scrapbook.php">Manage</a>
                </div>
            </div>

            <div class="card">
                <h3 style="margin:0 0 6px">Songs</h3>
                <div class="muted">Soundtracks add/edit/delete + image</div>
                <div style="margin-top:10px">
                    <a class="btn" href="<?= htmlspecialchars($BASE) ?>/admin/songs.php">Manage</a>
                </div>
            </div>

            <div class="card">
                <h3 style="margin:0 0 6px">Quiz</h3>
                <div class="muted">Question + options set (1 correct)</div>
                <div style="margin-top:10px">
                    <a class="btn" href="<?= htmlspecialchars($BASE) ?>/admin/quiz.php">Manage</a>
                </div>
            </div>
        </div>
    </div>
</body>


</html>
