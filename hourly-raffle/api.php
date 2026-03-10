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

// Refresh user data from DB
function getUser($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT forum_gold, raffle_tickets FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

if ($action === 'get_state') {
    $user = getUser($pdo, $userId);
    echo json_encode(['balance' => (int)$user['forum_gold'], 'tickets' => (int)$user['raffle_tickets']]);
    exit;
}

if ($action === 'buy') {
    $type = $_POST['type'] ?? '';

    $packs = [
        'solo'       => ['qty' => 1,   'price' => 50],
        'pack10'     => ['qty' => 10,  'price' => 450],
        'whale'      => ['qty' => 100, 'price' => 4000],
        'promo_solo' => ['qty' => 11,  'price' => 500],
        'promo_pack' => ['qty' => 60,  'price' => 2250],
        'promo_whale'=> ['qty' => 400, 'price' => 12000],
    ];

    if (!isset($packs[$type])) {
        echo json_encode(['error' => 'Pack invalide']);
        exit;
    }

    $pack = $packs[$type];
    $user = getUser($pdo, $userId);

    if ($user['forum_gold'] < $pack['price']) {
        echo json_encode(['error' => 'Fonds insuffisants']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE users SET forum_gold = forum_gold - ?, raffle_tickets = raffle_tickets + ? WHERE id = ? AND forum_gold >= ?');
    $stmt->execute([$pack['price'], $pack['qty'], $userId, $pack['price']]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Fonds insuffisants']);
        exit;
    }

    // Log transaction
    $stmt = $pdo->prepare('INSERT INTO transactions (from_user_id, to_user_id, amount, reason) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $userId, $pack['price'], 'Achat raffle: ' . $type]);

    $user = getUser($pdo, $userId);
    $_SESSION['user']['forum_gold'] = $user['forum_gold'];

    echo json_encode(['balance' => (int)$user['forum_gold'], 'tickets' => (int)$user['raffle_tickets']]);
    exit;
}

if ($action === 'draw') {
    $qty = (int)($_POST['qty'] ?? 0);
    $picks = json_decode($_POST['picks'] ?? '[]', true);

    if ($qty <= 0 || $qty > 999 || !is_array($picks) || count($picks) !== 5) {
        echo json_encode(['error' => 'Paramètres invalides']);
        exit;
    }

    foreach ($picks as $p) {
        if (!is_int($p) || $p < 1 || $p > 9) {
            echo json_encode(['error' => 'Numéros invalides (1-9)']);
            exit;
        }
    }

    $user = getUser($pdo, $userId);
    if ($user['raffle_tickets'] < $qty) {
        echo json_encode(['error' => 'Pas assez de tickets']);
        exit;
    }

    // Deduct tickets
    $stmt = $pdo->prepare('UPDATE users SET raffle_tickets = raffle_tickets - ? WHERE id = ? AND raffle_tickets >= ?');
    $stmt->execute([$qty, $userId, $qty]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Pas assez de tickets']);
        exit;
    }

    // Generate winning code
    $target = [];
    for ($i = 0; $i < 5; $i++) $target[] = random_int(1, 9);

    // Run all draws
    $results = [];
    $totalGains = 0;

    for ($i = 0; $i < $qty; $i++) {
        $draw = [];
        for ($j = 0; $j < 5; $j++) $draw[] = random_int(1, 9);

        // Count matches on first 4 balls
        $m = 0;
        $tempD = array_slice($draw, 0, 4);
        foreach (array_slice($picks, 0, 4) as $n) {
            $idx = array_search($n, $tempD);
            if ($idx !== false) {
                $m++;
                array_splice($tempD, $idx, 1);
            }
        }

        // Payout
        if ($m === 4 && $draw[4] === $target[4]) $g = 25000;
        elseif ($m === 4) $g = 125;
        elseif ($m === 3) $g = 75;
        elseif ($m === 2) $g = 25;
        elseif ($m === 1) $g = 5;
        else $g = 0;

        $totalGains += $g;
        $results[] = ['draw' => $draw, 'gain' => $g];
    }

    // Credit winnings
    if ($totalGains > 0) {
        $stmt = $pdo->prepare('UPDATE users SET forum_gold = forum_gold + ? WHERE id = ?');
        $stmt->execute([$totalGains, $userId]);

        $stmt = $pdo->prepare('INSERT INTO transactions (from_user_id, to_user_id, amount, reason) VALUES (?, ?, ?, ?)');
        $stmt->execute([null, $userId, $totalGains, 'Gains raffle: ' . $qty . ' tirages']);
    }

    $user = getUser($pdo, $userId);
    $_SESSION['user']['forum_gold'] = $user['forum_gold'];

    echo json_encode([
        'balance' => (int)$user['forum_gold'],
        'tickets' => (int)$user['raffle_tickets'],
        'target'  => $target,
        'results' => $results,
        'totalGains' => $totalGains,
    ]);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
