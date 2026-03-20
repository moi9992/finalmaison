<?php
require 'config/db.php';

// Stats globales
$nbUsers  = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nbTrades = $pdo->query("SELECT COUNT(*) FROM trades")->fetchColumn();
$nbPosts  = $pdo->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();

// Dernières annonces de trading
$recentTrades = $pdo->query("
    SELECT t.*, u.username
    FROM trades t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'open'
    ORDER BY t.created_at DESC
    LIMIT 5
")->fetchAll();

// Derniers topics du forum
$recentTopics = $pdo->query("
    SELECT ft.*, u.username, fc.name AS category_name
    FROM forum_topics ft
    JOIN users u ON ft.user_id = u.id
    JOIN forum_categories fc ON ft.category_id = fc.id
    ORDER BY ft.updated_at DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HYPERBUSINESS — Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/finalmaison-main/assets/css/style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

<!-- Hero -->
<div class="hero-section">
    <div class="container">
        <div class="section-label" style="color:#a0aabf;">🎮 Plateforme de trading et de service</div>
        <h1>HYPERBUSINESS<br><span>TRADE</span></h1>
        <p class="mt-3" style="color:#a0aabf;">La plateforme de trading d'items / services par les joueurs pour les joueurs</p>
        <?php if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']): ?>
            <div class="mt-4 d-flex gap-3 justify-content-center">
                <a href="/finalmaison-main/auth/register.php" class="btn btn-primary btn-lg">Rejoindre</a>
                <a href="/finalmaison-main/auth/login.php" class="btn btn-secondary btn-lg">Se connecter</a>
            </div>
        <?php else: ?>
            <div class="mt-4 d-flex gap-3 justify-content-center">
                <a href="/finalmaison-main/trade/index.php" class="btn btn-primary btn-lg">Voir les annonces</a>
                <a href="/finalmaison-main/forum/index.php" class="btn btn-secondary btn-lg">Forum</a>
                <a href="hourly-raffle/index.php" class="btn btn-warning btn-lg">Hourly Raffle</a>
                <a href="black-jack/blackjack.php" class="btn btn-light btn-lg">Black Jack</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="stats-bar">
    <div class="container">
        <div class="row text-center">
            <div class="col-4">
                <div class="stat-val"><?= number_format($nbUsers) ?></div>
                <div class="stat-label">Membres</div>
            </div>
            <div class="col-4">
                <div class="stat-val"><?= number_format($nbTrades) ?></div>
                <div class="stat-label">Annonces</div>
            </div>
            <div class="col-4">
                <div class="stat-val"><?= number_format($nbPosts) ?></div>
                <div class="stat-label">Messages</div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="container my-5">
    <div class="row g-4">

        <!-- Dernières annonces -->
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-tags me-2"></i>Dernières annonces</h5>
                <a href="/finalmaison-main/trade/index.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <?php if (empty($recentTrades)): ?>
                <p class="text-muted">Aucune annonce pour le moment.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($recentTrades as $trade): ?>
                        <a href="/finalmaison-main/trade/trade.php?id=<?= $trade['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span>
                                    <span class="badge <?= $trade['type'] === 'sell' ? 'bg-success' : 'bg-primary' ?> me-2">
                                        <?= $trade['type'] === 'sell' ? 'Vente' : 'Achat' ?>
                                    </span>
                                    <?= htmlspecialchars($trade['title']) ?>
                                </span>
                                <span class="text-warning fw-bold"><?= $trade['price_fg'] ?> J</span>
                            </div>
                            <small class="text-muted">
                                <?= htmlspecialchars($trade['game']) ?> — par <?= htmlspecialchars($trade['username']) ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Derniers topics -->
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-chat-dots me-2"></i>Derniers topics</h5>
                <a href="/finalmaison-main/forum/index.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <?php if (empty($recentTopics)): ?>
                <p class="text-muted">Aucun topic pour le moment.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($recentTopics as $topic): ?>
                        <a href="/finalmaison-main/forum/topic.php?id=<?= $topic['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span><?= htmlspecialchars($topic['title']) ?></span>
                                <small class="text-muted"><?= $topic['views'] ?> vues</small>
                            </div>
                            <small class="text-muted">
                                <?= htmlspecialchars($topic['category_name']) ?> — par <?= htmlspecialchars($topic['username']) ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
