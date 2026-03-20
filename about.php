<?php
require 'config/db.php';
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos & Règlement — HYPERBUSINESS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="main">
<div class="container my-5" style="max-width: 860px;">

    <!-- À propos -->
    <div class="card mb-4">
        <div class="card-header fw-bold fs-5">
            <i class="bi bi-info-circle me-2"></i>À propos de HYPERBUSINESS
        </div>
        <div class="card-body">
            <p>
                <strong>HYPERBUSINESS</strong> est une plateforme communautaire dédiée aux joueurs de jeux vidéo en quête d'échanges, de discussions et de bonnes affaires.
                Inspiré de d2jsp, le site repose sur une monnaie virtuelle — le <strong>Julienton (J)</strong> — utilisée pour acheter, vendre et négocier des items de jeu entre membres.
            </p>
            <p>
                Tu peux y trouver :
            </p>
            <ul>
                <li><i class="bi bi-chat-dots me-2 text-info"></i><strong>Un forum</strong> pour discuter, poser des questions et partager avec la communauté</li>
                <li><i class="bi bi-arrow-left-right me-2 text-warning"></i><strong>Un espace trading</strong> pour vendre ou acheter des items contre des J</li>
                <li><i class="bi bi-envelope me-2 text-success"></i><strong>Une messagerie privée</strong> pour contacter directement un membre</li>
                <li><i class="bi bi-dice-5 me-2 text-danger"></i><strong>Des mini-jeux</strong> (Blackjack, Hourly Raffle) pour tenter de multiplier tes J</li>
            </ul>
            <p class="mb-0 text-muted fst-italic">
                HYPERBUSINESS est un projet de fin d'année — les J n'ont aucune valeur réelle et les transactions restent fictives.
            </p>
        </div>
    </div>

    <!-- Règlement -->
    <div class="card">
        <div class="card-header fw-bold fs-5">
            <i class="bi bi-shield-check me-2"></i>Règlement
        </div>
        <div class="card-body">

            <h6 class="fw-bold text-info mt-2"><i class="bi bi-people me-2"></i>Comportement</h6>
            <ul>
                <li>Respecte les autres membres — insultes, harcèlement et discriminations sont interdits.</li>
                <li>Toute tentative d'intimidation ou de manipulation sera sanctionnée.</li>
                <li>Les faux comptes et le multi-compte sont interdits.</li>
            </ul>

            <h6 class="fw-bold text-warning mt-3"><i class="bi bi-arrow-left-right me-2"></i>Trading</h6>
            <ul>
                <li>Les arnaques (scam) sont strictement interdites et entraînent un ban permanent.</li>
                <li>Les annonces doivent être honnêtes et complètes — pas de prix abusifs intentionnels.</li>
                <li>Une fois une offre acceptée, l'échange est considéré comme finalisé.</li>
                <li>En cas de litige, contacte un modérateur via la messagerie.</li>
            </ul>

            <h6 class="fw-bold text-danger mt-3"><i class="bi bi-exclamation-triangle me-2"></i>Forum & Messages</h6>
            <ul>
                <li>Le spam, flood et contenu répétitif sont interdits.</li>
                <li>Pas de publicité pour des sites externes ou des services tiers.</li>
                <li>Le contenu illégal, pornographique ou choquant est interdit.</li>
                <li>Les messages privés ne doivent pas être utilisés pour harceler un membre.</li>
                <li>Respecte le thème de chaque section du forum — un hors-sujet répété (ex: parler de Candy Crush dans un forum Dofus) peut entraîner une suspension.</li>
            </ul>

            <h6 class="fw-bold text-success mt-3"><i class="bi bi-hammer me-2"></i>Sanctions</h6>
            <ul>
                <li><strong>Avertissement</strong> — pour les infractions mineures (premier écart).</li>
                <li><strong>Suspension temporaire</strong> — pour les infractions répétées.</li>
                <li><strong>Ban permanent</strong> — pour les cas graves (scam, harcèlement, contenu illégal).</li>
            </ul>

            <div class="alert alert-secondary mt-4 mb-0 text-white">
                <i class="bi bi-info-circle me-2"></i>
                Le règlement peut être modifié à tout moment. En utilisant le site, tu acceptes de le respecter.
                En cas de doute, contacte un <strong>modérateur</strong> ou un <strong>administrateur</strong>.
            </div>

        </div>
    </div>

</div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
