<?php
require '../config/db.php';
session_start();

// Profil demandé via ?id=X ou son propre profil
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['user']['id'] ?? 0);

if (!$profileId) {
    header('Location: /projet/auth/login.php');
    exit;
}

$user = $pdo->prepare("SELECT id, username, bio, forum_gold, reputation, role, created_at, last_login FROM users WHERE id = ?");
$user->execute([$profileId]);
$user = $user->fetch();

if (!$user) {
    header('Location: /projet/index.php');
    exit;
}

$isOwnProfile = isset($_SESSION['user']['id']) && $_SESSION['user']['id'] == $profileId;

// Mise à jour de la bio (son propre profil uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    $bio = trim($_POST['bio'] ?? '');
    $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?")->execute([$bio, $profileId]);
    $user['bio'] = $bio;
    $success = "Profil mis à jour !";
}

// Stats du profil
$nbTopics = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE user_id = ?");
$nbTopics->execute([$profileId]);
$nbTopics = $nbTopics->fetchColumn();

$nbPosts = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE user_id = ?");
$nbPosts->execute([$profileId]);
$nbPosts = $nbPosts->fetchColumn();

$nbTrades = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE user_id = ? AND status = 'traded'");
$nbTrades->execute([$profileId]);
$nbTrades = $nbTrades->fetchColumn();

// Réputation détaillée
$repStats = $pdo->prepare("
    SELECT
        SUM(rating = 'positive') AS positives,
        SUM(rating = 'neutral')  AS neutrals,
        SUM(rating = 'negative') AS negatives
    FROM reputation WHERE to_user_id = ?
");
$repStats->execute([$profileId]);
$repStats = $repStats->fetch();

// Dernières annonces
$lastTrades = $pdo->prepare("
    SELECT * FROM trades WHERE user_id = ? ORDER BY created_at DESC LIMIT 5
");
$lastTrades->execute([$profileId]);
$lastTrades = $lastTrades->fetchAll();

// Derniers topics
$lastTopics = $pdo->prepare("
    SELECT ft.*, fc.name AS category_name
    FROM forum_topics ft
    JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.user_id = ?
    ORDER BY ft.created_at DESC LIMIT 5
");
$lastTopics->execute([$profileId]);
$lastTopics = $lastTopics->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container my-5">

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Colonne gauche : infos profil -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <div class="display-1 mb-2"><i class="bi bi-person-circle text-secondary"></i></div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['username']) ?></h4>
                    <?php
                    $roleBadge = match($user['role']) {
                        'admin'     => 'bg-danger',
                        'moderator' => 'bg-warning text-dark',
                        default     => 'bg-secondary'
                    };
                    ?>
                    <span class="badge <?= $roleBadge ?> mb-2"><?= $user['role'] ?></span>

                    <?php if ($user['bio']): ?>
                        <p class="text-muted mt-2"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                    <?php endif; ?>

                    <hr>

                    <div class="d-flex justify-content-around text-center">
                        <div>
                            <div class="fw-bold text-warning fs-5"><?= number_format($user['forum_gold']) ?></div>
                            <small class="text-muted">Julientons</small>
                        </div>
                        <div>
                            <?php
                            $rep   = $user['reputation'];
                            $color = $rep > 0 ? 'text-success' : ($rep < 0 ? 'text-danger' : 'text-muted');
                            ?>
                            <div class="fw-bold fs-5 <?= $color ?>"><?= $rep > 0 ? '+' : '' ?><?= $rep ?></div>
                            <small class="text-muted">Réputation</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="card mb-3">
                <div class="card-header fw-bold">Statistiques</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-chat-dots me-2"></i>Topics créés</span>
                        <span class="fw-bold"><?= $nbTopics ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-chat-left-text me-2"></i>Messages postés</span>
                        <span class="fw-bold"><?= $nbPosts ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-tags me-2"></i>Trades complétés</span>
                        <span class="fw-bold"><?= $nbTrades ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-calendar me-2"></i>Inscrit le</span>
                        <span class="fw-bold"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                    </li>
                </ul>
            </div>

            <!-- Réputation détaillée -->
            <div class="card mb-3">
                <div class="card-header fw-bold">Réputation</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-success"><i class="bi bi-hand-thumbs-up me-2"></i>Positif</span>
                        <span class="fw-bold text-success"><?= $repStats['positives'] ?? 0 ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-dash-circle me-2"></i>Neutre</span>
                        <span class="fw-bold"><?= $repStats['neutrals'] ?? 0 ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-danger"><i class="bi bi-hand-thumbs-down me-2"></i>Négatif</span>
                        <span class="fw-bold text-danger"><?= $repStats['negatives'] ?? 0 ?></span>
                    </li>
                </ul>
            </div>

            <!-- Bouton envoyer un message -->
            <?php if (!$isOwnProfile && isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
                <a href="/projet/user/conversation.php?user_id=<?= $profileId ?>" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-envelope me-1"></i>Envoyer un message
                </a>
            <?php endif; ?>

            <!-- Modifier la bio (son propre profil) -->
            <?php if ($isOwnProfile): ?>
                <div class="card">
                    <div class="card-header fw-bold">Modifier ma bio</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <textarea name="bio" class="form-control" rows="3" placeholder="Parle de toi..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Sauvegarder</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Colonne droite : activité -->
        <div class="col-lg-8">

            <!-- Dernières annonces -->
            <h5 class="fw-bold mb-3"><i class="bi bi-tags me-2"></i>Dernières annonces</h5>
            <?php if (empty($lastTrades)): ?>
                <p class="text-muted mb-4">Aucune annonce.</p>
            <?php else: ?>
                <div class="list-group mb-4">
                    <?php foreach ($lastTrades as $trade): ?>
                        <a href="/projet/trade/trade.php?id=<?= $trade['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span>
                                    <span class="badge <?= $trade['type'] === 'sell' ? 'bg-success' : 'bg-primary' ?> me-1">
                                        <?= $trade['type'] === 'sell' ? 'Vente' : 'Achat' ?>
                                    </span>
                                    <?= htmlspecialchars($trade['title']) ?>
                                </span>
                                <span class="text-warning fw-bold"><?= number_format($trade['price_fg']) ?> J</span>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($trade['game']) ?> — <?= date('d/m/Y', strtotime($trade['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Derniers topics -->
            <h5 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2"></i>Derniers topics</h5>
            <?php if (empty($lastTopics)): ?>
                <p class="text-muted">Aucun topic.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($lastTopics as $topic): ?>
                        <a href="/projet/forum/topic.php?id=<?= $topic['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="fw-bold"><?= htmlspecialchars($topic['title']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($topic['category_name']) ?> — <?= date('d/m/Y', strtotime($topic['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
