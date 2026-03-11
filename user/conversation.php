<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']) {
    header('Location: /projet/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId      = $_SESSION['user']['id'];
$userRole    = $_SESSION['user']['role'] ?? 'user';
$otherUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$isStaff     = in_array($userRole, ['moderator', 'admin']);

if (!$otherUserId || $otherUserId === $userId) {
    header('Location: messages.php');
    exit;
}

$otherUser = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$otherUser->execute([$otherUserId]);
$otherUser = $otherUser->fetch();

if (!$otherUser) {
    header('Location: messages.php');
    exit;
}

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Conversation');
    $sendAsStaff = $isStaff && isset($_POST['staff_message']);

    if (!empty($content)) {
        $pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, subject, content, is_staff_message) VALUES (?, ?, ?, ?, ?)")
            ->execute([$userId, $otherUserId, $subject, $content, $sendAsStaff ? 1 : 0]);
        header("Location: conversation.php?user_id=$otherUserId");
        exit;
    }
}

// Marquer les messages reçus comme lus
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0")
    ->execute([$otherUserId, $userId]);

// Charger tous les messages de la conversation
$messages = $pdo->prepare("
    SELECT m.*, u.username AS sender_name, u.role AS sender_role
    FROM messages m
    JOIN users u ON u.id = m.from_user_id
    WHERE (m.from_user_id = ? AND m.to_user_id = ?)
       OR (m.from_user_id = ? AND m.to_user_id = ?)
    ORDER BY m.created_at ASC
");
$messages->execute([$userId, $otherUserId, $otherUserId, $userId]);
$messages = $messages->fetchAll();

// Vérifier si l'user peut répondre :
// Un user normal ne peut pas répondre si le dernier message est un message staff envoyé PAR l'autre
$canReply = true;
if (!$isStaff && !empty($messages)) {
    $lastMsg = end($messages);
    if ($lastMsg['is_staff_message'] && $lastMsg['from_user_id'] != $userId) {
        $canReply = false;
    }
}

$otherIsStaff = in_array($otherUser['role'], ['moderator', 'admin']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation avec <?= htmlspecialchars($otherUser['username']) ?> — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .msg-me    { background: #0d6efd22; border-left: 3px solid #0d6efd; }
        .msg-other { background: #f8f9fa;   border-left: 3px solid #6c757d; }
        .msg-staff { background: #dc354522; border-left: 3px solid #dc3545; }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container my-5" style="max-width: 800px;">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projet/user/messages.php">Messagerie</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($otherUser['username']) ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-person-circle me-2"></i>
            <?= htmlspecialchars($otherUser['username']) ?>
            <?php if ($otherIsStaff): ?>
                <span class="badge <?= $otherUser['role'] === 'admin' ? 'bg-danger' : 'bg-warning text-dark' ?> ms-1"><?= $otherUser['role'] ?></span>
            <?php endif; ?>
        </h4>
        <a href="/projet/user/profile.php?id=<?= $otherUserId ?>" class="btn btn-sm btn-outline-secondary">
            Voir le profil
        </a>
    </div>

    <!-- Messages -->
    <div class="mb-4">
        <?php if (empty($messages)): ?>
            <p class="text-muted text-center">Commence la conversation !</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <?php
                $isMe = $msg['from_user_id'] == $userId;
                if ($msg['is_staff_message']) {
                    $msgClass = 'msg-staff';
                    $margin = $isMe ? 'ms-5' : 'me-5';
                } else {
                    $msgClass = $isMe ? 'msg-me ms-5' : 'msg-other me-5';
                    $margin = '';
                }
                ?>
                <div class="p-3 mb-2 rounded <?= $msgClass ?> <?= $margin ?>">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold small">
                            <?= htmlspecialchars($msg['sender_name']) ?>
                            <?php if ($msg['is_staff_message']): ?>
                                <span class="badge bg-danger ms-1"><i class="bi bi-shield-fill me-1"></i>Message officiel</span>
                            <?php endif; ?>
                        </span>
                        <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($msg['created_at'])) ?></small>
                    </div>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Formulaire de réponse -->
    <?php if ($canReply): ?>
        <div class="card">
            <div class="card-header fw-bold">Répondre</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="subject" value="Conversation">
                    <div class="mb-3">
                        <textarea name="content" class="form-control" rows="4"
                                  placeholder="Ton message..." required></textarea>
                    </div>
                    <?php if ($isStaff): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="staff_message" id="staffMsg" value="1">
                            <label class="form-check-label text-danger fw-bold" for="staffMsg">
                                <i class="bi bi-shield-fill me-1"></i>Envoyer comme message officiel
                                <small class="text-muted fw-normal d-block">L'utilisateur ne pourra pas répondre et ne recevra pas de notification</small>
                            </label>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Envoyer
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">
            <i class="bi bi-lock me-2"></i>Vous ne pouvez pas répondre à cette conversation.
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
