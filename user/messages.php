<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']) {
    header('Location: /finalmaison-main/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user']['id'];

if (isset($_GET['read_notif'])) {
    $notifId = (int)$_GET['read_notif'];
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$notifId, $userId]);
    $notif = $pdo->prepare("SELECT type, reference_id FROM notifications WHERE id = ?");
    $notif->execute([$notifId]);
    $notif = $notif->fetch();
    if ($notif) {
        if ($notif['type'] === 'topic_reply') {
            header('Location: /finalmaison-main/forum/topic.php?id=' . $notif['reference_id']);
        } elseif ($notif['type'] === 'report') {
            header('Location: /finalmaison-main/admin/reports.php');
        } else {
            header('Location: /finalmaison-main/trade/trade.php?id=' . $notif['reference_id']);
        }
        exit;
    }
}

if (isset($_GET['read_all_notifs'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
    header('Location: messages.php');
    exit;
}

if (isset($_GET['delete_notif'])) {
    $notifId = (int)$_GET['delete_notif'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$notifId, $userId]);
    header('Location: messages.php');
    exit;
}

if (isset($_GET['delete_read_notifs'])) {
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1")->execute([$userId]);
    header('Location: messages.php');
    exit;
}

$notifications = $pdo->prepare("
    SELECT n.*, u.username AS from_username
    FROM notifications n
    JOIN users u ON n.from_user_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$notifications->execute([$userId]);
$notifications = $notifications->fetchAll();

$unreadNotifCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadNotifCount++;
}

$conversations = $pdo->prepare("
    SELECT
        m.id,
        m.content,
        m.created_at,
        m.from_user_id,
        m.to_user_id,
        u.username AS other_username,
        u.id AS other_id,
        u.avatar AS other_avatar,
        SUM(m2.is_read = 0 AND m2.to_user_id = :uid) AS unread_count
    FROM messages m
    JOIN users u ON u.id = CASE
        WHEN m.from_user_id = :uid2 THEN m.to_user_id
        ELSE m.from_user_id
    END
    JOIN messages m2 ON (
        (m2.from_user_id = u.id AND m2.to_user_id = :uid3) OR
        (m2.from_user_id = :uid4 AND m2.to_user_id = u.id)
    )
    WHERE (m.from_user_id = :uid5 OR m.to_user_id = :uid6)
    AND m.id = (
        SELECT MAX(m3.id) FROM messages m3
        WHERE (m3.from_user_id = :uid7 AND m3.to_user_id = u.id)
           OR (m3.from_user_id = u.id AND m3.to_user_id = :uid8)
    )
    GROUP BY u.id, m.id, m.content, m.created_at, m.from_user_id, m.to_user_id, u.username
    ORDER BY m.created_at DESC
");
$conversations->execute([
    ':uid'  => $userId, ':uid2' => $userId, ':uid3' => $userId,
    ':uid4' => $userId, ':uid5' => $userId, ':uid6' => $userId,
    ':uid7' => $userId, ':uid8' => $userId,
]);
$conversations = $conversations->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">
    <h2 class="fw-bold mb-4"><i class="bi bi-envelope me-2"></i>Messagerie</h2>

    <?php if (!empty($notifications)): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-bell me-2"></i>Notifications
                    <?php if ($unreadNotifCount > 0): ?>
                        <span class="badge bg-danger"><?= $unreadNotifCount ?></span>
                    <?php endif; ?>
                </h5>
                <div class="d-flex gap-2">
                    <?php if ($unreadNotifCount > 0): ?>
                        <a href="?read_all_notifs=1" class="btn btn-sm btn-outline-secondary">Tout marquer comme lu</a>
                    <?php endif; ?>
                    <?php if (count($notifications) - $unreadNotifCount > 0): ?>
                        <a href="?delete_read_notifs=1" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Supprimer toutes les notifications lues ?')">
                            <i class="bi bi-trash me-1"></i>Supprimer les lues
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="list-group">
                <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item py-3 <?= !$notif['is_read'] ? 'fw-bold' : '' ?> d-flex justify-content-between align-items-center">
                        <a href="?read_notif=<?= $notif['id'] ?>" class="text-decoration-none flex-grow-1">
                            <div>
                                <?php
                                $notifIcon = match($notif['type']) {
                                    'topic_reply' => 'bi-chat-dots',
                                    'report'      => 'bi-flag-fill text-warning',
                                    default       => 'bi-currency-exchange'
                                };
                                ?>
                                <i class="bi <?= $notifIcon ?> me-2"></i>
                                <?= htmlspecialchars($notif['message']) ?>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="badge bg-primary ms-1">Nouveau</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($notif['created_at'])) ?></small>
                        </a>
                        <?php if ($notif['is_read']): ?>
                            <a href="?delete_notif=<?= $notif['id'] ?>" class="btn btn-sm btn-outline-danger ms-2"
                               title="Supprimer" onclick="return confirm('Supprimer cette notification ?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <hr>
    <?php endif; ?>

    <h5 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-2"></i>Conversations</h5>

    <?php if (empty($conversations)): ?>
        <p class="text-muted">Aucune conversation pour le moment.</p>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($conversations as $conv): ?>
                <a href="/finalmaison-main/user/conversation.php?user_id=<?= $conv['other_id'] ?>"
                   class="list-group-item list-group-item-action py-3 <?= $conv['unread_count'] > 0 ? 'fw-bold' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if (!empty($conv['other_avatar'])): ?>
                                <img src="<?= htmlspecialchars($conv['other_avatar']) ?>" alt="avatar" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" class="me-2">
                            <?php else: ?>
                                <i class="bi bi-person-circle me-2"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($conv['other_username']) ?>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted fw-normal">
                                <?= htmlspecialchars(mb_strimwidth($conv['content'], 0, 60, '...')) ?>
                            </small>
                        </div>
                        <small class="text-muted"><?= date('d/m/Y à H:i', strtotime($conv['created_at'])) ?></small>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
