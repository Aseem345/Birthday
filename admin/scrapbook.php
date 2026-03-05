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

// uploads folder (/birthday/uploads)
$uploadsAbs = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads';
ensure_uploads_dir($uploadsAbs);

/* ---------- ADD (upload) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $caption = trim($_POST['caption'] ?? '');
    $sort = (int) ($_POST['sort_order'] ?? 0);

    if (!empty($_FILES['media']['name']) && ($_FILES['media']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $tmp = $_FILES['media']['tmp_name'];
        $orig = $_FILES['media']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        $allowedImg = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedVid = ['mp4', 'webm'];

        $type = null;
        if (in_array($ext, $allowedImg, true))
            $type = 'image';
        if (in_array($ext, $allowedVid, true))
            $type = 'video';

        if ($type === null) {
            $msg = "Only jpg/jpeg/png/webp or mp4/webm allowed";
        } else {
            $base = safe_file_name(pathinfo($orig, PATHINFO_FILENAME));
            $final = 'scrap_' . $base . '_' . time() . '.' . $ext;

            if (move_uploaded_file($tmp, $uploadsAbs . DIRECTORY_SEPARATOR . $final)) {
                $pdo->prepare("INSERT INTO scrapbook_items (media_type, file_name, caption, sort_order) VALUES (?,?,?,?)")
                    ->execute([$type, $final, $caption, $sort]);
                $msg = "Uploaded ✅";
            } else {
                $msg = "Upload failed (permissions?)";
            }
        }
    } else {
        $msg = "Choose a file first";
    }
}

/* ---------- DELETE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $st = $pdo->prepare("SELECT file_name FROM scrapbook_items WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();

    if ($row) {
        $file = $uploadsAbs . DIRECTORY_SEPARATOR . $row['file_name'];
        if (is_file($file))
            @unlink($file);

        $pdo->prepare("DELETE FROM scrapbook_items WHERE id=?")->execute([$id]);
        $msg = "Deleted ✅";
    }
}

/* ---------- UPDATE SORT/CAPTION ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $caption = trim($_POST['caption'] ?? '');
    $sort = (int) ($_POST['sort_order'] ?? 0);

    $pdo->prepare("UPDATE scrapbook_items SET caption=?, sort_order=? WHERE id=?")
        ->execute([$caption, $sort, $id]);

    $msg = "Updated ✅";
}

/* ---------- FETCH ---------- */
$items = $pdo->query("SELECT * FROM scrapbook_items ORDER BY sort_order ASC, id DESC")->fetchAll();
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Scrapbook Manager</title>
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

        a {
            color: #fff;
            text-decoration: none
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
            cursor: pointer
        }

        .card {
            padding: 16px;
            border-radius: 18px;
            background: #121226;
            border: 1px solid rgba(255, 255, 255, .12);
            margin-top: 14px
        }

        label {
            display: block;
            font-size: 12px;
            color: #b9b9c8;
            margin-top: 10px
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px 12px;
            margin-top: 6px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .06);
            color: #fff
        }

        .muted {
            color: #b9b9c8;
            font-size: 12px
        }

        .ok {
            margin-top: 10px;
            color: #b6ffc2
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 12px
        }

        @media(max-width:900px) {
            .grid {
                grid-template-columns: 1fr
            }
        }

        .item {
            padding: 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .10)
        }

        img,
        video {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .12);
            display: block
        }

        .row {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap
        }

        .row>* {
            flex: 1
        }

        .tiny {
            font-size: 12px;
            color: #b9b9c8;
            margin-top: 8px
        }

        form {
            margin: 0
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <h2 style="margin:0">Scrapbook Photos</h2>
            <div style="display:flex;gap:10px">
                <a class="btn" href="/birthday/admin/dashboard.php">Back</a>
                <a class="btn" href="/birthday/" target="_blank">Open Site</a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="ok">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3 style="margin:0 0 6px">Add New</h3>
            <div class="muted">Upload image/video. Sort order small number = first.</div>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload" />

                <label>Media file (jpg/png/webp OR mp4/webm)</label>
                <input type="file" name="media" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm" required />

                <div class="row">
                    <div>
                        <label>Caption (optional)</label>
                        <input name="caption" placeholder="The beginning..." />
                    </div>
                    <div>
                        <label>Sort order</label>
                        <input name="sort_order" type="number" value="0" />
                    </div>
                </div>

                <div style="margin-top:12px">
                    <button class="btn" type="submit">Upload</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 style="margin:0 0 6px">All Items</h3>
            <div class="muted">
                <?= count($items) ?> items
            </div>

            <div class="grid">
                <?php if (!$items): ?>
                    <div class="item">
                        <div class="muted">No scrapbook items yet. Upload above.</div>
                    </div>
                <?php endif; ?>

                <?php foreach ($items as $it): ?>
                    <div class="item">
                        <?php if ($it['media_type'] === 'video'): ?>
                            <video controls>
                                <source src="/birthday/uploads/<?= e($it['file_name']) ?>">
                            </video>
                        <?php else: ?>
                            <img src="/birthday/uploads/<?= e($it['file_name']) ?>" alt="scrap">
                        <?php endif; ?>

                        <div class="tiny">ID:
                            <?= (int) $it['id'] ?> • Type:
                            <?= e($it['media_type']) ?>
                        </div>

                        <form method="post" style="margin-top:10px">
                            <input type="hidden" name="action" value="update" />
                            <input type="hidden" name="id" value="<?= (int) $it['id'] ?>" />

                            <label>Caption</label>
                            <input name="caption" value="<?= e($it['caption']) ?>" />

                            <label>Sort order</label>
                            <input name="sort_order" type="number" value="<?= (int) $it['sort_order'] ?>" />

                            <div class="row" style="margin-top:12px">
                                <button class="btn" type="submit">Save</button>
                            </div>
                        </form>

                        <form method="post" onsubmit="return confirm('Delete this item?')" style="margin-top:10px">
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) $it['id'] ?>" />
                            <button class="btn" type="submit" style="border-color:rgba(239,68,68,.35)">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>


</html>
