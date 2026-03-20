<?php
require '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$action = $_POST['action'] ?? '';

if ($action === 'finish') {
    $bet = (int)($_POST['bet'] ?? 0);
    $result = $_POST['result'] ?? '';

    if ($bet <= 0 || !in_array($result, ['win', 'lose', 'push', 'blackjack'])) {
        echo json_encode(['error' => 'Paramètres invalides']);
        exit;
    }

    $user = $pdo->prepare("SELECT forum_gold FROM users WHERE id = ?");
    $user->execute([$userId]);
    $user = $user->fetch();

    if (!$user) {
        echo json_encode(['error' => 'Utilisateur introuvable']);
        exit;
    }

    $delta = 0;
    if ($result === 'win') $delta = $bet;
    elseif ($result === 'blackjack') $delta = (int)($bet * 1.5);
    elseif ($result === 'lose') $delta = -$bet;
    // push = 0

    if ($delta !== 0) {
        $pdo->prepare("UPDATE users SET forum_gold = forum_gold + ? WHERE id = ? AND forum_gold + ? >= 0")
            ->execute([$delta, $userId, $delta]);

        $reason = $delta > 0 ? "Blackjack: gain" : "Blackjack: perte";
        $pdo->prepare("INSERT INTO transactions (from_user_id, to_user_id, amount, reason) VALUES (?, ?, ?, ?)")
            ->execute([$delta > 0 ? null : $userId, $delta > 0 ? $userId : null, abs($delta), $reason]);
    }

    $newBalance = $pdo->prepare("SELECT forum_gold FROM users WHERE id = ?");
    $newBalance->execute([$userId]);
    $newBalance = (int)$newBalance->fetchColumn();

    $_SESSION['user']['forum_gold'] = $newBalance;

    echo json_encode(['balance' => $newBalance]);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
