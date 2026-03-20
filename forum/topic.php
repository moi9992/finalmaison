<?php
require '../config/db.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$topic = $pdo->prepare("
    SELECT ft.*, u.username, fc.name AS category_name, fc.id AS category_id
    FROM forum_topics ft
    JOIN users u ON ft.user_id = u.id
    JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.id = ?
");
$topic->execute([$id]);
$topic = $topic->fetch();

if (!$topic) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['viewed_topics'])) {
    $_SESSION['viewed_topics'] = [];
}
if (!in_array($id, $_SESSION['viewed_topics'])) {
    $pdo->prepare("UPDATE forum_topics SET views = views + 1 WHERE id = ?")->execute([$id]);
    $_SESSION['viewed_topics'][] = $id;
}

$userId   = $_SESSION['user']['id'] ?? null;
$userRole = $_SESSION['user']['role'] ?? 'user';

function getRank($posts) {
    $ranks = [
        ['name' => 'No Life',  'min' => 10000, 'color' => '#ff003c', 'secret' => true],
        ['name' => 'Faker',    'min' => 1000,  'color' => '#f5c800'],
        ['name' => 'Pro',      'min' => 500,   'color' => '#00e87a'],
        ['name' => 'Expert',   'min' => 300,   'color' => '#4ac8ff'],
        ['name' => 'Vétéran',  'min' => 150,   'color' => '#9b59b6'],
        ['name' => 'Confirmé', 'min' => 50,    'color' => '#3498db'],
        ['name' => 'Apprenti', 'min' => 20,    'color' => '#2ecc71'],
        ['name' => 'Débutant', 'min' => 5,     'color' => '#95a5a6'],
        ['name' => 'Noob',     'min' => 0,     'color' => '#7f8c8d'],
    ];
    foreach ($ranks as $i => $rank) {
        if ($posts >= $rank['min']) {
            $next = $i > 0 ? $ranks[$i - 1] : null;
            $progress = $next
                ? min(100, round(($posts - $rank['min']) / ($next['min'] - $rank['min']) * 100))
                : 100;
            return ['current' => $rank, 'next' => $next, 'progress' => $progress];
        }
    }
}

function canDelete($currentRole, $isOwner, $targetRole) {
    if ($currentRole === 'admin') return true;
    if ($isOwner) return true;
    if ($currentRole === 'moderator' && $targetRole === 'user') return true;
    return false;
}

$topicAuthorRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$topicAuthorRole->execute([$topic['user_id']]);
$topicAuthorRole = $topicAuthorRole->fetchColumn() ?: 'user';

if (isset($_GET['delete_topic']) && $userId) {
    if (canDelete($userRole, $userId == $topic['user_id'], $topicAuthorRole)) {
        $pdo->prepare("DELETE FROM forum_topics WHERE id = ?")->execute([$id]);
        header('Location: /finalmaison-main/forum/category.php?id=' . $topic['category_id']);
        exit;
    }
}

if (isset($_GET['toggle_pin']) && $userId && in_array($userRole, ['moderator', 'admin'])) {
    $pdo->prepare("UPDATE forum_topics SET is_pinned = NOT is_pinned WHERE id = ?")->execute([$id]);
    header("Location: topic.php?id=$id");
    exit;
}

if (isset($_GET['toggle_lock']) && $userId && in_array($userRole, ['moderator', 'admin'])) {
    $pdo->prepare("UPDATE forum_topics SET is_locked = NOT is_locked WHERE id = ?")->execute([$id]);
    header("Location: topic.php?id=$id");
    exit;
}

if (isset($_GET['delete_post']) && $userId) {
    $postId = (int)$_GET['delete_post'];
    $postInfo = $pdo->prepare("SELECT fp.user_id, u.role FROM forum_posts fp JOIN users u ON fp.user_id = u.id WHERE fp.id = ?");
    $postInfo->execute([$postId]);
    $postInfo = $postInfo->fetch();
    if ($postInfo && canDelete($userRole, $userId == $postInfo['user_id'], $postInfo['role'])) {
        $pdo->prepare("DELETE FROM forum_posts WHERE id = ?")->execute([$postId]);
    }
    header("Location: topic.php?id=$id");
    exit;
}

if (isset($_GET['report_post']) && $userId && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_reason'])) {
    $reportPostId = (int)$_GET['report_post'];
    $reason = trim($_POST['report_reason']);
    if (!empty($reason)) {
        $alreadyReported = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND type = 'post' AND target_id = ? AND status = 'pending'");
        $alreadyReported->execute([$userId, $reportPostId]);
        if ($alreadyReported->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO reports (reporter_id, type, target_id, reason) VALUES (?, 'post', ?, ?)")
                ->execute([$userId, $reportPostId, $reason]);

            $staff = $pdo->prepare("SELECT id FROM users WHERE role IN ('moderator', 'admin') AND id != ?");
            $staff->execute([$userId]);
            $postInfo2 = $pdo->prepare("SELECT u.username FROM forum_posts fp JOIN users u ON fp.user_id = u.id WHERE fp.id = ?");
            $postInfo2->execute([$reportPostId]);
            $postAuthor = $postInfo2->fetchColumn();
            $notifMsg = htmlspecialchars($_SESSION['user']['login']) . ' a signalé un message de ' . $postAuthor . ' dans "' . mb_strimwidth($topic['title'], 0, 40, '...') . '"';
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, reference_id, message) VALUES (?, ?, 'report', ?, ?)");
            foreach ($staff as $s) {
                $notifStmt->execute([$s['id'], $userId, $id, $notifMsg]);
            }
        }
    }
    header("Location: topic.php?id=$id&reported=1");
    exit;
}

if (isset($_GET['edit_post']) && $_SERVER['REQUEST_METHOD'] === 'POST' && $userId && isset($_POST['edit_content'])) {
    $editPostId = (int)$_GET['edit_post'];
    $editContent = trim($_POST['edit_content']);
    if (!empty($editContent)) {
        $postOwner = $pdo->prepare("SELECT user_id FROM forum_posts WHERE id = ? AND topic_id = ?");
        $postOwner->execute([$editPostId, $id]);
        $postOwner = $postOwner->fetchColumn();
        if ($postOwner == $userId) {
            $pdo->prepare("UPDATE forum_posts SET content = ?, updated_at = NOW() WHERE id = ?")->execute([$editContent, $editPostId]);
        }
    }
    header("Location: topic.php?id=$id");
    exit;
}

if (isset($_POST['edit_title']) && $userId == $topic['user_id']) {
    $newTitle = trim($_POST['edit_title']);
    if (!empty($newTitle)) {
        $pdo->prepare("UPDATE forum_topics SET title = ? WHERE id = ?")->execute([$newTitle, $id]);
        header("Location: topic.php?id=$id");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['isLog']) && $_SESSION['isLog'] && !$topic['is_locked'] && !isset($_POST['edit_content']) && !isset($_POST['edit_title'])) {
    $content = trim($_POST['content'] ?? '');
    if (!empty($content)) {
        $lastPost = $pdo->prepare("SELECT created_at FROM forum_posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $lastPost->execute([$_SESSION['user']['id']]);
        $lastPost = $lastPost->fetchColumn();
        if ($lastPost && (time() - strtotime($lastPost)) < 30) {
            $spamError = "Tu postes trop vite ! Attends " . (30 - (time() - strtotime($lastPost))) . " seconde(s).";
        } else {
        $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$id, $_SESSION['user']['id'], $content]);
        $pdo->prepare("UPDATE forum_topics SET updated_at = NOW() WHERE id = ?")->execute([$id]);

        $participants = $pdo->prepare("
            SELECT DISTINCT user_id FROM forum_posts WHERE topic_id = ? AND user_id != ?
            UNION
            SELECT ? AS user_id
        ");
        $participants->execute([$id, $_SESSION['user']['id'], $topic['user_id']]);
        $notifMsg = htmlspecialchars($_SESSION['user']['login']) . ' a répondu au topic "' . mb_strimwidth($topic['title'], 0, 50, '...') . '"';
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, reference_id, message) VALUES (?, ?, 'topic_reply', ?, ?)");
        foreach ($participants as $p) {
            if ($p['user_id'] != $_SESSION['user']['id']) {
                $notifStmt->execute([$p['user_id'], $_SESSION['user']['id'], $id, $notifMsg]);
            }
        }

        header("Location: topic.php?id=$id#bottom");
        exit;
        }
    }
}

$perPage = 15;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

$total = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE topic_id = ?");
$total->execute([$id]);
$totalPosts = $total->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

$posts = $pdo->prepare("
    SELECT fp.*, u.username, u.reputation, u.role AS author_role, u.created_at AS user_since,
        u.avatar, u.last_login, COUNT(fp2.id) AS user_posts
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.id
    LEFT JOIN forum_posts fp2 ON fp2.user_id = u.id
    WHERE fp.topic_id = ?
    GROUP BY fp.id
    ORDER BY fp.created_at ASC
    LIMIT $perPage OFFSET $offset
");
$posts->execute([$id]);
$posts = $posts->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($topic['title']) ?> — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container my-5">

    <?php if (isset($_GET['reported'])): ?>
        <div class="alert alert-success">Signalement envoyé aux modérateurs. Merci !</div>
    <?php endif; ?>

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/finalmaison-main/forum/index.php">Forum</a></li>
            <li class="breadcrumb-item"><a href="/finalmaison-main/forum/category.php?id=<?= $topic['category_id'] ?>"><?= htmlspecialchars($topic['category_name']) ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($topic['title']) ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="flex-grow-1 me-3">
            <?php if (isset($_GET['edit_title']) && $userId == $topic['user_id']): ?>
                <form method="POST" class="d-flex gap-2">
                    <input type="text" name="edit_title" class="form-control fw-bold fs-5" value="<?= htmlspecialchars($topic['title']) ?>" required>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i></button>
                    <a href="?id=<?= $id ?>" class="btn btn-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                </form>
            <?php else: ?>
                <h2 class="fw-bold mb-0">
                    <?php if ($topic['is_pinned']): ?><span class="badge bg-warning text-dark me-2"><i class="bi bi-pin"></i></span><?php endif; ?>
                    <?php if ($topic['is_locked']): ?><span class="badge bg-secondary me-2"><i class="bi bi-lock"></i></span><?php endif; ?>
                    <?= htmlspecialchars($topic['title']) ?>
                    <?php if ($userId == $topic['user_id']): ?>
                        <a href="?id=<?= $id ?>&edit_title=1" class="btn btn-sm btn-outline-secondary ms-2" title="Modifier le titre">
                            <i class="bi bi-pencil"></i>
                        </a>
                    <?php endif; ?>
                </h2>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted"><?= $topic['views'] ?> vues</small>
            <?php if ($userId && in_array($userRole, ['moderator', 'admin'])): ?>
                <a href="?id=<?= $id ?>&toggle_pin=1" class="btn btn-sm <?= $topic['is_pinned'] ? 'btn-warning' : 'btn-outline-warning' ?>" title="<?= $topic['is_pinned'] ? 'Désépingler' : 'Épingler' ?>">
                    <i class="bi bi-pin"></i>
                </a>
                <a href="?id=<?= $id ?>&toggle_lock=1" class="btn btn-sm <?= $topic['is_locked'] ? 'btn-secondary' : 'btn-outline-secondary' ?>" title="<?= $topic['is_locked'] ? 'Déverrouiller' : 'Verrouiller' ?>">
                    <i class="bi bi-lock"></i>
                </a>
            <?php endif; ?>
            <?php if ($userId && canDelete($userRole, $userId == $topic['user_id'], $topicAuthorRole)): ?>
                <a href="?id=<?= $id ?>&delete_topic=1"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Supprimer ce topic ? Tous les messages seront perdus.')">
                    <i class="bi bi-trash"></i> Supprimer le topic
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach ($posts as $post): ?>
        <div class="card mb-3">
            <div class="row g-0">
                <div class="col-md-2 bg-light border-end text-center p-3">
                    <?php if (!empty($post['avatar'])): ?>
                        <img src="<?= htmlspecialchars($post['avatar']) ?>" alt="avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;" class="mb-2">
                    <?php else: ?>
                        <i class="bi bi-person-circle fs-2 mb-2 d-block"></i>
                    <?php endif; ?>
                    <?php
                    $postRank = getRank($post['user_posts']);
                    $postOnline = $post['last_login'] && (time() - strtotime($post['last_login'])) < 900;
                    ?>
                    <a href="/finalmaison-main/user/profile.php?id=<?= $post['user_id'] ?>" class="fw-bold text-decoration-none">
                        <?= htmlspecialchars($post['username']) ?>
                        <span title="<?= $postOnline ? 'En ligne' : 'Hors ligne' ?>"
                              style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $postOnline ? '#00e87a' : '#4a5068' ?>;margin-left:4px;vertical-align:middle;"></span>
                    </a>
                    <?php if ($post['author_role'] === 'admin'): ?>
                        <span class="badge bg-danger d-block mx-auto mt-1" style="width:fit-content;">Admin</span>
                    <?php elseif ($post['author_role'] === 'moderator'): ?>
                        <span class="badge d-block mx-auto mt-1" style="width:fit-content;background:#f5c800 !important;color:#000 !important;">Modérateur</span>
                    <?php endif; ?>
                    <small style="color:<?= $postRank['current']['color'] ?>;" class="d-block mt-1"><?= $postRank['current']['name'] ?></small>
                    <small class="text-muted d-block"><?= $post['user_posts'] ?> messages</small>
                    <small class="text-muted d-block">
                        <?php
                            $rep = $post['reputation'];
                            $color = $rep > 0 ? 'text-success' : ($rep < 0 ? 'text-danger' : 'text-muted');
                        ?>
                        <span class="<?= $color ?>"><i class="bi bi-star-fill"></i> <?= $rep ?></span>
                    </small>
                </div>
                <div class="col-md-10">
                    <div class="card-body">
                        <?php if (isset($_GET['edit_post']) && (int)$_GET['edit_post'] === $post['id'] && $userId == $post['user_id']): ?>
                            <form method="POST" action="?id=<?= $id ?>&edit_post=<?= $post['id'] ?>">
                                <textarea name="edit_content" class="form-control mb-2" rows="4" required><?= htmlspecialchars($post['content']) ?></textarea>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Sauvegarder</button>
                                    <a href="?id=<?= $id ?>" class="btn btn-secondary btn-sm">Annuler</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?></small>
                                <?php if ($post['updated_at'] !== $post['created_at']): ?>
                                    <small class="text-muted fst-italic ms-2">(modifié le <?= date('d/m/Y à H:i', strtotime($post['updated_at'])) ?>)</small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1">
                                <?php if ($userId == $post['user_id'] && !isset($_GET['edit_post'])): ?>
                                    <a href="?id=<?= $id ?>&edit_post=<?= $post['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($userId && $userId != $post['user_id']): ?>
                                    <button class="btn btn-sm btn-outline-warning" title="Signaler"
                                            data-bs-toggle="modal" data-bs-target="#reportModal<?= $post['id'] ?>">
                                        <i class="bi bi-flag"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($userId && canDelete($userRole, $userId == $post['user_id'], $post['author_role'])): ?>
                                    <a href="?id=<?= $id ?>&delete_post=<?= $post['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Supprimer ce message ?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if ($userId && $userId != $post['user_id']): ?>
                            <div class="modal fade" id="reportModal<?= $post['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="?id=<?= $id ?>&report_post=<?= $post['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-flag me-2"></i>Signaler ce message</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted small">Message de <strong><?= htmlspecialchars($post['username']) ?></strong></p>
                                                <label class="form-label fw-bold">Raison du signalement</label>
                                                <select name="report_reason" class="form-select" required>
                                                    <option value="">-- Choisir --</option>
                                                    <option value="Spam ou publicité">Spam ou publicité</option>
                                                    <option value="Contenu offensant / harcèlement">Contenu offensant / harcèlement</option>
                                                    <option value="Arnaque / scam">Arnaque / scam</option>
                                                    <option value="Contenu inapproprié">Contenu inapproprié</option>
                                                    <option value="Autre">Autre</option>
                                                </select>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                <button type="submit" class="btn btn-warning"><i class="bi bi-flag me-1"></i>Signaler</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="mb-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?id=<?= $id ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <div id="bottom">
        <?php if (isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
            <?php if ($topic['is_locked']): ?>
                <div class="alert alert-secondary"><i class="bi bi-lock me-2"></i>Ce topic est verrouillé.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header fw-bold">Répondre</div>
                    <div class="card-body">
                        <?php if (!empty($spamError)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($spamError) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <textarea name="content" class="form-control" rows="5" placeholder="Votre réponse..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Envoyer</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <a href="/finalmaison-main/auth/login.php">Connecte-toi</a> pour répondre à ce topic.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
