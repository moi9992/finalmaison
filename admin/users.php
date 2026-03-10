<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /projet/index.php');
    exit;
}

// Bannir / débannir
if (isset($_GET['ban'])) {
    $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ? AND role != 'admin'")->execute([(int)$_GET['ban']]);
    header('Location: users.php');
    exit;
}
if (isset($_GET['unban'])) {
    $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?")->execute([(int)$_GET['unban']]);
    header('Location: users.php');
    exit;
}

// Changer le rôle
if (isset($_GET['set_role']) && isset($_GET['user_id'])) {
    $role   = in_array($_GET['set_role'], ['user', 'moderator', 'admin']) ? $_GET['set_role'] : 'user';
    $target = (int)$_GET['user_id'];
    if ($target !== $_SESSION['user']['id']) { // ne peut pas changer son propre rôle
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $target]);
    }
    header('Location: users.php');
    exit;
}

// Recherche
$search = trim($_GET['search'] ?? '');
$params = [];
$where  = '';
if ($search) {
    $where    = "WHERE username LIKE ? OR email LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$users = $pdo->prepare("
    SELECT u.*,
        (SELECT COUNT(*) FROM forum_topics WHERE user_id = u.id) AS nb_topics,
        (SELECT COUNT(*) FROM trades WHERE user_id = u.id) AS nb_trades
    FROM users u
    $where
    ORDER BY u.created_at DESC
");
$users->execute($params);
$users = $users->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion membres — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/projet/assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Gestion des membres</h2>
        <a href="/projet/admin/index.php" class="btn btn-outline-secondary btn-sm">← Retour admin</a>
    </div>

    <!-- Recherche -->
    <form method="GET" class="mb-4 d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Rechercher par pseudo ou email..."
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary px-4">Chercher</button>
        <?php if ($search): ?>
            <a href="users.php" class="btn btn-outline-secondary">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Pseudo</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th class="text-end">FG</th>
                    <th class="text-center">Topics</th>
                    <th class="text-center">Trades</th>
                    <th>Inscrit le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= $u['is_banned'] ? 'table-danger' : '' ?>">
                        <td><?= $u['id'] ?></td>
                        <td>
                            <a href="/projet/user/profile.php?id=<?= $u['id'] ?>" class="fw-bold text-decoration-none">
                                <?= htmlspecialchars($u['username']) ?>
                            </a>
                            <?php if ($u['is_banned']): ?>
                                <span class="badge bg-danger ms-1">Banni</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['user']['id']): ?>
                                <form method="GET" class="d-flex align-items-center gap-1">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="set_role" class="form-select form-select-sm" onchange="this.form.submit()" style="width:120px">
                                        <option value="user"      <?= $u['role'] === 'user'      ? 'selected' : '' ?>>user</option>
                                        <option value="moderator" <?= $u['role'] === 'moderator' ? 'selected' : '' ?>>moderator</option>
                                        <option value="admin"     <?= $u['role'] === 'admin'     ? 'selected' : '' ?>>admin</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-danger">admin (toi)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-warning fw-bold"><?= number_format($u['forum_gold']) ?></td>
                        <td class="text-center"><?= $u['nb_topics'] ?></td>
                        <td class="text-center"><?= $u['nb_trades'] ?></td>
                        <td class="text-muted small"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['user']['id'] && $u['role'] !== 'admin'): ?>
                                <div class="d-flex gap-1">
                                    <?php if ($u['is_banned']): ?>
                                        <a href="?unban=<?= $u['id'] ?>" class="btn btn-sm btn-success"
                                           onclick="return confirm('Débannir <?= htmlspecialchars($u['username']) ?> ?')">
                                            <i class="bi bi-unlock"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?ban=<?= $u['id'] ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Bannir <?= htmlspecialchars($u['username']) ?> ?')">
                                            <i class="bi bi-slash-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="/projet/admin/give_fg.php" class="btn btn-sm btn-warning" title="Donner des J">
                                        <i class="bi bi-coin"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted small"><?= count($users) ?> membre(s) trouvé(s)</p>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
