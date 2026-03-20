<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog'] || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /finalmaison-main/index.php');
    exit;
}

$search = trim($_GET['search'] ?? '');

// Lister toutes les conversations (paires d'users) avec le dernier message
$searchSQL = '';
$params = [];
if ($search) {
    $searchSQL = "HAVING u1.username LIKE ? OR u2.username LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$conversations = $pdo->prepare("
    SELECT
        LEAST(m.from_user_id, m.to_user_id) AS user_a,
        GREATEST(m.from_user_id, m.to_user_id) AS user_b,
        u1.username AS username_a,
        u2.username AS username_b,
        MAX(m.id) AS last_msg_id,
        MAX(m.created_at) AS last_msg_date,
        COUNT(*) AS total_messages,
        (SELECT content FROM messages WHERE id = MAX(m.id)) AS last_content
    FROM messages m
    JOIN users u1 ON u1.id = LEAST(m.from_user_id, m.to_user_id)
    JOIN users u2 ON u2.id = GREATEST(m.from_user_id, m.to_user_id)
    GROUP BY user_a, user_b, u1.username, u2.username
    $searchSQL
    ORDER BY last_msg_date DESC
    LIMIT 50
");
$conversations->execute($params);
$conversations = $conversations->fetchAll();

// Voir une conversation spécifique
$viewUserA = isset($_GET['a']) ? (int)$_GET['a'] : 0;
$viewUserB = isset($_GET['b']) ? (int)$_GET['b'] : 0;
$viewMessages = [];
$viewUsers = [];

if ($viewUserA && $viewUserB) {
    $viewMessages = $pdo->prepare("
        SELECT m.*, u.username AS sender_name, u.role AS sender_role
        FROM messages m
        JOIN users u ON u.id = m.from_user_id
        WHERE (m.from_user_id = ? AND m.to_user_id = ?)
           OR (m.from_user_id = ? AND m.to_user_id = ?)
        ORDER BY m.created_at ASC
    ");
    $viewMessages->execute([$viewUserA, $viewUserB, $viewUserB, $viewUserA]);
    $viewMessages = $viewMessages->fetchAll();

    $uA = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $uA->execute([$viewUserA]);
    $viewUsers['a'] = $uA->fetch();

    $uB = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $uB->execute([$viewUserB]);
    $viewUsers['b'] = $uB->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance MP — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="bi bi-eye me-2"></i>Surveillance des MP</h2>
        <a href="/finalmaison-main/admin/index.php" class="btn btn-outline-secondary btn-sm">Retour admin</a>
    </div>

    <?php if ($viewUserA && $viewUserB && !empty($viewUsers)): ?>
        <!-- Vue d'une conversation -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="messages.php">Toutes les conversations</a></li>
                <li class="breadcrumb-item active">
                    <?= htmlspecialchars($viewUsers['a']['username'] ?? '?') ?> & <?= htmlspecialchars($viewUsers['b']['username'] ?? '?') ?>
                </li>
            </ol>
        </nav>

        <div class="card mb-4">
            <div class="card-header fw-bold d-flex justify-content-between">
                <span>
                    <a href="/finalmaison-main/user/profile.php?id=<?= $viewUserA ?>"><?= htmlspecialchars($viewUsers['a']['username']) ?></a>
                    <i class="bi bi-arrows-expand mx-2"></i>
                    <a href="/finalmaison-main/user/profile.php?id=<?= $viewUserB ?>"><?= htmlspecialchars($viewUsers['b']['username']) ?></a>
                </span>
                <span class="text-muted"><?= count($viewMessages) ?> messages</span>
            </div>
            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <?php foreach ($viewMessages as $msg): ?>
                    <?php
                    $isA = $msg['from_user_id'] == $viewUserA;
                    $msgClass = $msg['is_staff_message'] ? 'msg-staff-view' : ($isA ? 'msg-a' : 'msg-b');
                    ?>
                    <div class="p-2 mb-2 rounded <?= $msgClass ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold small">
                                <?= htmlspecialchars($msg['sender_name']) ?>
                                <?php if ($msg['is_staff_message']): ?>
                                    <span class="badge bg-danger ms-1">Officiel</span>
                                <?php endif; ?>
                                <?php
                                $roleBadge = match($msg['sender_role']) {
                                    'admin'     => '<span class="badge bg-danger ms-1">admin</span>',
                                    'moderator' => '<span class="badge bg-warning text-dark ms-1">modo</span>',
                                    default     => ''
                                };
                                echo $roleBadge;
                                ?>
                            </span>
                            <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($msg['created_at'])) ?></small>
                        </div>
                        <p class="mb-0 small"><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Liste de toutes les conversations -->
        <form method="GET" class="mb-4">
            <div class="input-group" style="max-width: 400px;">
                <input type="text" name="search" class="form-control" placeholder="Rechercher un utilisateur..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </form>

        <?php if (empty($conversations)): ?>
            <div class="alert alert-info">Aucune conversation trouvée.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($conversations as $conv): ?>
                    <a href="?a=<?= $conv['user_a'] ?>&b=<?= $conv['user_b'] ?>"
                       class="list-group-item list-group-item-action py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-chat-left-text me-2"></i>
                                <strong><?= htmlspecialchars($conv['username_a']) ?></strong>
                                <i class="bi bi-arrows-expand mx-1 text-muted"></i>
                                <strong><?= htmlspecialchars($conv['username_b']) ?></strong>
                                <span class="badge bg-secondary ms-2"><?= $conv['total_messages'] ?> msg</span>
                            </div>
                            <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($conv['last_msg_date'])) ?></small>
                        </div>
                        <small class="text-muted d-block mt-1">
                            <?= htmlspecialchars(mb_strimwidth($conv['last_content'], 0, 80, '...')) ?>
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
