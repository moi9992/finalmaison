<?php
require '../config/db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $email = $_POST['email'];
        $mdp   = $_POST['mdp'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $utilisateur = $stmt->fetch();

        if ($utilisateur) {
            if (password_verify($mdp, $utilisateur['password'])) {
                $_SESSION['user'] = [
                    'id'    => $utilisateur['id'],
                    'login' => $utilisateur['username'],
                    'role'  => $utilisateur['role'],
                    'forum_gold' => $utilisateur['forum_gold'],
                ];
                $_SESSION['isLog'] = true;
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/finalmaison-main/index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $message = '<div class="alert alert-danger text-center mt-3">Mot de passe incorrect.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger text-center mt-3">Utilisateur non trouvé.</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-warning text-center mt-3">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container main-content">
    <div class="form-container border p-4 rounded bg-light shadow">
        <h2 class="mb-4">Connexion</h2>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="alert alert-success">Inscription réussie ! Tu peux te connecter.</div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label fs-5">E-mail</label>
                <input type="email" name="email" class="form-control form-control-lg border border-dark" placeholder="Entrez votre e-mail" required>
            </div>
            <div class="mb-4">
                <label class="form-label fs-5">Mot de passe</label>
                <input type="password" name="mdp" class="form-control form-control-lg border border-dark" placeholder="Entrez votre mot de passe" required>
            </div>
            <div class="d-grid gap-2 col-6 mx-auto mt-4">
                <button type="submit" class="btn btn-primary border rounded">Valider</button>
                <a href="register.php" class="btn btn-secondary border rounded">S'inscrire</a>
            </div>
        </form>

        <?php if (!empty($message)): ?>
            <?= $message ?>
        <?php endif; ?>

    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
