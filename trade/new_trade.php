<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']) {
    header('Location: /projet/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$games = ['Diablo 2', 'Path of Exile', 'Last Epoch', 'Dark and Darker', 'OSRS', 'Autre'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = $_POST['type'] ?? '';
    $game        = $_POST['game'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price_fg    = (int)($_POST['price_fg'] ?? 0);

    if (in_array($type, ['sell', 'buy']) && !empty($game) && !empty($title) && $price_fg >= 0) {
        $pdo->prepare("INSERT INTO trades (user_id, type, game, title, description, price_fg) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$_SESSION['user']['id'], $type, $game, $title, $description, $price_fg]);
        $tradeId = $pdo->lastInsertId();
        header("Location: /projet/trade/trade.php?id=$tradeId");
        exit;
    } else {
        $error = "Merci de remplir tous les champs obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle annonce — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container my-5">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projet/trade/index.php">Trading</a></li>
            <li class="breadcrumb-item active">Nouvelle annonce</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header fw-bold fs-5">
            <i class="bi bi-plus-lg me-2"></i>Créer une annonce
        </div>
        <div class="card-body">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Type *</label>
                        <select name="type" class="form-select" required>
                            <option value="">-- Choisir --</option>
                            <option value="sell">Vente</option>
                            <option value="buy">Achat</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Jeu *</label>
                        <select name="game" class="form-select" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($games as $g): ?>
                                <option value="<?= $g ?>"><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Titre *</label>
                        <input type="text" name="title" class="form-control" placeholder="Ex: WTS Zod Rune" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Décris ton item, ses stats, etc."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Prix en J *</label>
                        <div class="input-group">
                            <input type="number" name="price_fg" class="form-control" min="0" placeholder="0" required>
                            <span class="input-group-text text-warning fw-bold">J</span>
                        </div>
                        <small class="text-muted">Ton solde : <?= $_SESSION['user']['forum_gold'] ?? 0 ?> J</small>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Publier l'annonce</button>
                    <a href="/projet/trade/index.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
