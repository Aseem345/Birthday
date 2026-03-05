<?php
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/db.php';
require_admin();

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function get_section(PDO $pdo, string $key): array
{
    $st = $pdo->prepare("SELECT * FROM sections WHERE section_key=? LIMIT 1");
    $st->execute([$key]);
    $r = $st->fetch();

    if (!$r) {
        // If row not exist, create it (supports video column too)
        $pdo->prepare("INSERT INTO sections(section_key,title,subtitle,body,image,video) VALUES(?,?,?,?,?,?)")
            ->execute([$key, '', '', '', '', '']);
        $st->execute([$key]);
        $r = $st->fetch();
    }

    if (!isset($r['video']))
        $r['video'] = '';
    if (!isset($r['image']))
        $r['image'] = '';
    if (!isset($r['body']))
        $r['body'] = '';
    if (!isset($r['title']))
        $r['title'] = '';
    if (!isset($r['subtitle']))
        $r['subtitle'] = '';

    return $r;
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
$allowedKeys = ['hero', 'letter', 'certificate', 'scrapbook'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_POST['section_key'] ?? '';
    if (!in_array($key, $allowedKeys, true))
        die("Invalid section key");

    $title = $_POST['title'] ?? '';
    $subtitle = $_POST['subtitle'] ?? '';
    $body = $_POST['body'] ?? '';

    $uploadsAbs = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads';
    ensure_uploads_dir($uploadsAbs);

    // IMAGE upload optional (hero/certificate)
    $imageName = null;
    if (!empty($_FILES['image']['name']) && ($_FILES['image']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $tmp = $_FILES['image']['tmp_name'];
        $orig = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowedExt, true)) {
            $msg = "Only jpg/jpeg/png/webp allowed";
        } else {
            $base = safe_file_name(pathinfo($orig, PATHINFO_FILENAME));
            $final = $key . '_' . $base . '_' . time() . '.' . $ext;

            if (move_uploaded_file($tmp, $uploadsAbs . DIRECTORY_SEPARATOR . $final)) {
                $imageName = $final;
            } else {
                $msg = "Image upload failed (permissions?)";
            }
        }
    }

    // VIDEO upload optional (hero only)
    $videoName = null;
    if ($msg === "" && $key === 'hero') {
        if (!empty($_FILES['video']['name']) && ($_FILES['video']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $tmp = $_FILES['video']['tmp_name'];
            $orig = $_FILES['video']['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowedV = ['mp4', 'webm'];

            if (!in_array($ext, $allowedV, true)) {
                $msg = "Only mp4/webm allowed for video";
            } else {
                $base = safe_file_name(pathinfo($orig, PATHINFO_FILENAME));
                $final = $key . '_video_' . $base . '_' . time() . '.' . $ext;

                if (move_uploaded_file($tmp, $uploadsAbs . DIRECTORY_SEPARATOR . $final)) {
                    $videoName = $final;
                } else {
                    $msg = "Video upload failed (permissions?)";
                }
            }
        }
    }

    if ($msg === "") {
        $fields = ["title" => $title, "subtitle" => $subtitle, "body" => $body];
        if ($imageName !== null)
            $fields["image"] = $imageName;
        if ($videoName !== null)
            $fields["video"] = $videoName;

        $set = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $set[] = "$k=?";
            $vals[] = $v;
        }
        $vals[] = $key;

        $sql = "UPDATE sections SET " . implode(",", $set) . " WHERE section_key=?";
        $pdo->prepare($sql)->execute($vals);
        $msg = "Saved ✅";
    }
}

$hero = get_section($pdo, 'hero');
$letter = get_section($pdo, 'letter');
$certificate = get_section($pdo, 'certificate');
$scrapbook = get_section($pdo, 'scrapbook');
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Sections</title>
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
            color: #fff
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            min-height: 110px;
            resize: vertical
        }

        .muted {
            color: #b9b9c8;
            font-size: 12px
        }

        .ok {
            margin-top: 10px;
            color: #b6ffc2
        }

        img {
            max-width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .12);
            margin-top: 10px
        }

        video {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .12);
            margin-top: 10px
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <h2 style="margin:0">Sections</h2>
            <div style="display:flex;gap:10px">
                <a class="btn" href="dashboard.php">Back</a>
                <a class="btn" href="../" target="_blank">Open Site</a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="ok"><?= e($msg) ?></div>
        <?php endif; ?>

        <div class="grid">
            <!-- HERO -->
            <div class="card">
                <h3 style="margin:0 0 6px">Hero</h3>
                <div class="muted">Title + Subtitle + Body + Image + Video</div>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="section_key" value="hero" />

                    <label>Title</label>
                    <input name="title" value="<?= e($hero['title']) ?>" />

                    <label>Subtitle</label>
                    <input name="subtitle" value="<?= e($hero['subtitle']) ?>" />

                    <label>Body (optional)</label>
                    <textarea name="body"><?= e($hero['body']) ?></textarea>

                    <label>Image (optional)</label>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" />
                    <?php if (!empty($hero['image'])): ?>
                        <div class="muted">Current image:</div>
                        <img src="../uploads/<?= e($hero['image']) ?>" alt="hero" />
                    <?php endif; ?>

                    <label>Video (optional) (mp4/webm)</label>
                    <input type="file" name="video" accept=".mp4,.webm" />
                    <?php if (!empty($hero['video'])): ?>
                        <div class="muted">Current video:</div>
                        <video controls>
                            <source src="../uploads/<?= e($hero['video']) ?>">
                        </video>
                    <?php endif; ?>

                    <div style="margin-top:12px">
                        <button class="btn" type="submit">Save Hero</button>
                    </div>
                </form>
            </div>

            <!-- LETTER -->
            <div class="card">
                <h3 style="margin:0 0 6px">Letter</h3>
                <div class="muted">Title optional + Body</div>

                <form method="post">
                    <input type="hidden" name="section_key" value="letter" />

                    <label>Title</label>
                    <input name="title" value="<?= e($letter['title']) ?>" />

                    <label>Subtitle (optional)</label>
                    <input name="subtitle" value="<?= e($letter['subtitle']) ?>" />

                    <label>Body</label>
                    <textarea name="body"><?= e($letter['body']) ?></textarea>

                    <div style="margin-top:12px">
                        <button class="btn" type="submit">Save Letter</button>
                    </div>
                </form>
            </div>

            <!-- CERTIFICATE -->
            <div class="card">
                <h3 style="margin:0 0 6px">Certificate</h3>
                <div class="muted">Title + Subtitle + Body + Image</div>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="section_key" value="certificate" />

                    <label>Title</label>
                    <input name="title" value="<?= e($certificate['title']) ?>" />

                    <label>Subtitle</label>
                    <input name="subtitle" value="<?= e($certificate['subtitle']) ?>" />

                    <label>Body (big line)</label>
                    <input name="body" value="<?= e($certificate['body']) ?>" />

                    <label>Image (optional)</label>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" />

                    <?php if (!empty($certificate['image'])): ?>
                        <div class="muted">Current:</div>
                        <img src="../uploads/<?= e($certificate['image']) ?>" alt="certificate" />
                    <?php endif; ?>

                    <div style="margin-top:12px">
                        <button class="btn" type="submit">Save Certificate</button>
                    </div>
                </form>
            </div>

            <!-- SCRAPBOOK BUTTON SETTINGS -->
            <div class="card">
                <h3 style="margin:0 0 6px">Scrapbook Button</h3>
                <div class="muted">If you want scrapbook as external link, put URL in Body.</div>

                <form method="post">
                    <input type="hidden" name="section_key" value="scrapbook" />

                    <label>Body (URL) or leave empty (use internal scrapbook scene)</label>
                    <input name="body" value="<?= e($scrapbook['body']) ?>" placeholder="https://..." />

                    <label>Title (optional)</label>
                    <input name="title" value="<?= e($scrapbook['title']) ?>" />

                    <label>Subtitle (optional)</label>
                    <input name="subtitle" value="<?= e($scrapbook['subtitle']) ?>" />

                    <div style="margin-top:12px">
                        <button class="btn" type="submit">Save Scrapbook</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>


</html>
