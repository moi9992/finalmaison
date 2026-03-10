<?php
require '../config/db.php';
session_start();

$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId   = $_SESSION['user']['id'] ?? null;
$userRole = $_SESSION['user']['role'] ?? 'user';

$trade = $pdo->prepare("
    SELECT t.*, u.username, u.reputation, u.role AS owner_role
    FROM trades t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$trade->execute([$id]);
$trade = $trade->fetch();

if (!$trade) {
    header('Location: index.php');
    exit;
}

// Fermer l'annonce (vendeur uniquement)
if (isset($_GET['close']) && $userId == $trade['user_id']) {
    $pdo->prepare("UPDATE trades SET status = 'closed' WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    header("Location: trade.php?id=$id");
    exit;
}

// Suppression de l'annonce (propriétaire, modo > user, admin > tous)
function canDeleteTrade($currentRole, $isOwner, $targetRole) {
    if ($currentRole === 'admin') return true;
    if ($isOwner) return true;
    if ($currentRole === 'moderator' && $targetRole === 'user') return true;
    return false;
}

if (isset($_GET['delete']) && $userId && canDeleteTrade($userRole, $userId == $trade['user_id'], $trade['owner_role'])) {
    $pdo->prepare("DELETE FROM trades WHERE id = ?")->execute([$id]);
    header('Location: index.php');
    exit;
}

// Accepter une offre
if (isset($_GET['accept_offer'])) {
    $offerId = (int)$_GET['accept_offer'];
    $offer   = $pdo->prepare("SELECT * FROM trade_offers WHERE id = ? AND trade_id = ? AND status = 'pending'");
    $offer->execute([$offerId, $id]);
    $offer = $offer->fetch();

    if ($offer && $userId == $trade['user_id'] && $trade['status'] === 'open') {
        // Vérifier que l'acheteur a assez de J
        $buyer = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $buyer->execute([$offer['user_id']]);
        $buyer = $buyer->fetch();

        if ($buyer && $buyer['forum_gold'] >= $offer['amount_fg']) {
            // Transfert de J
            $pdo->prepare("UPDATE users SET forum_gold = forum_gold - ? WHERE id = ?")->execute([$offer['amount_fg'], $buyer['id']]);
            $pdo->prepare("UPDATE users SET forum_gold = forum_gold + ? WHERE id = ?")->execute([$offer['amount_fg'], $userId]);

            // Historique
            $pdo->prepare("INSERT INTO transactions (from_user_id, to_user_id, amount, reason) VALUES (?, ?, ?, ?)")
                ->execute([$buyer['id'], $userId, $offer['amount_fg'], "Trade #$id : " . $trade['title']]);

            // Mettre à jour offre et annonce
            $pdo->prepare("UPDATE trade_offers SET status = 'accepted' WHERE id = ?")->execute([$offerId]);
            $pdo->prepare("UPDATE trade_offers SET status = 'declined' WHERE trade_id = ? AND id != ? AND status = 'pending'")->execute([$id, $offerId]);
            $pdo->prepare("UPDATE trades SET status = 'traded' WHERE id = ?")->execute([$id]);

            // Mettre à jour la session si c'est le vendeur connecté
            $_SESSION['user']['forum_gold'] = ($_SESSION['user']['forum_gold'] ?? 0) + $offer['amount_fg'];

            $success = "Offre acceptée ! " . $offer['amount_fg'] . " J transférés sur ton compte.";
        } else {
            $error = "L'acheteur n'a pas assez de J.";
        }
    }
    // Recharger le trade
    $trade = $pdo->prepare("SELECT t.*, u.username, u.reputation FROM trades t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $trade->execute([$id]);
    $trade = $trade->fetch();
}

// Refuser une offre
if (isset($_GET['decline_offer'])) {
    $offerId = (int)$_GET['decline_offer'];
    if ($userId == $trade['user_id']) {
        $pdo->prepare("UPDATE trade_offers SET status = 'declined' WHERE id = ? AND trade_id = ?")->execute([$offerId, $id]);
    }
    header("Location: trade.php?id=$id");
    exit;
}

// Annuler sa propre offre
if (isset($_GET['cancel_offer'])) {
    $offerId = (int)$_GET['cancel_offer'];
    $pdo->prepare("DELETE FROM trade_offers WHERE id = ? AND user_id = ? AND status = 'pending'")->execute([$offerId, $userId]);
    header("Location: trade.php?id=$id");
    exit;
}

// Soumettre ou modifier une offre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['isLog']) && $_SESSION['isLog']) {
    $amount_fg = (int)($_POST['amount_fg'] ?? 0);
    $message   = trim($_POST['message'] ?? '');
    $offer_id  = (int)($_POST['offer_id'] ?? 0); // 0 = nouvelle offre, >0 = modification

    if ($amount_fg > 0 && $userId != $trade['user_id'] && $trade['status'] === 'open') {
        $myFg = $pdo->prepare("SELECT forum_gold FROM users WHERE id = ?");
        $myFg->execute([$userId]);
        $myFg = $myFg->fetchColumn();

        if ($myFg >= $amount_fg) {
            if ($offer_id > 0) {
                // Modifier ou re-soumettre une offre existante (pending ou declined)
                $pdo->prepare("UPDATE trade_offers SET amount_fg = ?, message = ?, status = 'pending' WHERE id = ? AND user_id = ?")
                    ->execute([$amount_fg, $message, $offer_id, $userId]);
                $success = "Offre envoyée !";

                // Notifier le vendeur
                $notifMsg = htmlspecialchars($_SESSION['user']['login']) . ' a modifié son offre sur "' . mb_strimwidth($trade['title'], 0, 50, '...') . '" (' . number_format($amount_fg) . ' J)';
                $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, reference_id, message) VALUES (?, ?, 'trade_offer', ?, ?)")
                    ->execute([$trade['user_id'], $userId, $id, $notifMsg]);
            } else {
                // Vérifier pas déjà une offre en attente
                $existing = $pdo->prepare("SELECT COUNT(*) FROM trade_offers WHERE trade_id = ? AND user_id = ? AND status = 'pending'");
                $existing->execute([$id, $userId]);
                if ($existing->fetchColumn() == 0) {
                    $pdo->prepare("INSERT INTO trade_offers (trade_id, user_id, amount_fg, message) VALUES (?, ?, ?, ?)")
                        ->execute([$id, $userId, $amount_fg, $message]);
                    $success = "Offre envoyée !";

                    // Notifier le vendeur
                    $notifMsg = htmlspecialchars($_SESSION['user']['login']) . ' a fait une offre sur "' . mb_strimwidth($trade['title'], 0, 50, '...') . '" (' . number_format($amount_fg) . ' J)';
                    $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, reference_id, message) VALUES (?, ?, 'trade_offer', ?, ?)")
                        ->execute([$trade['user_id'], $userId, $id, $notifMsg]);
                } else {
                    $error = "Tu as déjà une offre en attente, modifie-la directement.";
                }
            }
        } else {
            $error = "Tu n'as pas assez de J (solde : $myFg J).";
        }
    } else if ($amount_fg <= 0) {
        $error = "Le montant doit être supérieur à 0.";
    }
}

// Charger les offres
$offers = $pdo->prepare("
    SELECT o.*, u.username
    FROM trade_offers o
    JOIN users u ON o.user_id = u.id
    WHERE o.trade_id = ?
    ORDER BY o.created_at DESC
");
$offers->execute([$id]);
$offers = $offers->fetchAll();

// Mon offre (pending ou declined) pour afficher le formulaire
$myOffer = null;
if ($userId && $userId != $trade['user_id']) {
    $stmt = $pdo->prepare("SELECT * FROM trade_offers WHERE trade_id = ? AND user_id = ? AND status IN ('pending', 'declined') ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$id, $userId]);
    $myOffer = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($trade['title']) ?> — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projet/trade/index.php">Trading</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($trade['title']) ?></li>
        </ol>
    </nav>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Annonce -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <span class="badge <?= $trade['type'] === 'sell' ? 'bg-success' : 'bg-primary' ?> me-2">
                    <?= $trade['type'] === 'sell' ? 'Vente' : 'Achat' ?>
                </span>
                <span class="badge bg-secondary me-2"><?= htmlspecialchars($trade['game']) ?></span>
                <?php
                $statusLabels = ['open' => ['bg-success', 'Ouverte'], 'closed' => ['bg-secondary', 'Fermée'], 'traded' => ['bg-warning text-dark', 'Échangée']];
                [$statusClass, $statusLabel] = $statusLabels[$trade['status']];
                ?>
                <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
            </div>
            <?php if ($userId && $trade['status'] === 'open'): ?>
                <div class="d-flex gap-2">
                    <?php if ($userId == $trade['user_id']): ?>
                        <a href="?id=<?= $id ?>&close=1" class="btn btn-sm btn-outline-secondary"
                           onclick="return confirm('Fermer cette annonce ?')">Fermer</a>
                    <?php endif; ?>
                    <?php if (canDeleteTrade($userRole, $userId == $trade['user_id'], $trade['owner_role'])): ?>
                        <a href="?id=<?= $id ?>&delete=1" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Supprimer cette annonce ?')">Supprimer</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h4 class="fw-bold"><?= htmlspecialchars($trade['title']) ?></h4>
            <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($trade['description'] ?? '')) ?></p>
            <div class="d-flex gap-4">
                <div>
                    <small class="text-muted">Prix demandé</small><br>
                    <span class="fs-5 fw-bold text-warning"><?= number_format($trade['price_fg']) ?> J</span>
                </div>
                <div>
                    <small class="text-muted">Vendeur</small><br>
                    <span class="fw-bold"><?= htmlspecialchars($trade['username']) ?></span>
                    <small class="text-muted ms-1">(réputation: <?= $trade['reputation'] ?>)</small>
                </div>
                <div>
                    <small class="text-muted">Publié le</small><br>
                    <span><?= date('d/m/Y à H:i', strtotime($trade['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Offres reçues (visible par le vendeur) ou ses propres offres -->
        <div class="col-md-7">
            <h5 class="fw-bold mb-3">Offres (<?= count($offers) ?>)</h5>
            <?php if (empty($offers)): ?>
                <p class="text-muted">Aucune offre pour le moment.</p>
            <?php else: ?>
                <?php foreach ($offers as $offer): ?>
                    <?php
                    $offerBadge = match($offer['status']) {
                        'accepted' => 'bg-success',
                        'declined' => 'bg-danger',
                        default    => 'bg-warning text-dark'
                    };
                    $offerLabel = match($offer['status']) {
                        'accepted' => 'Acceptée',
                        'declined' => 'Refusée',
                        default    => 'En attente'
                    };
                    ?>
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold"><?= htmlspecialchars($offer['username']) ?></span>
                                    propose <span class="text-warning fw-bold"><?= number_format($offer['amount_fg']) ?> J</span>
                                    <?php if ($offer['message']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($offer['message']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge <?= $offerBadge ?>"><?= $offerLabel ?></span>
                                    <?php if ($userId == $trade['user_id'] && $offer['status'] === 'pending' && $trade['status'] === 'open'): ?>
                                        <a href="?id=<?= $id ?>&accept_offer=<?= $offer['id'] ?>"
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Accepter cette offre de <?= $offer['amount_fg'] ?> J ?')">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                        <a href="?id=<?= $id ?>&decline_offer=<?= $offer['id'] ?>"
                                           class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($offer['created_at'])) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulaire d'offre -->
        <div class="col-md-5">
            <?php if ($trade['status'] === 'open' && isset($_SESSION['isLog']) && $_SESSION['isLog'] && $userId != $trade['user_id']): ?>
                <div class="card">
                    <div class="card-header fw-bold">
                        <?php
                        if (!$myOffer) echo 'Faire une offre';
                        elseif ($myOffer['status'] === 'declined') echo 'Re-soumettre une offre';
                        else echo 'Modifier mon offre';
                        ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php if ($myOffer): ?>
                                <input type="hidden" name="offer_id" value="<?= $myOffer['id'] ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Montant en J</label>
                                <div class="input-group">
                                    <input type="number" name="amount_fg" class="form-control" min="1"
                                           value="<?= $myOffer ? $myOffer['amount_fg'] : '' ?>"
                                           placeholder="0" required>
                                    <span class="input-group-text text-warning fw-bold">J</span>
                                </div>
                                <small class="text-muted">Ton solde : <?= $_SESSION['user']['forum_gold'] ?? 0 ?> J</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Message (optionnel)</label>
                                <input type="text" name="message" class="form-control"
                                       value="<?= htmlspecialchars($myOffer['message'] ?? '') ?>"
                                       placeholder="Ex: Je suis dispo ce soir">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <?php
                                    if (!$myOffer) echo "Envoyer l'offre";
                                    elseif ($myOffer['status'] === 'declined') echo 'Re-soumettre';
                                    else echo "Modifier l'offre";
                                    ?>
                                </button>
                                <?php if ($myOffer): ?>
                                    <a href="?id=<?= $id ?>&cancel_offer=<?= $myOffer['id'] ?>"
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Annuler ton offre ?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($trade['status'] !== 'open'): ?>
                <div class="alert alert-secondary">Cette annonce est fermée.</div>
            <?php elseif (!isset($_SESSION['isLog']) || !$_SESSION['isLog']): ?>
                <div class="alert alert-info">
                    <a href="/projet/auth/login.php">Connecte-toi</a> pour faire une offre.
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
