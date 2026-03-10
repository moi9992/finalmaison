<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_unreadMessages = 0;
if (isset($_SESSION['isLog']) && $_SESSION['isLog']) {
    global $pdo;
    if (isset($pdo)) {
        $fresh = $pdo->prepare("SELECT forum_gold, role FROM users WHERE id = ?");
        $fresh->execute([$_SESSION['user']['id']]);
        $fresh = $fresh->fetch();
        if ($fresh) {
            $_SESSION['user']['forum_gold'] = $fresh['forum_gold'];
            $_SESSION['user']['role']       = $fresh['role'];
        }
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0");
        $unreadStmt->execute([$_SESSION['user']['id']]);
        $_unreadMessages = $unreadStmt->fetchColumn();

        $unreadNotifs = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unreadNotifs->execute([$_SESSION['user']['id']]);
        $_unreadMessages += $unreadNotifs->fetchColumn();
    }
}
?>
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="/projet/index.php">HYPERBUSINESS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/projet/index.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/projet/forum/index.php">Forum</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/projet/trade/index.php">Trading</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/projet/hourly-raffle/index.php">Hourly Raffle</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/projet/black-jack/blackjack.php">Blackjack</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
                    <li class="nav-item">
                        <span class="nav-link" style="font-family:'JetBrains Mono',monospace; color: var(--gold);">
                            <i class="bi bi-coin"></i>
                            <span id="header-balance"><?= number_format($_SESSION['user']['forum_gold'] ?? 0) ?></span> J
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="/projet/user/messages.php">
                            <i class="bi bi-envelope"></i>
                            <?php if ($_unreadMessages > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $_unreadMessages ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/projet/user/profile.php">
                            <i class="bi bi-person-circle"></i>
                            <?= htmlspecialchars($_SESSION['user']['login']) ?>
                        </a>
                    </li>
                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" style="color: var(--red) !important;" href="/projet/admin/index.php">Admin</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/projet/auth/logout.php">Déconnexion</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/projet/auth/login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary px-3 ms-2" href="/projet/auth/register.php">Inscription</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php if (isset($_SESSION['isLog']) && $_SESSION['isLog']): ?>
<script>
function updateBalance() {
    fetch('/projet/api/get_balance.php')
        .then(r => r.json())
        .then(data => {
            if (data.forum_gold !== undefined) {
                document.getElementById('header-balance').textContent =
                    Number(data.forum_gold).toLocaleString();
            }
        });
}
</script>
<?php endif; ?>
