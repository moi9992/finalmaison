<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || !$_SESSION['isLog']) {
    header('Location: /finalmaison-main/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$category = $pdo->prepare("SELECT * FROM forum_categories WHERE id = ?");
$category->execute([$category_id]);
$category = $category->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!empty($title) && !empty($content)) {
        $pdo->prepare("INSERT INTO forum_topics (category_id, user_id, title) VALUES (?, ?, ?)")
            ->execute([$category_id, $_SESSION['user']['id'], $title]);

        $topic_id = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$topic_id, $_SESSION['user']['id'], $content]);

        header("Location: /finalmaison-main/forum/topic.php?id=$topic_id");
        exit;
    } else {
        $error = "Le titre et le contenu sont obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau topic — D2JSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="main">
<div class="container my-5">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/finalmaison-main/forum/index.php">Forum</a></li>
            <li class="breadcrumb-item"><a href="/finalmaison-main/forum/category.php?id=<?= $category_id ?>"><?= htmlspecialchars($category['name']) ?></a></li>
            <li class="breadcrumb-item active">Nouveau topic</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header fw-bold fs-5">
            <i class="bi bi-plus-lg me-2"></i>Créer un topic dans "<?= htmlspecialchars($category['name']) ?>"
        </div>
        <div class="card-body">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-bold">Titre</label>
                    <input type="text" name="title" class="form-control" placeholder="Titre du topic" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Message</label>
                    <textarea name="content" class="form-control" rows="8" placeholder="Contenu du topic..." required></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Publier</button>
                    <a href="/finalmaison-main/forum/category.php?id=<?= $category_id ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>

        </div>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
