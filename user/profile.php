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

// Signalement d'un user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_user']) && !$isOwnProfile && isset($_SESSION['user']['id'])) {
    $reason = trim($_POST['report_reason'] ?? '');
    if (!empty($reason)) {
        $alreadyReported = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND type = 'user' AND target_id = ? AND status = 'pending'");
        $alreadyReported->execute([$_SESSION['user']['id'], $profileId]);
        if ($alreadyReported->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO reports (reporter_id, type, target_id, reason) VALUES (?, 'user', ?, ?)")
                ->execute([$_SESSION['user']['id'], $profileId, $reason]);

            $staff = $pdo->prepare("SELECT id FROM users WHERE role IN ('moderator', 'admin') AND id != ?");
            $staff->execute([$_SESSION['user']['id']]);
            $notifMsg = htmlspecialchars($_SESSION['user']['login']) . ' a signalé le profil de ' . $user['username'];
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, reference_id, message) VALUES (?, ?, 'report', ?, ?)");
            foreach ($staff as $s) {
                $notifStmt->execute([$s['id'], $_SESSION['user']['id'], $profileId, $notifMsg]);
            }
        }
        $success = "Signalement envoyé aux modérateurs. Merci !";
    }
}

// Mise à jour de la bio (son propre profil uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile && isset($_POST['bio'])) {
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

// Derniers avis de réputation reçus
$lastReps = $pdo->prepare("
    SELECT r.*, u.username AS from_username
    FROM reputation r
    JOIN users u ON r.from_user_id = u.id
    WHERE r.to_user_id = ?
    ORDER BY r.created_at DESC LIMIT 5
");
$lastReps->execute([$profileId]);
$lastReps = $lastReps->fetchAll();

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
                    $roleLabel = match($user['role']) {
                        'admin'     => ['bg-danger', 'Admin'],
                        'moderator' => ['', 'Modérateur', 'background:var(--gold);color:#000;font-weight:700;'],
                        default     => ['bg-secondary', 'User'],
                    };
                    ?>
                    <span class="badge <?= $roleLabel[0] ?> mb-2" <?= isset($roleLabel[2]) ? 'style="'.$roleLabel[2].'"' : '' ?>><?= $roleLabel[1] ?></span>

                    <?php if ($user['bio']): ?>
                        <p class="text-muted mt-2"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                    <?php endif; ?>

                    <hr>

                    <div class="d-flex justify-content-around text-center">
                        <div>
                            <div class="fw-bold text-warning fs-5"><?= number_format($user['forum_gold']) ?></div>
                            <small class="text-muted">Julientons</small>
                            <?php if ($isOwnProfile): ?>
                                <br><a href="/projet/user/transactions.php" class="small text-info"><i class="bi bi-clock-history"></i> Historique</a>
                            <?php endif; ?>
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

            <!-- Bouton envoyer un message + signaler -->
            <?php if (!$isOwnProfile && isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
                <a href="/projet/user/conversation.php?user_id=<?= $profileId ?>" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-envelope me-1"></i>Envoyer un message
                </a>
                <button class="btn btn-outline-warning w-100 mb-3" data-bs-toggle="modal" data-bs-target="#reportUserModal">
                    <i class="bi bi-flag me-1"></i>Signaler ce profil
                </button>

                <!-- Modal signalement user -->
                <div class="modal fade" id="reportUserModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="report_user" value="1">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-flag me-2"></i>Signaler <?= htmlspecialchars($user['username']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label fw-bold">Raison du signalement</label>
                                    <select name="report_reason" class="form-select" required>
                                        <option value="">-- Choisir --</option>
                                        <option value="Spam ou publicité">Spam ou publicité</option>
                                        <option value="Arnaque / scam">Arnaque / scam</option>
                                        <option value="Comportement toxique / harcèlement">Comportement toxique / harcèlement</option>
                                        <option value="Usurpation d'identité">Usurpation d'identité</option>
                                        <option value="Autre">Autre</option>
                                    </select>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-warning"><i class="bi bi-flag me-1"></i>Signaler</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
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

            <!-- Derniers avis de réputation -->
            <h5 class="fw-bold mb-3 mt-4"><i class="bi bi-star me-2"></i>Derniers avis reçus</h5>
            <?php if (empty($lastReps)): ?>
                <p class="text-muted">Aucun avis reçu.</p>
            <?php else: ?>
                <div class="list-group mb-3">
                    <?php foreach ($lastReps as $rep): ?>
                        <?php
                        $repIcon = match($rep['rating']) {
                            'positive' => ['bi-hand-thumbs-up-fill text-success', 'Positif'],
                            'negative' => ['bi-hand-thumbs-down-fill text-danger', 'Négatif'],
                            default    => ['bi-dash-circle-fill text-muted', 'Neutre']
                        };
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi <?= $repIcon[0] ?> me-1"></i>
                                    <strong><?= $repIcon[1] ?></strong>
                                    <span class="text-muted ms-1">par
                                        <a href="/projet/user/profile.php?id=<?= $rep['from_user_id'] ?>">
                                            <?= htmlspecialchars($rep['from_username']) ?>
                                        </a>
                                    </span>
                                </div>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($rep['created_at'])) ?></small>
                            </div>
                            <?php if ($rep['comment']): ?>
                                <p class="mb-0 mt-1 small"><?= htmlspecialchars($rep['comment']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="/projet/user/reputation.php?id=<?= $profileId ?>" class="btn btn-outline-secondary btn-sm w-100">
                    Voir tous les avis (<?= ($repStats['positives'] ?? 0) + ($repStats['neutrals'] ?? 0) + ($repStats['negatives'] ?? 0) ?>)
                </a>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
