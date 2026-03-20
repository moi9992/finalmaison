<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || $_SESSION['user']['role'] !== 'admin') {
    die('Accès refusé.');
}

$users = $pdo->query("SELECT id, username, forum_gold FROM users ORDER BY username")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $amount  = (int)$_POST['amount'];

    if ($user_id > 0 && $amount > 0) {
        $pdo->prepare("UPDATE users SET forum_gold = forum_gold + ? WHERE id = ?")->execute([$amount, $user_id]);
        $pdo->prepare("INSERT INTO transactions (from_user_id, to_user_id, amount, reason) VALUES (NULL, ?, ?, 'Don admin')")->execute([$user_id, $amount]);
        $success = "Julietons ajoutés avec succès !";
        $users = $pdo->query("SELECT id, username, forum_gold FROM users ORDER BY username")->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donner des Julietons — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/finalmaison-main/assets/css/style.css">
</head>
<body class="bg-dark text-white">

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container py-5" style="max-width: 500px;">
    <h3 class="fw-bold mb-4"><i class="bi bi-coin text-warning me-2"></i>Donner des Julietons</h3>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card bg-secondary text-white mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">Compte</label>
                    <select name="user_id" class="form-select text-dark" required>
                        <option value="">-- Choisir un compte --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['username']) ?> (<?= number_format($u['forum_gold']) ?> J)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Montant à ajouter</label>
                    <div class="input-group">
                        <input type="number" name="amount" class="form-control" min="1" value="1000000" required>
                        <span class="input-group-text text-warning fw-bold">J</span>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold">
                    <i class="bi bi-plus-circle me-1"></i>Ajouter les Julietons
                </button>
            </form>
        </div>
    </div>

    <h5 class="fw-bold mb-3">Soldes actuels</h5>
    <table class="table table-dark table-striped">
        <thead>
            <tr>
                <th>Pseudo</th>
                <th class="text-end">J</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td class="text-end text-warning fw-bold"><?= number_format($u['forum_gold']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="/finalmaison-main/admin/index.php" class="btn btn-outline-light btn-sm">← Retour admin</a>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
