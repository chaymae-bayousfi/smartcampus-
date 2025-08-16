<?php
require_once 'config/database.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCampus+ - Gestion intelligente de la vie étudiante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>SmartCampus+
            </a>
            
            <?php if (isLoggedIn()): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door me-1"></i>Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php"><i class="bi bi-calendar3 me-1"></i>Emploi du temps</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="budget.php"><i class="bi bi-wallet2 me-1"></i>Budget</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study-groups.php"><i class="bi bi-people me-1"></i>Groupes d'étude</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="forum.php"><i class="bi bi-chat-dots me-1"></i>Forum</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php"><i class="bi bi-calendar-event me-1"></i>Événements</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?= escape($currentUser['first_name']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Mon profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Déconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>