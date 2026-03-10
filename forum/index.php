<?php
require '../config/db.php';
session_start();

$categories = $pdo->query("
    SELECT fc.*,
        COUNT(DISTINCT ft.id) AS nb_topics,
        COUNT(DISTINCT fp.id) AS nb_posts
    FROM forum_categories fc
    LEFT JOIN forum_topics ft ON ft.category_id = fc.id
    LEFT JOIN forum_posts fp ON fp.topic_id = ft.id
    GROUP BY fc.id
    ORDER BY fc.sort_order
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <h2 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2"></i>Forum</h2>

    <div class="list-group">
        <?php foreach ($categories as $cat): ?>
            <a href="/projet/forum/category.php?id=<?= $cat['id'] ?>" class="list-group-item list-group-item-action py-3">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($cat['name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($cat['description'] ?? '') ?></small>
                    </div>
                    <div class="col-lg-2 text-center">
                        <span class="fw-bold"><?= $cat['nb_topics'] ?></span><br>
                        <small class="text-muted">topics</small>
                    </div>
                    <div class="col-lg-2 text-center">
                        <span class="fw-bold"><?= $cat['nb_posts'] ?></span><br>
                        <small class="text-muted">messages</small>
                    </div>
                    <div class="col-lg-1 text-end">
                        <i class="bi bi-chevron-right text-muted"></i>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
