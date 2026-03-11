<?php
require '../config/db.php';
session_start();

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$profileId) {
    header('Location: /projet/index.php');
    exit;
}

$user = $pdo->prepare("SELECT id, username, reputation FROM users WHERE id = ?");
$user->execute([$profileId]);
$user = $user->fetch();

if (!$user) {
    header('Location: /projet/index.php');
    exit;
}

// Filtrage par type
$filter = $_GET['filter'] ?? 'all';
$filterSql = '';
if ($filter === 'positive') $filterSql = "AND r.rating = 'positive'";
elseif ($filter === 'neutral') $filterSql = "AND r.rating = 'neutral'";
elseif ($filter === 'negative') $filterSql = "AND r.rating = 'negative'";

// Stats
$repStats = $pdo->prepare("
    SELECT
        SUM(rating = 'positive') AS positives,
        SUM(rating = 'neutral')  AS neutrals,
        SUM(rating = 'negative') AS negatives
    FROM reputation WHERE to_user_id = ?
");
$repStats->execute([$profileId]);
$repStats = $repStats->fetch();

// Pagination
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reputation r WHERE r.to_user_id = ? $filterSql");
$countStmt->execute([$profileId]);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Avis
$reps = $pdo->prepare("
    SELECT r.*, u.username AS from_username, t.title AS trade_title
    FROM reputation r
    JOIN users u ON r.from_user_id = u.id
    LEFT JOIN trades t ON r.trade_id = t.id
    WHERE r.to_user_id = ? $filterSql
    ORDER BY r.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$reps->execute([$profileId]);
$reps = $reps->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réputation de <?= htmlspecialchars($user['username']) ?> — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container my-5">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projet/user/profile.php?id=<?= $profileId ?>"><?= htmlspecialchars($user['username']) ?></a></li>
            <li class="breadcrumb-item active">Réputation</li>
        </ol>
    </nav>

    <div class="row g-4">

        <!-- Stats -->
        <div class="col-lg-3">
            <div class="card mb-3">
                <div class="card-header fw-bold">Score de réputation</div>
                <div class="card-body text-center">
                    <?php
                    $rep = $user['reputation'];
                    $color = $rep > 0 ? 'text-success' : ($rep < 0 ? 'text-danger' : 'text-muted');
                    ?>
                    <div class="display-4 fw-bold <?= $color ?>"><?= $rep > 0 ? '+' : '' ?><?= $rep ?></div>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-success"><i class="bi bi-hand-thumbs-up me-1"></i>Positif</span>
                        <span class="fw-bold text-success"><?= $repStats['positives'] ?? 0 ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-dash-circle me-1"></i>Neutre</span>
                        <span class="fw-bold"><?= $repStats['neutrals'] ?? 0 ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-danger"><i class="bi bi-hand-thumbs-down me-1"></i>Négatif</span>
                        <span class="fw-bold text-danger"><?= $repStats['negatives'] ?? 0 ?></span>
                    </li>
                </ul>
            </div>

            <!-- Filtres -->
            <div class="list-group">
                <a href="?id=<?= $profileId ?>&filter=all" class="list-group-item list-group-item-action <?= $filter === 'all' ? 'active' : '' ?>">
                    Tous les avis
                </a>
                <a href="?id=<?= $profileId ?>&filter=positive" class="list-group-item list-group-item-action <?= $filter === 'positive' ? 'active' : '' ?>">
                    <i class="bi bi-hand-thumbs-up text-success me-1"></i>Positifs
                </a>
                <a href="?id=<?= $profileId ?>&filter=neutral" class="list-group-item list-group-item-action <?= $filter === 'neutral' ? 'active' : '' ?>">
                    <i class="bi bi-dash-circle text-muted me-1"></i>Neutres
                </a>
                <a href="?id=<?= $profileId ?>&filter=negative" class="list-group-item list-group-item-action <?= $filter === 'negative' ? 'active' : '' ?>">
                    <i class="bi bi-hand-thumbs-down text-danger me-1"></i>Négatifs
                </a>
            </div>
        </div>

        <!-- Liste des avis -->
        <div class="col-lg-9">
            <h5 class="fw-bold mb-3">
                <i class="bi bi-star me-2"></i>Avis reçus
                <small class="text-muted">(<?= $total ?> au total)</small>
            </h5>

            <?php if (empty($reps)): ?>
                <p class="text-muted">Aucun avis trouvé.</p>
            <?php else: ?>
                <?php foreach ($reps as $rep): ?>
                    <?php
                    $repIcon = match($rep['rating']) {
                        'positive' => ['bi-hand-thumbs-up-fill text-success', 'Positif'],
                        'negative' => ['bi-hand-thumbs-down-fill text-danger', 'Négatif'],
                        default    => ['bi-dash-circle-fill text-muted', 'Neutre']
                    };
                    ?>
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi <?= $repIcon[0] ?> me-1"></i>
                                    <strong><?= $repIcon[1] ?></strong>
                                    <span class="text-muted ms-2">par
                                        <a href="/projet/user/profile.php?id=<?= $rep['from_user_id'] ?>">
                                            <?= htmlspecialchars($rep['from_username']) ?>
                                        </a>
                                    </span>
                                    <?php if ($rep['trade_title']): ?>
                                        <span class="text-muted ms-2">—
                                            <a href="/projet/trade/trade.php?id=<?= $rep['trade_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($rep['trade_title']) ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($rep['created_at'])) ?></small>
                            </div>
                            <?php if ($rep['comment']): ?>
                                <p class="mb-0 mt-1"><?= htmlspecialchars($rep['comment']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?id=<?= $profileId ?>&filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
