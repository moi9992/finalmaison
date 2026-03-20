<?php
require '../config/db.php';
session_start();

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['user']['id'] ?? 0);

if (!$profileId) {
    header('Location: /finalmaison-main/auth/login.php');
    exit;
}

$user = $pdo->prepare("SELECT id, username, bio, forum_gold, reputation, role, created_at, last_login, avatar FROM users WHERE id = ?");
$user->execute([$profileId]);
$user = $user->fetch();

if (!$user) {
    header('Location: /finalmaison-main/index.php');
    exit;
}

$isOwnProfile = isset($_SESSION['user']['id']) && $_SESSION['user']['id'] == $profileId;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile && isset($_POST['bio'])) {
    $bio = trim($_POST['bio'] ?? '');
    $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?")->execute([$bio, $profileId]);
    $user['bio'] = $bio;
    $success = "Profil mis à jour !";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Erreur lors de l'upload.";
    } elseif (!in_array($file['type'], $allowed)) {
        $error = "Format non supporté. JPG, PNG, GIF ou WEBP uniquement.";
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $error = "L'image ne doit pas dépasser 2 Mo.";
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $profileId . '.' . $ext;
        $dest = __DIR__ . '/../assets/uploads/avatars/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $avatarPath = '/finalmaison-main/assets/uploads/avatars/' . $filename;
            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatarPath, $profileId]);
            $user['avatar'] = $avatarPath;
            $success = "Avatar mis à jour !";
        } else {
            $error = "Impossible de sauvegarder l'image.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile && isset($_POST['change_password'])) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd     = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $row->execute([$profileId]);
    $row = $row->fetch();

    if (!password_verify($currentPwd, $row['password'])) {
        $error = "Mot de passe actuel incorrect.";
    } elseif ($newPwd !== $confirmPwd) {
        $error = "Les deux nouveaux mots de passe ne correspondent pas.";
    } else {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPwd, PASSWORD_DEFAULT), $profileId]);
        $success = "Mot de passe changé avec succès !";
    }
}

$nbTopics = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE user_id = ?");
$nbTopics->execute([$profileId]);
$nbTopics = $nbTopics->fetchColumn();

$nbPosts = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE user_id = ?");
$nbPosts->execute([$profileId]);
$nbPosts = $nbPosts->fetchColumn();

$nbTrades = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE user_id = ? AND status = 'traded'");
$nbTrades->execute([$profileId]);
$nbTrades = $nbTrades->fetchColumn();

$repStats = $pdo->prepare("
    SELECT
        SUM(rating = 'positive') AS positives,
        SUM(rating = 'neutral')  AS neutrals,
        SUM(rating = 'negative') AS negatives
    FROM reputation WHERE to_user_id = ?
");
$repStats->execute([$profileId]);
$repStats = $repStats->fetch();

$lastReps = $pdo->prepare("
    SELECT r.*, u.username AS from_username
    FROM reputation r
    JOIN users u ON r.from_user_id = u.id
    WHERE r.to_user_id = ?
    ORDER BY r.created_at DESC LIMIT 5
");
$lastReps->execute([$profileId]);
$lastReps = $lastReps->fetchAll();

$lastTrades = $pdo->prepare("
    SELECT * FROM trades WHERE user_id = ? ORDER BY created_at DESC LIMIT 5
");
$lastTrades->execute([$profileId]);
$lastTrades = $lastTrades->fetchAll();

function getRank($posts) {
    $ranks = [
        ['name' => 'No Life',  'min' => 10000, 'color' => '#ff003c', 'secret' => true],
        ['name' => 'Faker',    'min' => 1000,  'color' => '#f5c800'],
        ['name' => 'Pro',      'min' => 500,   'color' => '#00e87a'],
        ['name' => 'Expert',   'min' => 300,   'color' => '#4ac8ff'],
        ['name' => 'Vétéran',  'min' => 150,   'color' => '#9b59b6'],
        ['name' => 'Confirmé', 'min' => 50,    'color' => '#3498db'],
        ['name' => 'Apprenti', 'min' => 20,    'color' => '#2ecc71'],
        ['name' => 'Débutant', 'min' => 5,     'color' => '#95a5a6'],
        ['name' => 'Noob',     'min' => 0,     'color' => '#7f8c8d'],
    ];
    foreach ($ranks as $i => $rank) {
        if ($posts >= $rank['min']) {
            $next = $i > 0 ? $ranks[$i - 1] : null;
            $progress = $next
                ? min(100, round(($posts - $rank['min']) / ($next['min'] - $rank['min']) * 100))
                : 100;
            return ['current' => $rank, 'next' => $next, 'progress' => $progress];
        }
    }
}
$rankInfo = getRank($nbPosts);

$isOnline = $user['last_login'] && (time() - strtotime($user['last_login'])) < 900;

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

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="avatar"
                             class="rounded-circle mb-2" style="width:96px;height:96px;object-fit:cover;border:2px solid var(--border);">
                    <?php else: ?>
                        <div class="display-1 mb-2"><i class="bi bi-person-circle text-secondary"></i></div>
                    <?php endif; ?>
                    <?php if ($isOwnProfile): ?>
                        <div class="mb-2">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                <i class="bi bi-camera me-1"></i>Changer l'avatar
                            </button>
                        </div>
                    <?php endif; ?>
                    <h4 class="fw-bold mb-1">
                        <?= htmlspecialchars($user['username']) ?>
                        <span title="<?= $isOnline ? 'En ligne' : 'Hors ligne' ?>"
                              style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $isOnline ? '#00e87a' : '#4a5068' ?>;margin-left:6px;vertical-align:middle;"></span>
                    </h4>
                    <?php
                    $roleLabel = match($user['role']) {
                        'admin'     => ['bg-danger', 'Admin'],
                        'moderator' => ['', 'Modérateur', 'background:var(--gold);color:#000;font-weight:700;'],
                        default     => ['bg-secondary', 'User'],
                    };
                    ?>
                    <span class="badge <?= $roleLabel[0] ?> mb-2" <?= isset($roleLabel[2]) ? 'style="'.$roleLabel[2].'"' : '' ?>><?= $roleLabel[1] ?></span>

                    <div class="mt-2 mb-1">
                        <span class="fw-bold" style="color:<?= $rankInfo['current']['color'] ?>;">
                            <i class="bi bi-controller me-1"></i><?= $rankInfo['current']['name'] ?>
                        </span>
                    </div>
                    <div class="px-2 mb-2">
                        <div class="progress" style="height:6px;background:#1e2035;">
                            <div class="progress-bar" style="width:<?= $rankInfo['progress'] ?>%;background:<?= $rankInfo['current']['color'] ?>;transition:width 0.4s;"></div>
                        </div>
                        <small class="text-muted">
                            <?php if ($rankInfo['next'] && empty($rankInfo['next']['secret'])): ?>
                                <?= $nbPosts ?> / <?= $rankInfo['next']['min'] ?> posts → <span style="color:<?= $rankInfo['next']['color'] ?>;"><?= $rankInfo['next']['name'] ?></span>
                            <?php elseif (!$rankInfo['next'] || !empty($rankInfo['current']['secret'])): ?>
                                Rang maximum atteint 🎉
                            <?php else: ?>
                                <?= $nbPosts ?> posts — Rang maximum atteint 🎉
                            <?php endif; ?>
                        </small>
                    </div>

                    <?php if ($user['bio']): ?>
                        <p class="text-muted mt-2"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                    <?php endif; ?>

                    <hr>

                    <div class="d-flex justify-content-around text-center">
                        <div>
                            <div class="fw-bold text-warning fs-5"><?= number_format($user['forum_gold']) ?></div>
                            <small class="text-muted">Julientons</small>
                            <?php if ($isOwnProfile): ?>
                                <br><a href="/finalmaison-main/user/transactions.php" class="small text-info"><i class="bi bi-clock-history"></i> Historique</a>
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

            <?php if (!$isOwnProfile && isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
                <a href="/finalmaison-main/user/conversation.php?user_id=<?= $profileId ?>" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-envelope me-1"></i>Envoyer un message
                </a>
                <button class="btn btn-outline-warning w-100 mb-3" data-bs-toggle="modal" data-bs-target="#reportUserModal">
                    <i class="bi bi-flag me-1"></i>Signaler ce profil
                </button>

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

            <?php if ($isOwnProfile): ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <div class="card mb-3">
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
                <div class="modal fade" id="avatarModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Changer mon avatar</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label fw-bold">Image (JPG, PNG, GIF, WEBP — max 2 Mo)</label>
                                    <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Uploader</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <button class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#changePwdModal">
                    <i class="bi bi-key me-1"></i>Changer mon mot de passe
                </button>

                <div class="modal fade" id="changePwdModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="change_password" value="1">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Changer mon mot de passe</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Mot de passe actuel</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Nouveau mot de passe</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold">Confirmer le nouveau mot de passe</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-warning"><i class="bi bi-key me-1"></i>Confirmer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">

            <h5 class="fw-bold mb-3"><i class="bi bi-tags me-2"></i>Dernières annonces</h5>
            <?php if (empty($lastTrades)): ?>
                <p class="text-muted mb-4">Aucune annonce.</p>
            <?php else: ?>
                <div class="list-group mb-4">
                    <?php foreach ($lastTrades as $trade): ?>
                        <a href="/finalmaison-main/trade/trade.php?id=<?= $trade['id'] ?>" class="list-group-item list-group-item-action">
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

            <h5 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2"></i>Derniers topics</h5>
            <?php if (empty($lastTopics)): ?>
                <p class="text-muted">Aucun topic.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($lastTopics as $topic): ?>
                        <a href="/finalmaison-main/forum/topic.php?id=<?= $topic['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="fw-bold"><?= htmlspecialchars($topic['title']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($topic['category_name']) ?> — <?= date('d/m/Y', strtotime($topic['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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
                                        <a href="/finalmaison-main/user/profile.php?id=<?= $rep['from_user_id'] ?>">
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
                <a href="/finalmaison-main/user/reputation.php?id=<?= $profileId ?>" class="btn btn-outline-secondary btn-sm w-100">
                    Voir tous les avis (<?= ($repStats['positives'] ?? 0) + ($repStats['neutrals'] ?? 0) + ($repStats['negatives'] ?? 0) ?>)
                </a>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
