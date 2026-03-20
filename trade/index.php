<?php
require '../config/db.php';
session_start();

$game   = $_GET['game'] ?? '';
$type   = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$where  = ["t.status = 'open'"];
$params = [];

if ($game) {
    $where[]  = "t.game = ?";
    $params[] = $game;
}
if ($type === 'sell' || $type === 'buy') {
    $where[]  = "t.type = ?";
    $params[] = $type;
}
if ($search) {
    $where[]  = "(t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = implode(' AND ', $where);

$perPage     = 20;
$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset      = ($page - 1) * $perPage;
$totalTrades = $pdo->prepare("SELECT COUNT(*) FROM trades t WHERE $whereSQL");
$totalTrades->execute($params);
$totalTrades = $totalTrades->fetchColumn();
$totalPages  = ceil($totalTrades / $perPage);

$trades = $pdo->prepare("
    SELECT t.*, u.username
    FROM trades t
    JOIN users u ON t.user_id = u.id
    WHERE $whereSQL
    ORDER BY t.bumped_at DESC
    LIMIT $perPage OFFSET $offset
");
$trades->execute($params);
$trades = $trades->fetchAll();

$games = $pdo->query("SELECT DISTINCT game FROM trades ORDER BY game")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="bi bi-tags me-2"></i>Annonces</h2>
        <?php if (isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
            <a href="/finalmaison-main/trade/new_trade.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nouvelle annonce
            </a>
        <?php endif; ?>
    </div>

    <form method="GET" action="" class="card p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Recherche</label>
                <input type="text" name="search" class="form-control" placeholder="Titre ou description..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Jeu</label>
                <select name="game" class="form-select">
                    <option value="">Tous les jeux</option>
                    <?php foreach ($games as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>" <?= $game === $g ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Type</label>
                <select name="type" class="form-select">
                    <option value="">Vente & Achat</option>
                    <option value="sell" <?= $type === 'sell' ? 'selected' : '' ?>>Vente</option>
                    <option value="buy"  <?= $type === 'buy'  ? 'selected' : '' ?>>Achat</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </div>
    </form>

    <?php if (empty($trades)): ?>
        <p class="text-muted">Aucune annonce trouvée.</p>
    <?php else: ?>
        <div class="list-group mb-4">
            <?php foreach ($trades as $trade): ?>
                <a href="/finalmaison-main/trade/trade.php?id=<?= $trade['id'] ?>" class="list-group-item list-group-item-action py-3">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <span class="badge <?= $trade['type'] === 'sell' ? 'bg-success' : 'bg-primary' ?> me-2">
                                <?= $trade['type'] === 'sell' ? 'Vente' : 'Achat' ?>
                            </span>
                            <span class="fw-bold"><?= htmlspecialchars($trade['title']) ?></span>
                            <br>
                            <small class="text-muted">
                                <?= htmlspecialchars($trade['game']) ?> — par <span class="text-info" onclick="event.preventDefault(); window.location='/finalmaison-main/user/profile.php?id=<?= $trade['user_id'] ?>';"><?= htmlspecialchars($trade['username']) ?></span>
                                — <?= date('d/m/Y', strtotime($trade['created_at'])) ?>
                            </small>
                        </div>
                        <div class="col-md-3 text-center">
                            <small class="text-muted">Prix demandé</small><br>
                            <span class="fw-bold text-warning"><?= number_format($trade['price_fg']) ?> J</span>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="badge bg-secondary"><?= htmlspecialchars($trade['game']) ?></span>
                        </div>
                        <div class="col-md-1 text-end">
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php
                    $queryParams = array_filter(['game' => $game, 'type' => $type, 'search' => $search]);
                    for ($i = 1; $i <= $totalPages; $i++):
                        $queryParams['page'] = $i;
                    ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($queryParams) ?>"><?= $i ?></a>
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
