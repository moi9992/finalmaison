<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /finalmaison-main/index.php');
    exit;
}

$nbUsers  = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nbTrades = $pdo->query("SELECT COUNT(*) FROM trades")->fetchColumn();
$nbTopics = $pdo->query("SELECT COUNT(*) FROM forum_topics")->fetchColumn();
$nbPosts  = $pdo->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();

$lastUsers = $pdo->query("SELECT id, username, email, role, forum_gold, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/finalmaison-main/assets/css/style.css">
</head>
<body class="bg-dark text-white">

<?php include '../includes/header.php'; ?>

<div class="main">

<div class="container my-5">
    <h2 class="fw-bold mb-4"><i class="bi bi-shield-lock me-2 text-danger"></i>Panel Admin</h2>

    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card bg-primary text-white text-center p-3">
                <h3 class="fw-bold"><?= $nbUsers ?></h3>
                <div><i class="bi bi-people me-1"></i>Membres</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white text-center p-3">
                <h3 class="fw-bold"><?= $nbTrades ?></h3>
                <div><i class="bi bi-tags me-1"></i>Annonces</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark text-center p-3">
                <h3 class="fw-bold"><?= $nbTopics ?></h3>
                <div><i class="bi bi-chat-dots me-1"></i>Topics</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white text-center p-3">
                <h3 class="fw-bold"><?= $nbPosts ?></h3>
                <div><i class="bi bi-chat-left-text me-1"></i>Messages</div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-3">Actions rapides</h5>
    <div class="d-flex gap-2 mb-5">
        <a href="/finalmaison-main/admin/give_fg.php" class="btn btn-warning">
            <i class="bi bi-coin me-1"></i>Donner des FG
        </a>
        <a href="/finalmaison-main/admin/users.php" class="btn btn-primary">
            <i class="bi bi-people me-1"></i>Gérer les membres
        </a>
        <a href="/finalmaison-main/admin/categories.php" class="btn btn-info">
            <i class="bi bi-folder-plus me-1"></i>Catégories Forum
        </a>
        <a href="/finalmaison-main/admin/reports.php" class="btn btn-outline-danger">
            <i class="bi bi-flag me-1"></i>Signalements
        </a>
        <a href="/finalmaison-main/admin/messages.php" class="btn btn-outline-light">
            <i class="bi bi-eye me-1"></i>Surveillance MP
        </a>
    </div>

    <h5 class="fw-bold mb-3">Derniers inscrits</h5>
    <div class="table-responsive">
        <table class="table table-dark table-striped table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pseudo</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th class="text-end">FG</th>
                    <th>Inscrit le</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lastUsers as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php
                            $roleBadge = match($u['role']) {
                                'admin'     => 'bg-danger',
                                'moderator' => 'bg-warning text-dark',
                                default     => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $roleBadge ?>"><?= $u['role'] ?></span>
                        </td>
                        <td class="text-end text-warning fw-bold"><?= number_format($u['forum_gold']) ?></td>
                        <td class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
