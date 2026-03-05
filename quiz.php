<?php
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/db.php';
require_admin();

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$msg = "";

/* ---------- ensure sort_order column exists (safe try) ---------- */
try {
    $pdo->exec("ALTER TABLE quiz_questions ADD sort_order INT DEFAULT 0");
} catch (Throwable $e) {
}

/* ---------- helpers ---------- */
function ensure_question_has_4_options(PDO $pdo, int $qid): void
{
    $st = $pdo->prepare("SELECT id FROM quiz_options WHERE question_id=? ORDER BY id ASC");
    $st->execute([$qid]);
    $opts = $st->fetchAll(PDO::FETCH_COLUMN);

    $need = 4 - count($opts);
    if ($need <= 0)
        return;

    $defaults = ['Option 1', 'Option 2', 'Option 3', 'Option 4'];
    for ($i = 0; $i < $need; $i++) {
        $text = $defaults[count($opts) + $i] ?? ('Option ' . (count($opts) + $i + 1));
        $is_correct = (count($opts) + $i === 0) ? 1 : 0; // first one correct by default
        $pdo->prepare("INSERT INTO quiz_options(question_id, option_text, is_correct) VALUES(?,?,?)")
            ->execute([$qid, $text, $is_correct]);
    }
}

function create_new_question(PDO $pdo, string $qText): int
{
    $max = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM quiz_questions")->fetchColumn();
    $sort = $max + 1;

    $pdo->prepare("INSERT INTO quiz_questions(question, sort_order) VALUES(?,?)")
        ->execute([$qText, $sort]);
    $qid = (int) $pdo->lastInsertId();

    // create 4 default options
    $pdo->prepare("INSERT INTO quiz_options(question_id, option_text, is_correct) VALUES(?,?,?)")
        ->execute([$qid, 'Me ❤️', 1]);
    $pdo->prepare("INSERT INTO quiz_options(question_id, option_text, is_correct) VALUES(?,?,?)")
        ->execute([$qid, 'Someone else', 0]);
    $pdo->prepare("INSERT INTO quiz_options(question_id, option_text, is_correct) VALUES(?,?,?)")
        ->execute([$qid, 'Your friend', 0]);
    $pdo->prepare("INSERT INTO quiz_options(question_id, option_text, is_correct) VALUES(?,?,?)")
        ->execute([$qid, 'Your pet 😄', 0]);

    return $qid;
}

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add new question
    if ($action === 'add') {
        $newQ = trim($_POST['new_question'] ?? '');
        if ($newQ === '')
            $newQ = "New question...";
        $newId = create_new_question($pdo, $newQ);
        header("Location: quiz.php?qid=" . $newId . "&msg=Added");
        exit;
    }

    // Save order (sort)
    if ($action === 'save_order') {
        $orders = $_POST['order'] ?? [];
        if (is_array($orders)) {
            foreach ($orders as $qid => $ord) {
                $qid = (int) $qid;
                $ord = (int) $ord;
                $pdo->prepare("UPDATE quiz_questions SET sort_order=? WHERE id=?")->execute([$ord, $qid]);
            }
        }
        header("Location: quiz.php?msg=Order%20saved");
        exit;
    }

    // Delete question
    if ($action === 'delete') {
        $qid = (int) ($_POST['qid'] ?? 0);
        if ($qid > 0) {
            $pdo->prepare("DELETE FROM quiz_options WHERE question_id=?")->execute([$qid]);
            $pdo->prepare("DELETE FROM quiz_questions WHERE id=?")->execute([$qid]);
        }
        header("Location: quiz.php?msg=Deleted");
        exit;
    }

    // Save question + options
    if ($action === 'save') {
        $qid = (int) ($_POST['qid'] ?? 0);
        if ($qid <= 0) {
            header("Location: quiz.php?msg=Invalid");
            exit;
        }

        $question = trim($_POST['question'] ?? '');
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $correct_id = (int) ($_POST['correct_id'] ?? 0);

        $pdo->prepare("UPDATE quiz_questions SET question=?, sort_order=? WHERE id=?")
            ->execute([$question, $sort_order, $qid]);

        // ensure 4 options exist
        ensure_question_has_4_options($pdo, $qid);

        // reload options after ensuring
        $st = $pdo->prepare("SELECT * FROM quiz_options WHERE question_id=? ORDER BY id ASC");
        $st->execute([$qid]);
        $options = $st->fetchAll();

        foreach ($options as $op) {
            $id = (int) $op['id'];
            $text = $_POST['opt_' . $id] ?? $op['option_text'];
            $text = trim($text);
            if ($text === '')
                $text = $op['option_text'];

            $is_correct = ($id === $correct_id) ? 1 : 0;

            $pdo->prepare("UPDATE quiz_options SET option_text=?, is_correct=? WHERE id=? AND question_id=?")
                ->execute([$text, $is_correct, $id, $qid]);
        }

        header("Location: quiz.php?qid=" . $qid . "&msg=Saved");
        exit;
    }
}

/* ---------- fetch all questions ---------- */
$questions = $pdo->query("SELECT * FROM quiz_questions ORDER BY sort_order ASC, id ASC")->fetchAll();

/* if none, create default */
if (!$questions) {
    $firstId = create_new_question($pdo, "Who loves you the most?");
    $questions = $pdo->query("SELECT * FROM quiz_questions ORDER BY sort_order ASC, id ASC")->fetchAll();
}

/* selected question */
$qid = (int) ($_GET['qid'] ?? 0);
if ($qid <= 0)
    $qid = (int) $questions[0]['id'];

/* load selected */
$stQ = $pdo->prepare("SELECT * FROM quiz_questions WHERE id=? LIMIT 1");
$stQ->execute([$qid]);
$q = $stQ->fetch();

if (!$q) {
    // fallback first
    $qid = (int) $questions[0]['id'];
    $stQ->execute([$qid]);
    $q = $stQ->fetch();
}

ensure_question_has_4_options($pdo, (int) $q['id']);

$stO = $pdo->prepare("SELECT * FROM quiz_options WHERE question_id=? ORDER BY id ASC");
$stO->execute([$q['id']]);
$options = $stO->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Quiz</title>
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
            text-decoration: none;
            cursor: pointer
        }

        .ok {
            color: #b6ffc2;
            margin-top: 10px
        }

        .layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 14px;
            margin-top: 16px
        }

        @media(max-width:900px) {
            .layout {
                grid-template-columns: 1fr
            }
        }

        .card {
            padding: 16px;
            border-radius: 18px;
            background: #121226;
            border: 1px solid rgba(255, 255, 255, .12)
        }

        .muted {
            color: #b9b9c8;
            font-size: 12px
        }

        label {
            display: block;
            font-size: 12px;
            color: #b9b9c8;
            margin-top: 10px
        }

        input {
            width: 100%;
            padding: 10px 12px;
            margin-top: 6px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .06);
            color: #fff
        }

        .row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px
        }

        .row input[type="radio"] {
            width: auto;
            margin: 0
        }

        .qitem {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px 10px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .10);
            background: rgba(255, 255, 255, .04);
            margin-top: 10px
        }

        .qitem.active {
            border-color: rgba(255, 79, 216, .35);
            box-shadow: 0 0 0 4px rgba(255, 79, 216, .10);
        }

        .qtitle {
            flex: 1;
            min-width: 0
        }

        .qtitle a {
            color: #fff;
            text-decoration: none;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tiny {
            width: 70px
        }

        .danger {
            border-color: rgba(239, 68, 68, .35)
        }

        form {
            margin: 0
        }

        .split {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 12px
        }

        .split>* {
            flex: 1
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <h2 style="margin:0">Quiz Manager (Multiple)</h2>
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

        <div class="layout">

            <!-- LEFT: question list + add + order -->
            <div class="card">
                <h3 style="margin:0 0 6px">All Questions</h3>
                <div class="muted">Click to edit • Sort order small = first</div>

                <form method="post" style="margin-top:12px">
                    <input type="hidden" name="action" value="add">
                    <label>Add new question</label>
                    <div class="row">
                        <input name="new_question" placeholder="Type your new question..." />
                        <button class="btn" type="submit" style="white-space:nowrap">+ Add</button>
                    </div>
                </form>

                <form method="post" style="margin-top:14px">
                    <input type="hidden" name="action" value="save_order">

                    <?php foreach ($questions as $qq): ?>
                        <?php $active = ((int) $qq['id'] === (int) $qid); ?>
                        <div class="qitem <?= $active ? 'active' : '' ?>">
                            <div class="qtitle">
                                <a href="quiz.php?qid=<?= (int) $qq['id'] ?>">
                                    <?= e($qq['question']) ?>
                                </a>
                                <div class="muted">ID:
                                    <?= (int) $qq['id'] ?>
                                </div>
                            </div>
                            <div class="tiny">
                                <input type="number" name="order[<?= (int) $qq['id'] ?>]"
                                    value="<?= (int) $qq['sort_order'] ?>" />
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top:12px">
                        <button class="btn" type="submit">Save Order</button>
                    </div>
                </form>
            </div>

            <!-- RIGHT: edit selected question -->
            <div class="card">
                <h3 style="margin:0 0 6px">Edit Question</h3>
                <div class="muted">Set question + 4 options + choose correct</div>

                <form method="post" style="margin-top:10px">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">

                    <div class="split">
                        <div>
                            <label>Question</label>
                            <input name="question" value="<?= e($q['question']) ?>" />
                        </div>
                        <div style="max-width:180px">
                            <label>Sort order</label>
                            <input name="sort_order" type="number" value="<?= (int) $q['sort_order'] ?>" />
                        </div>
                    </div>

                    <label>Options (select correct one)</label>

                    <?php foreach ($options as $op): ?>
                        <div class="row">
                            <input type="radio" name="correct_id" value="<?= (int) $op['id'] ?>"
                                <?= ((int) $op['is_correct'] === 1 ? 'checked' : '') ?> />
                            <input name="opt_<?= (int) $op['id'] ?>" value="<?= e($op['option_text']) ?>" />
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap">
                        <button class="btn" type="submit">Save</button>
                    </div>
                </form>

                <form method="post" onsubmit="return confirm('Delete this question?')" style="margin-top:10px">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">
                    <button class="btn danger" type="submit">Delete Question</button>
                </form>

                <div class="muted" style="margin-top:12px">
                   
                </div>
            </div>

        </div>
    </div>
</body>

</html>