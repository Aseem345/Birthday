<?php
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/db.php';
require_admin();

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function ensure_uploads_dir(string $abs): void
{
    if (!is_dir($abs))
        mkdir($abs, 0777, true);
}
function safe_file_name(string $name): string
{
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return $name ?: ('file_' . time());
}

$msg = "";

/* delete */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare("DELETE FROM songs WHERE id=?")->execute([$id]);
    header("Location: songs.php?msg=Deleted");
    exit;
}

/* add/update */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = $_POST['song_title'] ?? '';
    $quote = $_POST['quote'] ?? '';
    $url = $_POST['spotify_url'] ?? '';
    $sort = (int) ($_POST['sort_order'] ?? 0);

    $cover = null;
    if (!empty($_FILES['cover_image']['name']) && ($_FILES['cover_image']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $tmp = $_FILES['cover_image']['tmp_name'];
        $orig = $_FILES['cover_image']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowedExt, true)) {
            $uploadsAbs = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads';
            ensure_uploads_dir($uploadsAbs);
            $base = safe_file_name(pathinfo($orig, PATHINFO_FILENAME));
            $final = "song_" . $base . "_" . time() . "." . $ext;
            if (move_uploaded_file($tmp, $uploadsAbs . DIRECTORY_SEPARATOR . $final)) {
                $cover = $final;
            }
        } else {
            $msg = "Only jpg/jpeg/png/webp allowed";
        }
    }

    if ($msg === "") {
        if ($id > 0) {
            if ($cover !== null) {
                $st = $pdo->prepare("UPDATE songs SET song_title=?, quote=?, spotify_url=?, cover_image=?, sort_order=? WHERE id=?");
                $st->execute([$title, $quote, $url, $cover, $sort, $id]);
            } else {
                $st = $pdo->prepare("UPDATE songs SET song_title=?, quote=?, spotify_url=?, sort_order=? WHERE id=?");
                $st->execute([$title, $quote, $url, $sort, $id]);
            }
            header("Location: songs.php?msg=Updated");
            exit;
        } else {
            $st = $pdo->prepare("INSERT INTO songs(song_title,quote,spotify_url,cover_image,sort_order) VALUES(?,?,?,?,?)");
            $st->execute([$title, $quote, $url, $cover, $sort]);
            header("Location: songs.php?msg=Added");
            exit;
        }
    }
}

$msg = $_GET['msg'] ?? $msg;

/* edit load */
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $st = $pdo->prepare("SELECT * FROM songs WHERE id=?");
    $st->execute([$id]);
    $edit = $st->fetch();
}

$songs = $pdo->query("SELECT * FROM songs ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Songs</title>
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
            grid-template-columns: 420px 1fr;
            gap: 12px;
            margin-top: 16px
        }

        @media(max-width:900px) {
            .grid {
                grid-template-columns: 1fr
            }
        }

        .card {
            padding: 16px;
            border-radius: 18px;
            background: #121226;
            border: 1px solid rgba(255, 255, 255, .12)
        }

        label {
            display: block;
            font-size: 12px;
            color: #b9b9c8;
            margin-top: 10px
        }

        input,
        textarea {
            width: 100%;
            padding: 10px 12px;
            margin-top: 6px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .06);
            color: #fff
        }

        textarea {
            min-height: 95px;
            resize: vertical
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, .10);
            font-size: 13px
        }

        th {
            color: #b9b9c8;
            text-align: left
        }

        img {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, .12)
        }

        .ok {
            color: #b6ffc2;
            margin-top: 10px
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <h2 style="margin:0">Songs</h2>
            <div style="display:flex;gap:10px">
                <a class="btn" href="/birthday/admin/dashboard.php">Back</a>
                <a class="btn" href="/birthday/" target="_blank">Open Site</a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="ok"><?= e($msg) ?></div><?php endif; ?>

        <div class="grid">
            <div class="card">
                <h3 style="margin:0 0 6px"><?= $edit ? "Edit Song" : "Add Song" ?></h3>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>" />

                    <label>Song Title</label>
                    <input name="song_title" required value="<?= e($edit['song_title'] ?? '') ?>" />

                    <label>Quote</label>
                    <textarea name="quote"><?= e($edit['quote'] ?? '') ?></textarea>

                    <label>Spotify URL</label>
                    <input name="spotify_url" value="<?= e($edit['spotify_url'] ?? '') ?>" placeholder="https://..." />

                    <label>Sort Order</label>
                    <input name="sort_order" type="number" value="<?= e($edit['sort_order'] ?? 0) ?>" />

                    <label>Cover Image (optional)</label>
                    <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp" />

                    <?php if (!empty($edit['cover_image'])): ?>
                        <div style="margin-top:10px">
                            <img src="/birthday/uploads/<?= e($edit['cover_image']) ?>" alt="cover" />
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
                        <button class="btn" type="submit"><?= $edit ? "Update" : "Add" ?></button>
                        <?php if ($edit): ?><a class="btn" href="songs.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3 style="margin:0 0 10px">All Songs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Title</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($songs as $s): ?>
                            <tr>
                                <td>
                                        <?php if (!empty($s['cover_image'])): ?>
                                        <img src="/birthday/uploads/<?= e($s['cover_image']) ?>" alt="" />
                                        <?php else: ?>
                                        —
                                        <?php endif; ?>
                                </td>
                                <td><?= e($s['song_title']) ?></td>
                                <td><?= (int) $s['sort_order'] ?></td>
                                <td>
                                    <a class="btn" href="songs.php?edit=<?= (int) $s['id'] ?>">Edit</a>
                                    <a class="btn" href="songs.php?delete=<?= (int) $s['id'] ?>"
                                        onclick="return confirm('Delete this song?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$songs): ?>
                            <tr>
                                <td colspan="4" style="color:#b9b9c8">No songs yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>


</html>
