<?php
require '../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($login) && !empty($email) && !empty($password)) {

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $login]);
        $userExists = $stmt->fetchColumn();

        if ($userExists > 0) {
            $status = 'utilisateur_exist';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, forum_gold) VALUES (?, ?, ?, 'user', 1000)");
                $stmt->execute([$login, $email, password_hash($password, PASSWORD_DEFAULT)]);
                header('Location: login.php?status=success');
                exit;
            } catch (PDOException $e) {
                $status = 'db_error';
            }
        }
    } else {
        $status = 'missing_fields';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container main-content">
    <div class="form-container border p-4 rounded bg-light shadow">
        <h2 class="mb-4">Inscription</h2>

        <?php
        if (isset($status)) {
            $alerts = [
                'utilisateur_exist' => ['danger',  "Email ou identifiant déjà utilisé."],
                'db_error'          => ['danger',  "Erreur lors de l'inscription, réessaie."],
                'missing_fields'    => ['warning', "Merci de remplir tous les champs."],
            ];
            if (isset($alerts[$status])) {
                [$type, $msg] = $alerts[$status];
                echo "<div class=\"alert alert-$type\">$msg</div>";
            }
        }
        if (isset($_GET['status']) && $_GET['status'] === 'success') {
            echo '<div class="alert alert-success">Inscription réussie ! Tu peux te connecter.</div>';
        }
        ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label fs-5">Identifiant</label>
                <input name="login" type="text" class="form-control form-control-lg border border-dark" placeholder="Identifiant" required>
            </div>
            <div class="mb-4">
                <label class="form-label fs-5">E-mail</label>
                <input name="email" type="email" class="form-control form-control-lg border border-dark" placeholder="E-mail" required>
            </div>
            <div class="mb-4">
                <label class="form-label fs-5">Mot de passe</label>
                <input name="password" type="password" class="form-control form-control-lg border border-dark" placeholder="Mot de passe" required>
            </div>
            <div class="d-grid gap-2 col-6 mx-auto mt-4">
                <button type="submit" class="btn btn-primary border rounded">S'inscrire</button>
                <a href="login.php" class="btn btn-secondary border rounded">Déjà un compte ?</a>
            </div>
        </form>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>

</html>
