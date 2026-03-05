<?php
// index.php
require __DIR__ . '/config/db.php';

/* ---------- BASE PATH (CHANGE ONLY IF FOLDER NAME CHANGES) ---------- */
$BASE = "/birthday";

/* ---------- helpers ---------- */
function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function get_section(PDO $pdo, string $key): array
{
    $st = $pdo->prepare("SELECT * FROM sections WHERE section_key=? LIMIT 1");
    $st->execute([$key]);
    $row = $st->fetch();
    return $row ?: [
        "section_key" => $key,
        "title" => "",
        "subtitle" => "",
        "body" => "",
        "image" => "",
        "video" => ""
    ];
}

function nl2p(string $text): string
{
    $text = trim($text);
    if ($text === '')
        return '';
    $parts = preg_split("/\R{2,}/", $text);
    $html = "";
    foreach ($parts as $p) {
        $html .= "<p>" . nl2br(e(trim($p))) . "</p>";
    }
    return $html;
}

/* ---------- fetch content ---------- */
$hero = get_section($pdo, 'hero');
$letter = get_section($pdo, 'letter');
$certificate = get_section($pdo, 'certificate');
$scrapbook = get_section($pdo, 'scrapbook');

if (!isset($hero['video']))
    $hero['video'] = '';

$songs = $pdo->query("SELECT * FROM songs ORDER BY sort_order ASC, id ASC")->fetchAll();

/* ---------- MULTI QUIZ (NEW) ---------- */
$quizPack = [];
try {
    // sort_order column may exist (we added in admin)
    $qs = $pdo->query("SELECT id, question, COALESCE(sort_order,0) AS sort_order FROM quiz_questions ORDER BY sort_order ASC, id ASC")->fetchAll();
    foreach ($qs as $qq) {
        $st = $pdo->prepare("SELECT id, option_text, is_correct FROM quiz_options WHERE question_id=? ORDER BY id ASC");
        $st->execute([(int) $qq['id']]);
        $opts = $st->fetchAll();

        if ($opts) {
            $quizPack[] = [
                "id" => (int) $qq['id'],
                "question" => (string) $qq['question'],
                "options" => array_map(function ($o) {
                    return [
                        "id" => (int) $o['id'],
                        "text" => (string) $o['option_text'],
                        "is_correct" => (int) $o['is_correct']
                    ];
                }, $opts)
            ];
        }
    }
} catch (Throwable $e) {
    $quizPack = [];
}

/* ---------- scrapbook items ---------- */
$scrapItems = [];
try {
    $scrapItems = $pdo->query("SELECT * FROM scrapbook_items ORDER BY sort_order ASC, id DESC")->fetchAll();
} catch (Throwable $e) {
    $scrapItems = [];
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>
        <?= e($hero['title'] ?: 'Happy Birthday') ?>
    </title>

    <style>
        :root {
            --bg: #0a0a12;
            --card: #121226;
            --muted: #b9b9c8;
            --text: #fff;
            --pink: #ff4fd8;
            --purple: #8b5cf6;
            --ring: rgba(255, 79, 216, .25);
            --shadow: 0 12px 35px rgba(0, 0, 0, .45);
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
            background: radial-gradient(900px 500px at 20% 10%, rgba(255, 79, 216, .20), transparent 55%),
                radial-gradient(700px 450px at 80% 25%, rgba(139, 92, 246, .18), transparent 55%),
                var(--bg);
            color: var(--text);
            overflow: hidden;
            height: 100vh;
        }

        #fx {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        .wrap {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 34px 18px 90px;
            height: 100vh;
            overflow: hidden;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0 18px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .badge {
            font-size: 12px;
            padding: 6px 10px;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 999px;
            color: var(--muted);
            background: rgba(255, 255, 255, .04);
        }

        .scenes {
            position: relative;
            height: calc(100vh - 120px);
        }

        .scene {
            position: absolute;
            inset: 0;
            opacity: 0;
            transform: translateY(14px) scale(.99);
            pointer-events: none;
            transition: opacity .45s ease, transform .45s ease;
            overflow: auto;
            padding-right: 6px;
        }

        .scene.active {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .scene::-webkit-scrollbar {
            width: 8px;
        }

        .scene::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .12);
            border-radius: 99px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 18px;
            align-items: stretch;
            margin-top: 8px;
        }

        @media (max-width:900px) {
            .hero {
                grid-template-columns: 1fr
            }
        }

        .card {
            background: linear-gradient(180deg, rgba(255, 255, 255, .05), rgba(255, 255, 255, .02));
            border: 1px solid rgba(255, 255, 255, .10);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 22px;
            backdrop-filter: blur(10px);
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(34px, 4vw, 54px);
            line-height: 1.05;
            letter-spacing: -.5px;
        }

        .hero h2 {
            margin: 10px 0 0;
            font-weight: 500;
            color: var(--muted);
            font-size: clamp(16px, 2vw, 20px);
        }

        .hero .cta {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .btn {
            border: 0;
            background: linear-gradient(135deg, var(--pink), var(--purple));
            color: #fff;
            padding: 12px 16px;
            border-radius: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(255, 79, 216, .18);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn.secondary {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            box-shadow: none;
            color: #fff;
        }

        .btn:hover {
            filter: brightness(1.04)
        }

        .mini {
            font-size: 12px;
            color: var(--muted);
        }

        .photo {
            position: relative;
            overflow: hidden;
            min-height: 240px;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, .10);
            background: rgba(255, 255, 255, .04);
        }

        .photo img,
        .photo video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            filter: saturate(1.05) contrast(1.05);
            transform: scale(1.02);
        }

        .photo .overlay {
            position: absolute;
            inset: auto 14px 14px 14px;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(0, 0, 0, .45);
            border: 1px solid rgba(255, 255, 255, .12);
        }

        .section-title {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 10px;
            margin: 12px 0 12px;
        }

        .section-title h3 {
            margin: 0;
            font-size: 18px;
            letter-spacing: .2px;
        }

        .section-title p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        @media (max-width:900px) {
            .grid {
                grid-template-columns: 1fr
            }
        }

        .song {
            padding: 16px;
            border-radius: 18px;
            background: rgba(18, 18, 38, .68);
            border: 1px solid rgba(255, 255, 255, .10);
            transition: transform .15s ease;
        }

        .song:hover {
            transform: translateY(-2px)
        }

        .song .top {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .song img {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .05);
        }

        .song h4 {
            margin: 0;
            font-size: 15px
        }

        .song .quote {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45
        }

        .song a {
            margin-top: 12px;
            display: inline-block;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            padding: 9px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .05);
        }

        .song a:hover {
            background: rgba(255, 255, 255, .08)
        }

        .letter p {
            color: #eaeaf5;
            line-height: 1.75;
            margin: 0 0 12px
        }

        .letter p:last-child {
            margin-bottom: 0
        }

        .letter .hint {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px dashed rgba(255, 255, 255, .18);
            color: var(--muted);
            background: rgba(255, 255, 255, .03);
            font-size: 13px;
        }

        .opts {
            display: grid;
            gap: 10px
        }

        .opt {
            text-align: left;
            border-radius: 16px;
            padding: 12px 14px;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .05);
            color: #fff;
            cursor: pointer;
            font-weight: 600;
        }

        .opt:hover {
            background: rgba(255, 255, 255, .08)
        }

        .result {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .04);
            color: var(--muted);
            display: none;
        }

        .result.ok {
            border-color: rgba(34, 197, 94, .35);
            box-shadow: 0 0 0 4px rgba(34, 197, 94, .10);
        }

        .result.bad {
            border-color: rgba(239, 68, 68, .35);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, .10);
        }

        .cert {
            position: relative;
            overflow: hidden;
        }

        .cert:before {
            content: "";
            position: absolute;
            inset: -2px;
            background: radial-gradient(400px 220px at 20% 20%, rgba(255, 79, 216, .18), transparent 60%),
                radial-gradient(380px 220px at 80% 30%, rgba(139, 92, 246, .18), transparent 60%);
            pointer-events: none;
        }

        .cert .inner {
            position: relative;
            padding: 24px;
            border-radius: 22px;
            background: rgba(0, 0, 0, .25);
            border: 1px solid rgba(255, 255, 255, .12);
        }

        .cert h3 {
            margin: 0;
            font-size: 20px
        }

        .cert h4 {
            margin: 8px 0 0;
            color: var(--muted);
            font-weight: 500
        }

        .cert .big {
            margin: 14px 0 0;
            font-size: clamp(22px, 3vw, 30px);
            font-weight: 800;
            letter-spacing: .2px;
        }

        footer {
            margin-top: 12px;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        .scene-actions {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        /* TYPEWRITER */
        .letterText p {
            color: #eaeaf5;
            line-height: 1.75;
            margin: 0 0 12px;
        }

        .letterText p:last-child {
            margin-bottom: 0;
        }

        .cursor {
            display: inline-block;
            width: 10px;
            margin-left: 2px;
            border-left: 2px solid rgba(255, 255, 255, .75);
            animation: blink 1s steps(1) infinite;
        }

        @keyframes blink {
            50% {
                opacity: 0;
            }
        }

        /* Scrapbook BIG layout */
        .scrap-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        @media (max-width:900px) {
            .scrap-grid {
                grid-template-columns: 1fr;
            }
        }

        .scrap-card {
            padding: 16px;
            border-radius: 18px;
            background: rgba(18, 18, 38, .68);
            border: 1px solid rgba(255, 255, 255, .10);
            transition: transform .15s ease;
            overflow: hidden;
        }

        .scrap-card:hover {
            transform: translateY(-2px);
        }

        .scrapMedia {
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, .12);
            display: block;
        }

        .scrap-caption {
            margin-top: 12px;
            font-size: 15px;
            font-weight: 800;
        }

        .scrap-sub {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        /* ✅ quiz progress small */
        .quiz-progress {
            margin-top: 10px;
            font-size: 12px;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
    </style>
</head>

<body>
    <canvas id="fx"></canvas>

    <div class="wrap">
        <header>
            <div class="logo">
                <span style="font-size:18px">💖</span>
                <span>
                    <?= e($hero['subtitle'] ?: 'Birthday') ?>
                </span>
                <span class="badge">✰༆🅺𝐞𝐧𝐳𝐨🅱︎𝐨𝐦𝐦𝐢𝐲</span>
            </div>
        </header>

        <div class="scenes">

            <!-- HERO -->
            <div class="scene" data-scene="hero" id="hero">
                <div class="hero">
                    <div class="card">
                        <h1>
                            <?= e($hero['title'] ?: 'Happy Birthday') ?>
                        </h1>
                        <h2>
                            <?= e($hero['subtitle'] ?: 'To my favorite person') ?>
                        </h2>

                        <?php if (!empty($hero['body'])): ?>
                            <div style="margin-top:14px;color:var(--muted);line-height:1.7">
                                <?= nl2p($hero['body']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="cta">
                            <button class="btn js-go" data-go="soundtracks">🎁 Open My Gift</button>
                            <button class="btn secondary js-go" data-go="letter">💌 Read my letter</button>

                            <?php if (!empty($scrapItems)): ?>
                                <button class="btn secondary js-go" data-go="scrapbook">📸 View our scrapbook</button>
                            <?php else: ?>
                                <?php $scrapUrl = trim((string) ($scrapbook['body'] ?? "")); ?>
                                <?php if ($scrapUrl !== ""): ?>
                                    <a class="btn secondary" target="_blank" rel="noopener" href="<?= e($scrapUrl) ?>">📸 View
                                        our scrapbook</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="photo">
                        <?php if (!empty($hero['video'])): ?>
                            <video autoplay muted loop playsinline>
                                <source src="<?= $BASE ?>/uploads/<?= e($hero['video']) ?>" type="video/mp4">
                            </video>
                        <?php elseif (!empty($hero['image'])): ?>
                            <img src="<?= $BASE ?>/uploads/<?= e($hero['image']) ?>" alt="Hero image" />
                        <?php else: ?>
                            <div
                                style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--muted);padding:22px;text-align:center">
                                (Hero image/video not set)<br />Admin → Sections → hero → upload pannunga
                            </div>
                        <?php endif; ?>

                        <div class="overlay">
                            <div style="font-weight:800">✨ Today is special</div>
                            <div style="color:var(--muted);font-size:13px;margin-top:4px">Because you exist.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SOUNDTRACKS -->
            <div class="scene" data-scene="soundtracks" id="soundtracks">
                <div class="section-title">
                    <div>
                        <h3>🎶 Soundtracks</h3>
                        <p>Our little playlist of feelings</p>
                    </div>
                    <div class="mini">
                        <?= count($songs) ?> tracks
                    </div>
                </div>

                <div class="grid">
                    <?php if (!$songs): ?>
                        <div class="song">
                            <h4>No songs yet</h4>
                            <p class="quote">Admin → Songs la add pannunga.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($songs as $s): ?>
                        <div class="song">
                            <div class="top">
                                <?php if (!empty($s['cover_image'])): ?>
                                    <img src="<?= $BASE ?>/uploads/<?= e($s['cover_image']) ?>" alt="cover">
                                <?php else: ?>
                                    <div
                                        style="width:52px;height:52px;border-radius:14px;border:1px solid rgba(255,255,255,.12);
                                    display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.05)">
                                        🎵</div>
                                <?php endif; ?>

                                <div>
                                    <h4>
                                        <?= e($s['song_title']) ?>
                                    </h4>
                                    <div class="mini">for you 💗</div>
                                </div>
                            </div>

                            <?php if (!empty($s['quote'])): ?>
                                <div class="quote">
                                    <?= nl2br(e($s['quote'])) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($s['spotify_url'])): ?>
                                <a href="<?= e($s['spotify_url']) ?>" target="_blank" rel="noopener">▶ Open</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="scene-actions">
                    <button class="btn secondary js-go" data-go="hero">⬅ Back</button>
                    <?php if (!empty($scrapItems)): ?>
                        <button class="btn js-go" data-go="scrapbook">Next ➡</button>
                    <?php else: ?>
                        <button class="btn js-go" data-go="letter">Next ➡</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SCRAPBOOK -->
            <div class="scene" data-scene="scrapbook" id="scrapbook">
                <div class="section-title">
                    <div>
                        <h3>📸 Scrapbook</h3>
                        <p>Our photos & moments</p>
                    </div>
                    <div class="mini">
                        <?= count($scrapItems) ?> memories
                    </div>
                </div>

                <div class="scrap-grid">
                    <?php if (!$scrapItems): ?>
                        <div class="scrap-card">
                            <h4 style="margin:0 0 6px">No scrapbook items yet</h4>
                            <p class="scrap-sub">Admin → Scrapbook Photos la upload pannunga.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($scrapItems as $it): ?>
                        <div class="scrap-card">
                            <?php if (($it['media_type'] ?? 'image') === 'video'): ?>
                                <video class="scrapMedia" controls>
                                    <source src="<?= $BASE ?>/uploads/<?= e($it['file_name']) ?>">
                                </video>
                            <?php else: ?>
                                <img class="scrapMedia" src="<?= $BASE ?>/uploads/<?= e($it['file_name']) ?>" alt="scrap">
                            <?php endif; ?>

                            <?php if (!empty($it['caption'])): ?>
                                <div class="scrap-caption">
                                    <?= e($it['caption']) ?>
                                </div>
                            <?php else: ?>
                                <div class="scrap-caption">💜 Memory</div>
                            <?php endif; ?>
                            <div class="scrap-sub">Our little moment ✨</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="scene-actions">
                    <button class="btn secondary js-go" data-go="soundtracks">⬅ Back</button>
                    <button class="btn js-go" data-go="letter">Next ➡</button>
                </div>
            </div>

            <!-- LETTER -->
            <div class="scene" data-scene="letter" id="letter">
                <div class="section-title">
                    <div>
                        <h3>💌 Letter</h3>
                        <p>Something I want you to read slowly</p>
                    </div>
                </div>

                <div class="card letter">
                    <?php if (!empty($letter['title'])): ?>
                        <h3 style="margin:0 0 10px">
                            <?= e($letter['title']) ?>
                        </h3>
                    <?php endif; ?>

                    <div id="letterText" class="letterText"></div>
                    <textarea id="letterRaw" style="display:none;"><?= e($letter['body']) ?></textarea>

                    <?php if (!$letter['body']): ?>
                        <p style="color:var(--muted)">Letter not set yet. Admin → Sections → letter → body update pannunga.
                        </p>
                    <?php endif; ?>

                    <div class="hint">Next Quiz try pannunga 😄</div>
                </div>

                <div class="scene-actions">
                    <?php if (!empty($scrapItems)): ?>
                        <button class="btn secondary js-go" data-go="scrapbook">⬅ Back</button>
                    <?php else: ?>
                        <button class="btn secondary js-go" data-go="soundtracks">⬅ Back</button>
                    <?php endif; ?>
                    <button class="btn js-go" data-go="quiz">Next ➡</button>
                </div>
            </div>

            <!-- QUIZ (MULTI) -->
            <div class="scene" data-scene="quiz" id="quiz">
                <div class="section-title">
                    <div>
                        <h3>🧠 Love Quiz</h3>
                        <p>Correct na next question 😄</p>
                    </div>
                    <div class="mini" id="quizCounter"></div>
                </div>

                <div class="card quiz">
                    <div class="q" id="quizQ">(Quiz not set)</div>

                    <div class="opts" id="quizOpts"></div>

                    <div id="quizResult" class="result"></div>

                    <div class="quiz-progress">
                        <span id="quizHint"></span>
                        <span class="mini">Tip: correct click panna next varum</span>
                    </div>
                </div>

                <div class="scene-actions">
                    <button class="btn secondary js-go" data-go="letter">⬅ Back</button>
                    <button class="btn js-go" data-go="certificate">Skip ➡</button>
                </div>
            </div>

            <!-- CERTIFICATE -->
            <div class="scene" data-scene="certificate" id="certificate">
                <div class="section-title">
                    <div>
                        <h3>🏆 Certificate</h3>
                        <p>Officially… forever.</p>
                    </div>
                </div>

                <div class="card cert">
                    <div class="inner">
                        <h3>
                            <?= e($certificate['title'] ?: 'Certificate of My Heart') ?>
                        </h3>
                        <h4>
                            <?= e($certificate['subtitle'] ?: 'Awarded to') ?>
                        </h4>

                        <div class="big">
                            <?= e($certificate['body'] ?: 'The Owner of My World') ?>
                        </div>

                        <?php if (!empty($certificate['image'])): ?>
                            <div
                                style="margin-top:14px;border-radius:18px;overflow:hidden;border:1px solid rgba(255,255,255,.12)">
                                <img src="<?= $BASE ?>/uploads/<?= e($certificate['image']) ?>" alt="certificate image"
                                    style="width:100%;display:block">
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:14px;color:var(--muted);font-size:13px">Signed with love 💗</div>
                    </div>
                </div>

                <div class="scene-actions">
                    <button class="btn secondary js-go" data-go="quiz">⬅ Back</button>
                    <button class="btn js-go" data-go="hero">🏠 Home</button>

                    <?php if (!empty($scrapItems)): ?>
                        <button class="btn secondary js-go" data-go="scrapbook">📸 Scrapbook</button>
                    <?php else: ?>
                        <?php $scrapUrl = trim((string) ($scrapbook['body'] ?? "")); ?>
                        <?php if ($scrapUrl !== ""): ?>
                            <a class="btn secondary" target="_blank" rel="noopener" href="<?= e($scrapUrl) ?>">📸 Scrapbook</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <footer>©
                    <?= date('Y') ?> • Made with ✰༆🅺𝐞𝐧𝐳𝐨🅱︎𝐨𝐦𝐦𝐢𝐲•
                </footer>
            </div>

        </div>
    </div>

    <script>
        /* ---------- ONLY BUTTON CLICK = NEXT SCREEN ---------- */
        const scenes = Array.from(document.querySelectorAll('.scene'));

        function showById(id) {
            scenes.forEach(s => s.classList.remove('active'));
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
            onSceneChange(id);
        }

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-go');
            if (!btn) return;
            const go = btn.getAttribute('data-go');
            if (go) showById(go);
        });

        /* ---------- TYPEWRITER (LETTER) ---------- */
        let letterPlayed = false;

        function typeLetter() {
            const rawEl = document.getElementById('letterRaw');
            const box = document.getElementById('letterText');
            if (!rawEl || !box) return;

            const raw = (rawEl.value || '').trim();
            if (raw === '') return;

            if (letterPlayed) return;
            letterPlayed = true;

            const paras = raw.split(/\n\s*\n/).map(p => p.trim()).filter(Boolean);

            box.innerHTML = '';
            const cursor = document.createElement('span');
            cursor.className = 'cursor';

            let pIndex = 0, i = 0;
            let currentP = document.createElement('p');
            box.appendChild(currentP);
            currentP.appendChild(cursor);

            function tick() {
                const text = paras[pIndex];
                if (i >= text.length) {
                    pIndex++;
                    i = 0;

                    if (pIndex >= paras.length) {
                        cursor.remove();
                        return;
                    }

                    currentP = document.createElement('p');
                    box.appendChild(currentP);
                    currentP.appendChild(cursor);

                    setTimeout(tick, 350);
                    return;
                }

                const ch = text[i];
                cursor.insertAdjacentText('beforebegin', ch);
                i++;

                let speed = 28;
                if (ch === '.' || ch === '!' || ch === '?') speed = 220;
                else if (ch === ',') speed = 120;

                setTimeout(tick, speed);
            }

            tick();
        }

        /* ---------- MULTI QUIZ JS ---------- */
        const QUIZ = <?= json_encode($quizPack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        let quizIndex = 0;     // current question index
        let quizLocked = false;

        const quizQ = document.getElementById('quizQ');
        const quizOpts = document.getElementById('quizOpts');
        const quizResult = document.getElementById('quizResult');
        const quizCounter = document.getElementById('quizCounter');
        const quizHint = document.getElementById('quizHint');

        function renderQuizQuestion(idx) {
            if (!quizQ || !quizOpts) return;

            quizLocked = false;
            quizResult.style.display = 'none';
            quizResult.classList.remove('ok', 'bad');
            quizResult.textContent = '';

            if (!QUIZ || QUIZ.length === 0) {
                quizQ.textContent = "(Quiz questions not set yet. Admin → Quiz la add pannunga.)";
                quizOpts.innerHTML = '';
                if (quizCounter) quizCounter.textContent = '';
                if (quizHint) quizHint.textContent = '';
                return;
            }

            if (idx < 0) idx = 0;
            if (idx >= QUIZ.length) idx = QUIZ.length - 1;
            quizIndex = idx;

            const q = QUIZ[quizIndex];
            quizQ.textContent = q.question;

            if (quizCounter) quizCounter.textContent = (quizIndex + 1) + " / " + QUIZ.length;
            if (quizHint) quizHint.textContent = "Question " + (quizIndex + 1);

            quizOpts.innerHTML = '';
            (q.options || []).forEach(opt => {
                const b = document.createElement('button');
                b.className = 'opt';
                b.type = 'button';
                b.textContent = opt.text;
                b.dataset.correct = String(opt.is_correct || 0);

                b.addEventListener('click', () => {
                    if (quizLocked) return;

                    const ok = b.dataset.correct === '1';
                    quizResult.style.display = 'block';
                    quizResult.classList.remove('ok', 'bad');

                    if (ok) {
                        quizLocked = true;
                        quizResult.classList.add('ok');
                        quizResult.textContent = "Correct! 😍 Next question loading...";

                        setTimeout(() => {
                            if (quizIndex + 1 < QUIZ.length) {
                                renderQuizQuestion(quizIndex + 1);
                            } else {
                                // last question done -> go certificate
                                quizResult.textContent = "All correct 😘 Now your surprise... 💖";
                                setTimeout(() => showById('certificate'), 800);
                            }
                        }, 700);
                    } else {
                        quizResult.classList.add('bad');
                        quizResult.textContent = "Aiyo 😄 try again da…";
                    }
                });

                quizOpts.appendChild(b);
            });
        }

        function onSceneChange(activeId) {
            if (activeId === 'letter') typeLetter();

            if (activeId === 'quiz') {
                // whenever quiz scene opened -> start from first question
                renderQuizQuestion(0);
            }
        }

        /* start always hero */
        showById('hero');

        /* ---------- hearts effect ---------- */
        const canvas = document.getElementById('fx');
        const ctx = canvas.getContext('2d');
        let W, H, hearts = [];

        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize);
        resize();

        function rand(min, max) { return Math.random() * (max - min) + min; }
        function makeHeart() {
            return {
                x: rand(0, W), y: H + rand(20, 120), s: rand(10, 22),
                vy: rand(0.6, 1.6), vx: rand(-0.3, 0.3),
                a: rand(0.25, 0.7), rot: rand(-0.6, 0.6), vr: rand(-0.01, 0.01)
            };
        }
        function drawHeart(x, y, size, rot, alpha) {
            ctx.save(); ctx.translate(x, y); ctx.rotate(rot); ctx.globalAlpha = alpha;
            ctx.beginPath();
            const s = size;
            ctx.moveTo(0, s * 0.35);
            ctx.bezierCurveTo(0, 0, -s * 0.5, 0, -s * 0.5, s * 0.35);
            ctx.bezierCurveTo(-s * 0.5, s * 0.7, 0, s * 0.9, 0, s * 1.15);
            ctx.bezierCurveTo(0, s * 0.9, s * 0.5, s * 0.7, s * 0.5, s * 0.35);
            ctx.bezierCurveTo(s * 0.5, 0, 0, 0, 0, s * 0.35);
            ctx.closePath();
            ctx.fillStyle = 'rgba(255,79,216,0.9)';
            ctx.fill();
            ctx.restore();
        }
        function tick() {
            ctx.clearRect(0, 0, W, H);
            if (hearts.length < 55 && Math.random() < 0.35) hearts.push(makeHeart());
            hearts.forEach(h => { h.y -= h.vy; h.x += h.vx; h.rot += h.vr; drawHeart(h.x, h.y, h.s, h.rot, h.a); });
            hearts = hearts.filter(h => h.y > -120);
            requestAnimationFrame(tick);
        }
        tick();
    </script>
</body>

</html>