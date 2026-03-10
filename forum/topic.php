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

// Incrémenter les vues (1 seule fois par session)
if (!isset($_SESSION['viewed_topics'])) {
    $_SESSION['viewed_topics'] = [];
}
if (!in_array($id, $_SESSION['viewed_topics'])) {
    $pdo->prepare("UPDATE forum_topics SET views = views + 1 WHERE id = ?")->execute([$id]);
    $_SESSION['viewed_topics'][] = $id;
}

$userId   = $_SESSION['user']['id'] ?? null;
$userRole = $_SESSION['user']['role'] ?? 'user';

function canDelete($currentRole, $isOwner, $targetRole) {
    if ($currentRole === 'admin') return true;
    if ($isOwner) return true;
    if ($currentRole === 'moderator' && $targetRole === 'user') return true;
    return false;
}

// Rôle de l'auteur du topic
$topicAuthorRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$topicAuthorRole->execute([$topic['user_id']]);
$topicAuthorRole = $topicAuthorRole->fetchColumn() ?: 'user';

// Suppression du topic
if (isset($_GET['delete_topic']) && $userId) {
    if (canDelete($userRole, $userId == $topic['user_id'], $topicAuthorRole)) {
        $pdo->prepare("DELETE FROM forum_topics WHERE id = ?")->execute([$id]);
        header('Location: /projet/forum/category.php?id=' . $topic['category_id']);
        exit;
    }
}

// Suppression d'un post
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

// Soumission d'une réponse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['isLog']) && $_SESSION['isLog'] && !$topic['is_locked']) {
    $content = trim($_POST['content'] ?? '');
    if (!empty($content)) {
        $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$id, $_SESSION['user']['id'], $content]);
        $pdo->prepare("UPDATE forum_topics SET updated_at = NOW() WHERE id = ?")->execute([$id]);

        // Notifier l'auteur du topic + tous les participants (sauf celui qui poste)
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

// Pagination
$perPage = 15;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

$total = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE topic_id = ?");
$total->execute([$id]);
$totalPosts = $total->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

$posts = $pdo->prepare("
    SELECT fp.*, u.username, u.reputation, u.role AS author_role, u.created_at AS user_since,
        COUNT(fp2.id) AS user_posts
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

    <!-- Fil d'ariane -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projet/forum/index.php">Forum</a></li>
            <li class="breadcrumb-item"><a href="/projet/forum/category.php?id=<?= $topic['category_id'] ?>"><?= htmlspecialchars($topic['category_name']) ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($topic['title']) ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">
            <?php if ($topic['is_pinned']): ?><span class="badge bg-warning text-dark me-2"><i class="bi bi-pin"></i></span><?php endif; ?>
            <?php if ($topic['is_locked']): ?><span class="badge bg-secondary me-2"><i class="bi bi-lock"></i></span><?php endif; ?>
            <?= htmlspecialchars($topic['title']) ?>
        </h2>
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted"><?= $topic['views'] ?> vues</small>
            <?php if ($userId && canDelete($userRole, $userId == $topic['user_id'], $topicAuthorRole)): ?>
                <a href="?id=<?= $id ?>&delete_topic=1"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Supprimer ce topic ? Tous les messages seront perdus.')">
                    <i class="bi bi-trash"></i> Supprimer le topic
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Posts -->
    <?php foreach ($posts as $post): ?>
        <div class="card mb-3">
            <div class="row g-0">
                <div class="col-md-2 bg-light border-end text-center p-3">
                    <div class="fw-bold"><?= htmlspecialchars($post['username']) ?></div>
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
                        <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?></small>
                            <?php if ($userId && canDelete($userRole, $userId == $post['user_id'], $post['author_role'])): ?>
                                <a href="?id=<?= $id ?>&delete_post=<?= $post['id'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Supprimer ce message ?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Pagination -->
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

    <!-- Formulaire de réponse -->
    <div id="bottom">
        <?php if (isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
            <?php if ($topic['is_locked']): ?>
                <div class="alert alert-secondary"><i class="bi bi-lock me-2"></i>Ce topic est verrouillé.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header fw-bold">Répondre</div>
                    <div class="card-body">
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
                <a href="/projet/auth/login.php">Connecte-toi</a> pour répondre à ce topic.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
