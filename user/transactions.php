<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']) {
    header('Location: /finalmaison-main/auth/login.php');
    exit;
}

$userId  = $_SESSION['user']['id'];
$filter  = $_GET['filter'] ?? 'all';
$sort    = $_GET['sort'] ?? 'date';
$dir     = $_GET['dir'] ?? 'desc';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$allowedSorts = ['date' => 't.created_at', 'amount' => 't.amount'];
$orderCol = $allowedSorts[$sort] ?? 't.created_at';
$orderDir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

$where = "(t.from_user_id = ? OR t.to_user_id = ?)";
$params = [$userId, $userId];

if ($filter === 'in') {
    $where = "t.to_user_id = ? AND (t.from_user_id IS NULL OR t.from_user_id != ?)";
    $params = [$userId, $userId];
} elseif ($filter === 'out') {
    $where = "t.from_user_id = ?";
    $params = [$userId];
}

$cnt = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE $where");
$cnt->execute($params);
$total = $cnt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT t.*,
           uf.username AS from_username,
           ut.username AS to_username
    FROM transactions t
    LEFT JOIN users uf ON t.from_user_id = uf.id
    LEFT JOIN users ut ON t.to_user_id = ut.id
    WHERE $where
    ORDER BY $orderCol $orderDir
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

function sortUrl($col, $currentSort, $currentDir, $filter) {
    $newDir = ($currentSort === $col && $currentDir === 'desc') ? 'asc' : 'desc';
    return "?filter=$filter&sort=$col&dir=$newDir";
}
function sortIcon($col, $currentSort, $currentDir) {
    if ($currentSort !== $col) return '<i class="bi bi-arrow-down-up text-muted ms-1"></i>';
    return $currentDir === 'asc'
        ? '<i class="bi bi-arrow-up ms-1"></i>'
        : '<i class="bi bi-arrow-down ms-1"></i>';
}

$inStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE to_user_id = ? AND (from_user_id IS NULL OR from_user_id != ?)");
$inStmt->execute([$userId, $userId]);
$totalIn = $inStmt->fetchColumn();

$outStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE from_user_id = ?");
$outStmt->execute([$userId]);
$totalOut = $outStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions — HYPERBUSINESS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <h2 class="fw-bold mb-4"><i class="bi bi-clock-history me-2"></i>Historique des transactions</h2>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center p-3">
                <small class="text-muted">Solde actuel</small>
                <div class="fs-4 fw-bold text-warning"><?= number_format($_SESSION['user']['forum_gold']) ?> J</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3">
                <small class="text-muted">Total reçu</small>
                <div class="fs-4 fw-bold text-success">+<?= number_format($totalIn) ?> J</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3">
                <small class="text-muted">Total dépensé</small>
                <div class="fs-4 fw-bold text-danger">-<?= number_format($totalOut) ?> J</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <?php
        $filters = ['all' => 'Tout', 'in' => 'Reçu', 'out' => 'Dépensé'];
        foreach ($filters as $key => $label): ?>
            <a href="?filter=<?= $key ?>" class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
        <span class="text-muted ms-auto align-self-center"><?= $total ?> transaction<?= $total > 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="alert alert-info">Aucune transaction.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><a href="<?= sortUrl('date', $sort, $dir, $filter) ?>" class="text-decoration-none" style="color:inherit;cursor:pointer;">Date <?= sortIcon('date', $sort, $dir) ?></a></th>
                        <th>Type</th>
                        <th><a href="<?= sortUrl('amount', $sort, $dir, $filter) ?>" class="text-decoration-none" style="color:inherit;cursor:pointer;">Montant <?= sortIcon('amount', $sort, $dir) ?></a></th>
                        <th>De / Vers</th>
                        <th>Raison</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx):
                        $isIncoming = ($tx['to_user_id'] == $userId && ($tx['from_user_id'] === null || $tx['from_user_id'] != $userId));
                    ?>
                        <tr>
                            <td>
                                <small><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></small>
                            </td>
                            <td>
                                <?php if ($isIncoming): ?>
                                    <span class="badge bg-success"><i class="bi bi-arrow-down-circle me-1"></i>Reçu</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-arrow-up-circle me-1"></i>Dépensé</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold <?= $isIncoming ? 'text-success' : 'text-danger' ?>">
                                <?= $isIncoming ? '+' : '-' ?><?= number_format($tx['amount']) ?> J
                            </td>
                            <td>
                                <?php if ($isIncoming): ?>
                                    <?php if ($tx['from_user_id']): ?>
                                        <a href="/finalmaison-main/user/profile.php?id=<?= $tx['from_user_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($tx['from_username']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Système</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="/finalmaison-main/user/profile.php?id=<?= $tx['to_user_id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($tx['to_username']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($tx['reason']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&dir=<?= $dir ?>&page=<?= $page - 1 ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 3);
                    $end   = min($totalPages, $page + 3);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&dir=<?= $dir ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&dir=<?= $dir ?>&page=<?= $page + 1 ?>">&raquo;</a>
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
