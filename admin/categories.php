<?php
require '../config/db.php';
session_start();

if (!isset($_SESSION['isLog']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /finalmaison-main/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $slug = preg_replace('/[^a-z0-9\-]/', '', str_replace(' ', '-', strtolower($name)));

    if ($name === '' || $slug === '') {
        $error = "Le nom est obligatoire.";
    } else {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM forum_categories WHERE slug = ?");
        $exists->execute([$slug]);
        if ($exists->fetchColumn() > 0) {
            $error = "Une catégorie avec ce slug existe déjà.";
        } else {
            $maxOrder = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM forum_categories")->fetchColumn();
            $pdo->prepare("INSERT INTO forum_categories (name, description, slug, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$name, $description ?: null, $slug, $maxOrder + 1]);
            $success = "Catégorie \"$name\" ajoutée !";
        }
    }
}

if (isset($_GET['delete'])) {
    $catId = (int)$_GET['delete'];
    $topicCount = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE category_id = ?");
    $topicCount->execute([$catId]);
    if ($topicCount->fetchColumn() > 0) {
        $error = "Impossible de supprimer : cette catégorie contient des topics.";
    } else {
        $pdo->prepare("DELETE FROM forum_categories WHERE id = ?")->execute([$catId]);
        $success = "Catégorie supprimée.";
    }
}

$categories = $pdo->query("
    SELECT fc.*, COUNT(ft.id) AS nb_topics
    FROM forum_categories fc
    LEFT JOIN forum_topics ft ON ft.category_id = fc.id
    GROUP BY fc.id
    ORDER BY fc.sort_order
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories Forum — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/finalmaison-main/assets/css/style.css">
</head>
<body class="bg-dark text-white">

<?php include '../includes/header.php'; ?>

<div class="main">
<div class="container py-5" style="max-width: 700px;">
    <h3 class="fw-bold mb-4"><i class="bi bi-folder-plus text-primary me-2"></i>Catégories du Forum</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card bg-secondary text-white mb-4">
        <div class="card-header fw-bold">Ajouter une catégorie</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nom</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Dofus, Valorant..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description (optionnel)</label>
                    <input type="text" name="description" class="form-control" placeholder="Ex: Échanges et discussions Dofus">
                </div>
                <button type="submit" name="add" class="btn btn-primary w-100 fw-bold">
                    <i class="bi bi-plus-circle me-1"></i>Ajouter
                </button>
            </form>
        </div>
    </div>

    <h5 class="fw-bold mb-3">Catégories existantes</h5>
    <table class="table table-dark table-striped">
        <thead>
            <tr>
                <th>Ordre</th>
                <th>Nom</th>
                <th>Topics</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="text-muted"><?= $cat['sort_order'] ?></td>
                    <td>
                        <span class="fw-bold"><?= htmlspecialchars($cat['name']) ?></span>
                        <?php if ($cat['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($cat['description']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $cat['nb_topics'] ?></td>
                    <td class="text-end">
                        <?php if ($cat['nb_topics'] == 0): ?>
                            <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Supprimer la catégorie \"<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>\" ?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted" title="Contient des topics"><i class="bi bi-lock"></i></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="/finalmaison-main/admin/index.php" class="btn btn-outline-light btn-sm mt-2">← Retour admin</a>
</div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
