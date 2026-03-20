<?php
require 'config/db.php';
session_start();

$q    = trim($_GET['q'] ?? '');
$tab  = $_GET['tab'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$topics = $trades = $members = [];
$totalTopics = $totalTrades = $totalMembers = 0;

$aliases = [
    'mc'        => 'minecraft',
    'lol'       => 'league of legends',
    'tft'       => 'teamfight tactics',
    'valo'      => 'valorant',
    'dofus'     => 'dofus',
    'd2'        => 'diablo 2',
    'poe'       => 'path of exile',
    'le'        => 'last epoch',
    'dad'       => 'dark and darker',
    'osrs'      => 'old school runescape',
    'bj'        => 'blackjack',
    'fg'        => 'julienton',
];
$qLower = strtolower($q);
$expanded = $aliases[$qLower] ?? null;

if ($q !== '') {
    $like = "%$q%";
    $likes = [$like];
    if ($expanded) {
        $likes[] = "%$expanded%";
    }

    $topicWhere = "ft.title LIKE ? OR fc.name LIKE ?";
    $topicParams = [$like, $like];
    $tradeWhere = "t.title LIKE ? OR t.description LIKE ? OR t.game LIKE ?";
    $tradeParams = [$like, $like, $like];
    $memberWhere = "username LIKE ?";
    $memberParams = [$like];
    if ($expanded) {
        $eLike = "%$expanded%";
        $topicWhere .= " OR ft.title LIKE ? OR fc.name LIKE ?";
        $topicParams[] = $eLike; $topicParams[] = $eLike;
        $tradeWhere .= " OR t.title LIKE ? OR t.description LIKE ? OR t.game LIKE ?";
        $tradeParams[] = $eLike; $tradeParams[] = $eLike; $tradeParams[] = $eLike;
        $memberWhere .= " OR username LIKE ?";
        $memberParams[] = $eLike;
    }

    if ($tab === 'all' || $tab === 'topics') {
        $limit = $tab === 'all' ? 5 : $perPage;
        $off   = $tab === 'all' ? 0 : $offset;

        $cnt = $pdo->prepare("
            SELECT COUNT(*) FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE $topicWhere
        ");
        $cnt->execute($topicParams);
        $totalTopics = $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT ft.id, ft.title, ft.created_at, ft.views, ft.is_pinned, ft.is_locked,
                   u.username, u.id AS user_id, fc.name AS category_name, fc.slug AS category_slug,
                   (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) AS post_count
            FROM forum_topics ft
            JOIN users u ON ft.user_id = u.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE $topicWhere
            ORDER BY ft.created_at DESC
            LIMIT $limit OFFSET $off
        ");
        $stmt->execute($topicParams);
        $topics = $stmt->fetchAll();
    }

    if ($tab === 'all' || $tab === 'trades') {
        $limit = $tab === 'all' ? 5 : $perPage;
        $off   = $tab === 'all' ? 0 : $offset;

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM trades t WHERE $tradeWhere");
        $cnt->execute($tradeParams);
        $totalTrades = $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT t.id, t.title, t.type, t.game, t.price_fg, t.status, t.created_at,
                   u.username, u.id AS user_id
            FROM trades t
            JOIN users u ON t.user_id = u.id
            WHERE $tradeWhere
            ORDER BY t.bumped_at DESC
            LIMIT $limit OFFSET $off
        ");
        $stmt->execute($tradeParams);
        $trades = $stmt->fetchAll();
    }

    if ($tab === 'all' || $tab === 'members') {
        $limit = $tab === 'all' ? 5 : $perPage;
        $off   = $tab === 'all' ? 0 : $offset;

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $memberWhere");
        $cnt->execute($memberParams);
        $totalMembers = $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id, username, role, reputation, forum_gold, created_at, is_banned
            FROM users
            WHERE $memberWhere
            ORDER BY reputation DESC
            LIMIT $limit OFFSET $off
        ");
        $stmt->execute($memberParams);
        $members = $stmt->fetchAll();
    }
}

$totalItems = match($tab) {
    'topics'  => $totalTopics,
    'trades'  => $totalTrades,
    'members' => $totalMembers,
    default   => 0,
};
$totalPages = $tab === 'all' ? 0 : max(1, ceil($totalItems / $perPage));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche — HYPERBUSINESS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <form method="GET" class="mb-4">
        <div class="input-group input-group-lg">
            <span class="input-group-text" style="background:var(--panel);border-color:var(--border);color:var(--muted);"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Rechercher des topics, annonces, membres..."
                   value="<?= htmlspecialchars($q) ?>" autofocus>
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </div>
    </form>

    <?php if ($q !== ''): ?>
        <ul class="nav nav-tabs mb-4">
            <?php
            $tabs = [
                'all'     => ['Tout', $totalTopics + $totalTrades + $totalMembers],
                'topics'  => ['Topics', $totalTopics],
                'trades'  => ['Annonces', $totalTrades],
                'members' => ['Membres', $totalMembers],
            ];
            if ($tab !== 'all') {
                if ($tab === 'topics') {
                    $c1 = $pdo->prepare("SELECT COUNT(*) FROM trades t WHERE $tradeWhere");
                    $c1->execute($tradeParams);
                    $totalTrades = $c1->fetchColumn();
                    $c2 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $memberWhere");
                    $c2->execute($memberParams);
                    $totalMembers = $c2->fetchColumn();
                } elseif ($tab === 'trades') {
                    $c1 = $pdo->prepare("SELECT COUNT(*) FROM forum_topics ft JOIN forum_categories fc ON ft.category_id = fc.id WHERE $topicWhere");
                    $c1->execute($topicParams);
                    $totalTopics = $c1->fetchColumn();
                    $c2 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $memberWhere");
                    $c2->execute($memberParams);
                    $totalMembers = $c2->fetchColumn();
                } elseif ($tab === 'members') {
                    $c1 = $pdo->prepare("SELECT COUNT(*) FROM forum_topics ft JOIN forum_categories fc ON ft.category_id = fc.id WHERE $topicWhere");
                    $c1->execute($topicParams);
                    $totalTopics = $c1->fetchColumn();
                    $c2 = $pdo->prepare("SELECT COUNT(*) FROM trades t WHERE $tradeWhere");
                    $c2->execute($tradeParams);
                    $totalTrades = $c2->fetchColumn();
                }
                $tabs['all'][1] = $totalTopics + $totalTrades + $totalMembers;
                $tabs['topics'][1] = $totalTopics;
                $tabs['trades'][1] = $totalTrades;
                $tabs['members'][1] = $totalMembers;
            }
            foreach ($tabs as $key => [$label, $count]): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
                       href="?q=<?= urlencode($q) ?>&tab=<?= $key ?>">
                        <?= $label ?> <span class="badge bg-secondary ms-1"><?= $count ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($totalTopics + $totalTrades + $totalMembers === 0): ?>
            <div class="alert alert-info">Aucun résultat pour « <?= htmlspecialchars($q) ?> »</div>
        <?php endif; ?>

        <?php if (!empty($topics)): ?>
            <div class="mb-5">
                <h5 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2"></i>Topics
                    <?php if ($tab === 'all' && $totalTopics > 5): ?>
                        <a href="?q=<?= urlencode($q) ?>&tab=topics" class="btn btn-sm btn-outline-primary ms-2">Voir tous (<?= $totalTopics ?>)</a>
                    <?php endif; ?>
                </h5>
                <div class="list-group">
                    <?php foreach ($topics as $t): ?>
                        <a href="/finalmaison-main/forum/topic.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if ($t['is_pinned']): ?><span class="badge bg-warning text-dark me-1"><i class="bi bi-pin"></i></span><?php endif; ?>
                                    <?php if ($t['is_locked']): ?><span class="badge bg-secondary me-1"><i class="bi bi-lock"></i></span><?php endif; ?>
                                    <span class="fw-bold"><?= htmlspecialchars($t['title']) ?></span>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($t['category_name']) ?> — par <?= htmlspecialchars($t['username']) ?>
                                        — <?= $t['post_count'] ?> messages — <?= $t['views'] ?> vues
                                    </small>
                                </div>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($t['created_at'])) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($trades)): ?>
            <div class="mb-5">
                <h5 class="fw-bold mb-3"><i class="bi bi-tags me-2"></i>Annonces
                    <?php if ($tab === 'all' && $totalTrades > 5): ?>
                        <a href="?q=<?= urlencode($q) ?>&tab=trades" class="btn btn-sm btn-outline-primary ms-2">Voir toutes (<?= $totalTrades ?>)</a>
                    <?php endif; ?>
                </h5>
                <div class="list-group">
                    <?php foreach ($trades as $t): ?>
                        <a href="/finalmaison-main/trade/trade.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge <?= $t['type'] === 'sell' ? 'bg-success' : 'bg-primary' ?> me-1">
                                        <?= $t['type'] === 'sell' ? 'Vente' : 'Achat' ?>
                                    </span>
                                    <?php if ($t['status'] !== 'open'): ?>
                                        <span class="badge bg-secondary me-1"><?= $t['status'] === 'traded' ? 'Échangé' : 'Fermé' ?></span>
                                    <?php endif; ?>
                                    <span class="fw-bold"><?= htmlspecialchars($t['title']) ?></span>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($t['game']) ?> — par <?= htmlspecialchars($t['username']) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-warning"><?= number_format($t['price_fg']) ?> J</span><br>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($t['created_at'])) ?></small>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($members)): ?>
            <div class="mb-5">
                <h5 class="fw-bold mb-3"><i class="bi bi-people me-2"></i>Membres
                    <?php if ($tab === 'all' && $totalMembers > 5): ?>
                        <a href="?q=<?= urlencode($q) ?>&tab=members" class="btn btn-sm btn-outline-primary ms-2">Voir tous (<?= $totalMembers ?>)</a>
                    <?php endif; ?>
                </h5>
                <div class="list-group">
                    <?php foreach ($members as $m): ?>
                        <a href="/finalmaison-main/user/profile.php?id=<?= $m['id'] ?>" class="list-group-item list-group-item-action <?= $m['is_banned'] ? 'opacity-50' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-person-circle me-1"></i>
                                    <span class="fw-bold"><?= htmlspecialchars($m['username']) ?></span>
                                    <?php
                                    $badge = match($m['role']) {
                                        'admin'     => '<span class="badge bg-danger ms-1">Admin</span>',
                                        'moderator' => '<span class="badge ms-1" style="background:var(--gold);color:#000;font-weight:700;">Modo</span>',
                                        default     => '',
                                    };
                                    echo $badge;
                                    ?>
                                    <?php if ($m['is_banned']): ?>
                                        <span class="badge bg-dark ms-1">Banni</span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted">
                                        Réputation: <?php
                                        $rep = $m['reputation'];
                                        $color = $rep > 0 ? 'text-success' : ($rep < 0 ? 'text-danger' : 'text-muted');
                                        echo "<span class=\"$color fw-bold\">" . ($rep > 0 ? '+' : '') . "$rep</span>";
                                        ?> — <?= number_format($m['forum_gold']) ?> J
                                    </small>
                                </div>
                                <small class="text-muted">Inscrit le <?= date('d/m/Y', strtotime($m['created_at'])) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&tab=<?= $tab ?>&page=<?= $page - 1 ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 3);
                    $end   = min($totalPages, $page + 3);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&tab=<?= $tab ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&tab=<?= $tab ?>&page=<?= $page + 1 ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <p class="text-muted text-center mt-5">Tape quelque chose pour lancer la recherche.</p>
    <?php endif; ?>

</div>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
