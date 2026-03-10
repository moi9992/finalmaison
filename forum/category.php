<?php
require '../config/db.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$category = $pdo->prepare("SELECT * FROM forum_categories WHERE id = ?");
$category->execute([$id]);
$category = $category->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

// Pagination
$perPage = 20;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

$total     = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE category_id = ?");
$total->execute([$id]);
$totalTopics = $total->fetchColumn();
$totalPages  = ceil($totalTopics / $perPage);

$topics = $pdo->prepare("
    SELECT ft.*, u.username,
        COUNT(fp.id) AS nb_replies,
        MAX(fp.created_at) AS last_reply_at
    FROM forum_topics ft
    JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_posts fp ON fp.topic_id = ft.id
    WHERE ft.category_id = ?
    GROUP BY ft.id
    ORDER BY ft.is_pinned DESC, ft.updated_at DESC
    LIMIT $perPage OFFSET $offset
");
$topics->execute([$id]);
$topics = $topics->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category['name']) ?> — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <!-- Fil d'ariane -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projet/forum/index.php">Forum</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($category['name']) ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><?= htmlspecialchars($category['name']) ?></h2>
        <?php if (isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
            <a href="/projet/forum/new_topic.php?category_id=<?= $id ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nouveau topic
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($topics)): ?>
        <p class="text-muted">Aucun topic dans cette catégorie pour le moment.</p>
    <?php else: ?>
        <div class="list-group mb-4">
            <?php foreach ($topics as $topic): ?>
                <a href="/projet/forum/topic.php?id=<?= $topic['id'] ?>" class="list-group-item list-group-item-action py-3">
                    <div class="row align-items-center">
                        <div class="col-lg-7">
                            <?php if ($topic['is_pinned']): ?>
                                <span class="badge bg-warning text-dark me-1"><i class="bi bi-pin"></i></span>
                            <?php endif; ?>
                            <?php if ($topic['is_locked']): ?>
                                <span class="badge bg-secondary me-1"><i class="bi bi-lock"></i></span>
                            <?php endif; ?>
                            <span class="fw-bold"><?= htmlspecialchars($topic['title']) ?></span>
                            <br>
                            <small class="text-muted">par <?= htmlspecialchars($topic['username']) ?> — <?= date('d/m/Y', strtotime($topic['created_at'])) ?></small>
                        </div>
                        <div class="col-lg-2 text-center">
                            <span class="fw-bold"><?= $topic['nb_replies'] ?></span><br>
                            <small class="text-muted">réponses</small>
                        </div>
                        <div class="col-lg-2 text-center">
                            <span class="fw-bold"><?= $topic['views'] ?></span><br>
                            <small class="text-muted">vues</small>
                        </div>
                        <div class="col-lg-1 text-end">
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?id=<?= $id ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
