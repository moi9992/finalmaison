<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']) {
    http_response_code(401);
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

require __DIR__ . '/../config/db.php';

$stmt = $pdo->prepare("SELECT forum_gold FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$gold = $stmt->fetchColumn();

$_SESSION['user']['forum_gold'] = $gold;

echo json_encode(['forum_gold' => (int)$gold]);
