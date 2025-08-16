<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <?php if (!isLoggedIn()): ?>
    <!-- Page d'accueil pour les non-connectés -->
    <div class="hero-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-primary mb-4">
                        SmartCampus+
                        <span class="d-block text-secondary fs-3">Gestion intelligente de la vie étudiante</span>
                    </h1>
                    <p class="lead mb-4">
                        Centralisez votre emploi du temps, gérez votre budget, trouvez des groupes d'étude 
                        et restez connecté avec votre communauté étudiante.
                    </p>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="register.php" class="btn btn-primary btn-lg px-4">Commencer gratuitement</a>
                        <a href="login.php" class="btn btn-outline-primary btn-lg px-4">Se connecter</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-calendar3 fs-1 text-primary mb-3"></i>
                                    <h5>Emploi du temps</h5>
                                    <p class="text-muted">Organisez vos cours et ne manquez jamais une deadline</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-wallet2 fs-1 text-success mb-3"></i>
                                    <h5>Gestion budgétaire</h5>
                                    <p class="text-muted">Suivez vos dépenses et maîtrisez votre budget étudiant</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-people fs-1 text-warning mb-3"></i>
                                    <h5>Groupes d'étude</h5>
                                    <p class="text-muted">Matching intelligent pour former des groupes efficaces</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-chat-dots fs-1 text-info mb-3"></i>
                                    <h5>Communauté</h5>
                                    <p class="text-muted">Forum et événements pour rester connecté</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section fonctionnalités -->
    <div class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Fonctionnalités clés</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-bell fs-2"></i>
                        </div>
                        <h4 class="mt-3">Alertes intelligentes</h4>
                        <p class="text-muted">Recevez des notifications pour vos devoirs, examens et événements importants.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-graph-up fs-2"></i>
                        </div>
                        <h4 class="mt-3">Suivi budgétaire</h4>
                        <p class="text-muted">Analysez vos dépenses par catégorie et optimisez votre budget mensuel.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-robot fs-2"></i>
                        </div>
                        <h4 class="mt-3">Matching IA</h4>
                        <p class="text-muted">Algorithme intelligent pour former des groupes d'étude optimaux.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Redirection vers le dashboard si connecté -->
    <script>window.location.href = 'dashboard.php';</script>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>