<?php
require '../config/db.php';
session_start();

$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort'] ?? 'newest';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Tri
$orderBy = match($sort) {
    'reputation' => 'u.reputation DESC',
    'gold'       => 'u.forum_gold DESC',
    'posts'      => 'post_count DESC',
    'trades'     => 'trade_count DESC',
    'oldest'     => 'u.created_at ASC',
    default      => 'u.created_at DESC',
};

// Recherche
$where = '1=1';
$params = [];
if ($search !== '') {
    $where = 'u.username LIKE ?';
    $params[] = "%$search%";
}

// Total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Membres
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role, u.forum_gold, u.reputation, u.created_at, u.last_login, u.is_banned,
        (SELECT COUNT(*) FROM forum_posts fp WHERE fp.user_id = u.id) AS post_count,
        (SELECT COUNT(*) FROM trades t WHERE t.user_id = u.id) AS trade_count
    FROM users u
    WHERE $where
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres — HYPERBUSINESS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <h2 class="fw-bold mb-4"><i class="bi bi-people me-2"></i>Membres <small class="text-muted fs-6">(<?= $total ?>)</small></h2>

    <!-- Recherche + Tri -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <form method="GET" class="input-group">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="text" name="search" class="form-control" placeholder="Rechercher un membre..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <?php if ($search): ?>
                    <a href="?sort=<?= htmlspecialchars($sort) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-md-6">
            <div class="d-flex gap-1 flex-wrap">
                <?php
                $sorts = [
                    'newest'     => 'Récents',
                    'oldest'     => 'Anciens',
                    'reputation' => 'Réputation',
                    'gold'       => 'Julientons',
                    'posts'      => 'Messages',
                    'trades'     => 'Annonces',
                ];
                foreach ($sorts as $key => $label): ?>
                    <a href="?sort=<?= $key ?>&search=<?= urlencode($search) ?>"
                       class="btn btn-sm <?= $sort === $key ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Liste -->
    <?php if (empty($members)): ?>
        <div class="alert alert-info">Aucun membre trouvé.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th class="text-center">Rôle</th>
                        <th class="text-end">Réputation</th>
                        <th class="text-end">Julientons</th>
                        <th class="text-center">Messages</th>
                        <th class="text-center">Annonces</th>
                        <th>Inscrit le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr <?= $m['is_banned'] ? 'class="opacity-50"' : '' ?>>
                            <td>
                                <a href="/projet/user/profile.php?id=<?= $m['id'] ?>" class="text-decoration-none fw-bold">
                                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($m['username']) ?>
                                </a>
                                <?php if ($m['is_banned']): ?>
                                    <span class="badge bg-dark ms-1">Banni</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $roleBadge = match($m['role']) {
                                    'admin'     => ['bg-danger', 'Admin'],
                                    'moderator' => ['', 'Modo', 'background:var(--gold);color:#000;font-weight:700;'],
                                    default     => ['bg-secondary', 'User'],
                                };
                                ?>
                                <span class="badge <?= $roleBadge[0] ?>" <?= isset($roleBadge[2]) ? 'style="'.$roleBadge[2].'"' : '' ?>><?= $roleBadge[1] ?></span>
                            </td>
                            <td class="text-end">
                                <?php
                                $rep = $m['reputation'];
                                $color = $rep > 0 ? 'text-success' : ($rep < 0 ? 'text-danger' : 'text-muted');
                                ?>
                                <span class="<?= $color ?> fw-bold"><?= $rep > 0 ? '+' : '' ?><?= $rep ?></span>
                            </td>
                            <td class="text-end text-warning fw-bold"><?= number_format($m['forum_gold']) ?> J</td>
                            <td class="text-center"><?= $m['post_count'] ?></td>
                            <td class="text-center"><?= $m['trade_count'] ?></td>
                            <td>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($m['created_at'])) ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 3);
                    $end   = min($totalPages, $page + 3);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
