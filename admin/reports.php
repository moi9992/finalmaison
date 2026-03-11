<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']) {
    header('Location: /projet/auth/login.php');
    exit;
}

$userRole = $_SESSION['user']['role'] ?? 'user';
$userId   = $_SESSION['user']['id'];

if (!in_array($userRole, ['moderator', 'admin'])) {
    header('Location: /projet/index.php');
    exit;
}

// Résoudre un signalement
if (isset($_GET['resolve']) && $userId) {
    $reportId = (int)$_GET['resolve'];
    $pdo->prepare("UPDATE reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ? AND status = 'pending'")
        ->execute([$userId, $reportId]);
    header('Location: reports.php');
    exit;
}

// Ignorer un signalement
if (isset($_GET['dismiss']) && $userId) {
    $reportId = (int)$_GET['dismiss'];
    $pdo->prepare("UPDATE reports SET status = 'dismissed', resolved_by = ?, resolved_at = NOW() WHERE id = ? AND status = 'pending'")
        ->execute([$userId, $reportId]);
    header('Location: reports.php');
    exit;
}

// Filtre par statut
$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, ['pending', 'resolved', 'dismissed', 'all'])) {
    $statusFilter = 'pending';
}

$where = $statusFilter === 'all' ? '1=1' : "r.status = '$statusFilter'";

$reports = $pdo->query("
    SELECT r.*,
           reporter.username AS reporter_name,
           resolver.username AS resolver_name
    FROM reports r
    JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN users resolver ON r.resolved_by = resolver.id
    WHERE $where
    ORDER BY r.created_at DESC
    LIMIT 50
")->fetchAll();

// Pour chaque report, charger le contexte (post content, username, etc.)
foreach ($reports as &$report) {
    if ($report['type'] === 'post') {
        $ctx = $pdo->prepare("SELECT fp.content, fp.topic_id, u.username, ft.title AS topic_title FROM forum_posts fp JOIN users u ON fp.user_id = u.id JOIN forum_topics ft ON fp.topic_id = ft.id WHERE fp.id = ?");
        $ctx->execute([$report['target_id']]);
        $report['context'] = $ctx->fetch();
    } elseif ($report['type'] === 'user') {
        $ctx = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $ctx->execute([$report['target_id']]);
        $report['context'] = $ctx->fetch();
    } elseif ($report['type'] === 'trade') {
        $ctx = $pdo->prepare("SELECT t.title, u.username FROM trades t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
        $ctx->execute([$report['target_id']]);
        $report['context'] = $ctx->fetch();
    }
}
unset($report);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signalements — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="bi bi-flag me-2"></i>Signalements</h2>
        <a href="/projet/admin/index.php" class="btn btn-outline-secondary btn-sm">Retour admin</a>
    </div>

    <!-- Filtres -->
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">
                En attente
                <?php
                $pendingCount = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
                if ($pendingCount > 0): ?>
                    <span class="badge bg-danger"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'resolved' ? 'active' : '' ?>" href="?status=resolved">Résolus</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'dismissed' ? 'active' : '' ?>" href="?status=dismissed">Ignorés</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all">Tous</a>
        </li>
    </ul>

    <?php if (empty($reports)): ?>
        <div class="alert alert-info">Aucun signalement <?= $statusFilter === 'pending' ? 'en attente' : '' ?>.</div>
    <?php else: ?>
        <?php foreach ($reports as $report): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <!-- Type et badge -->
                            <div class="mb-2">
                                <?php
                                $typeBadge = match($report['type']) {
                                    'post'  => ['bg-info', 'Message'],
                                    'user'  => ['bg-warning text-dark', 'Utilisateur'],
                                    'trade' => ['bg-success', 'Annonce'],
                                };
                                $statusBadge = match($report['status']) {
                                    'pending'   => ['bg-danger', 'En attente'],
                                    'resolved'  => ['bg-success', 'Résolu'],
                                    'dismissed' => ['bg-secondary', 'Ignoré'],
                                };
                                ?>
                                <span class="badge <?= $typeBadge[0] ?> me-1"><?= $typeBadge[1] ?></span>
                                <span class="badge <?= $statusBadge[0] ?>"><?= $statusBadge[1] ?></span>
                            </div>

                            <!-- Raison -->
                            <p class="mb-1">
                                <strong>Raison :</strong> <?= htmlspecialchars($report['reason']) ?>
                            </p>

                            <!-- Contexte -->
                            <?php if ($report['type'] === 'post' && $report['context']): ?>
                                <div class="bg-light p-2 rounded mb-2">
                                    <small class="text-muted">Message de <strong><?= htmlspecialchars($report['context']['username']) ?></strong>
                                    dans <a href="/projet/forum/topic.php?id=<?= $report['context']['topic_id'] ?>"><?= htmlspecialchars($report['context']['topic_title']) ?></a> :</small>
                                    <p class="mb-0 small"><?= htmlspecialchars(mb_strimwidth($report['context']['content'], 0, 200, '...')) ?></p>
                                </div>
                            <?php elseif ($report['type'] === 'user' && $report['context']): ?>
                                <p class="mb-1">
                                    <strong>Utilisateur :</strong>
                                    <a href="/projet/user/profile.php?id=<?= $report['target_id'] ?>"><?= htmlspecialchars($report['context']['username']) ?></a>
                                </p>
                            <?php elseif ($report['type'] === 'trade' && $report['context']): ?>
                                <p class="mb-1">
                                    <strong>Annonce :</strong>
                                    <a href="/projet/trade/trade.php?id=<?= $report['target_id'] ?>"><?= htmlspecialchars($report['context']['title']) ?></a>
                                    par <?= htmlspecialchars($report['context']['username']) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Info signalement -->
                            <small class="text-muted">
                                Signalé par <a href="/projet/user/profile.php?id=<?= $report['reporter_id'] ?>"><?= htmlspecialchars($report['reporter_name']) ?></a>
                                le <?= date('d/m/Y à H:i', strtotime($report['created_at'])) ?>
                            </small>
                            <?php if ($report['resolver_name']): ?>
                                <br><small class="text-muted">
                                    Traité par <strong><?= htmlspecialchars($report['resolver_name']) ?></strong>
                                    le <?= date('d/m/Y à H:i', strtotime($report['resolved_at'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <?php if ($report['status'] === 'pending'): ?>
                            <div class="d-flex gap-2 ms-3">
                                <a href="?resolve=<?= $report['id'] ?>" class="btn btn-sm btn-success" title="Marquer comme résolu">
                                    <i class="bi bi-check-lg"></i>
                                </a>
                                <a href="?dismiss=<?= $report['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ignorer">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                                <?php if ($report['type'] === 'user' && $userRole === 'admin'): ?>
                                    <a href="/projet/admin/users.php?search=<?= urlencode($report['context']['username'] ?? '') ?>"
                                       class="btn btn-sm btn-outline-danger" title="Gérer le user">
                                        <i class="bi bi-person-x"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
