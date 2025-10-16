<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];

// Statistiques pour le dashboard
$stats = [];

// Nombre de devoirs en cours
$query = "SELECT COUNT(*) as count FROM assignments WHERE user_id = ? AND status = 'pending' AND due_date >= CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['pending_assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Dépenses du mois actuel
$query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = ? AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['monthly_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Budget du mois actuel
$query = "SELECT total_budget FROM budgets WHERE user_id = ? AND month_year = DATE_FORMAT(CURDATE(), '%Y-%m')";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['monthly_budget'] = $budget ? $budget['total_budget'] : 0;

// Groupes d'étude actifs
$query = "SELECT COUNT(*) as count FROM group_members gm JOIN study_groups sg ON gm.group_id = sg.id WHERE gm.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['active_groups'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Prochains devoirs
$query = "SELECT * FROM assignments WHERE user_id = ? AND status = 'pending' AND due_date >= CURDATE() ORDER BY due_date LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$upcoming_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cours aujourd'hui
$today = date('l'); // Nom du jour en anglais
$query = "SELECT * FROM schedules WHERE user_id = ? AND day_of_week = ? ORDER BY start_time";
$stmt = $db->prepare($query);
$stmt->execute([$userId, $today]);
$today_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dernières dépenses
$query = "SELECT * FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$recent_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-house-door me-2"></i>Tableau de bord
                <small class="text-muted">Bonjour <?= escape($_SESSION['user_name']) ?> !</small>
            </h1>
        </div>
    </div>
    
    <!-- Statistiques principales -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <h3 id="stat-pending-assignments"><?= $stats['pending_assignments'] ?></h3>
                <p><i class="bi bi-clipboard-check me-2"></i>Devoirs en cours</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #198754, #20c997);">
                <h3><?= number_format($stats['monthly_expenses'], 0, ',', ' ') ?>DH</h3>
                <p><i class="bi bi-wallet2 me-2"></i>Dépenses ce mois</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #fd7e14, #ffc107);">
                <h3><?= number_format($stats['monthly_budget'], 0, ',', ' ') ?>DH</h3>
                <p><i class="bi bi-piggy-bank me-2"></i>Budget mensuel</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1, #e0aaff);">
                <h3><?= $stats['active_groups'] ?></h3>
                <p><i class="bi bi-people me-2"></i>Groupes d'étude</p>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Emploi du temps aujourd'hui -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-day me-2"></i>Cours aujourd'hui</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($today_classes)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
                            <p class="text-muted">Aucun cours prévu aujourd'hui</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($today_classes as $class): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= escape($class['subject']) ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= date('H:i', strtotime($class['start_time'])) ?> - 
                                                <?= date('H:i', strtotime($class['end_time'])) ?>
                                                <?php if ($class['room']): ?>
                                                    <i class="bi bi-geo-alt ms-2 me-1"></i><?= escape($class['room']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-primary">
                                            <?php
                                            $now = time();
                                            $start = strtotime($class['start_time']);
                                            $end = strtotime($class['end_time']);
                                            if ($now < $start) echo 'À venir';
                                            elseif ($now >= $start && $now <= $end) echo 'En cours';
                                            else echo 'Terminé';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Prochains devoirs -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Prochains devoirs</h5>
                    <a href="schedule.php#assignments" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_assignments)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle fs-1 text-success mb-3"></i>
                            <p class="text-muted">Aucun devoir en attente !</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcoming_assignments as $assignment): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= escape($assignment['title']) ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-book me-1"></i><?= escape($assignment['subject']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block">
                                                <?= date('d/m/Y', strtotime($assignment['due_date'])) ?>
                                            </small>
                                            <?php
                                            $days_left = floor((strtotime($assignment['due_date']) - time()) / (60*60*24));
                                            $badge_class = $days_left <= 1 ? 'danger' : ($days_left <= 3 ? 'warning' : 'success');
                                            ?>
                                            <span class="badge bg-<?= $badge_class ?>">
                                                <?= $days_left <= 0 ? 'Aujourd\'hui' : $days_left . ' jour' . ($days_left > 1 ? 's' : '') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Aperçu budget -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Budget du mois</h5>
                    <a href="budget.php" class="btn btn-sm btn-outline-success">Gérer</a>
                </div>
                <div class="card-body">
                    <?php if ($stats['monthly_budget'] > 0): ?>
                        <?php
                        $percentage = ($stats['monthly_expenses'] / $stats['monthly_budget']) * 100;
                        $progress_class = $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success');
                        ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Dépensé</span>
                            <span><?= number_format($stats['monthly_expenses'], 2, ',', ' ') ?>DH / <?= number_format($stats['monthly_budget'], 0, ',', ' ') ?>DH</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar bg-<?= $progress_class ?>" style="width: <?= min($percentage, 100) ?>%"></div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h6 class="text-<?= $progress_class ?>"><?= number_format($percentage, 1) ?>%</h6>
                                    <small class="text-muted">Utilisé</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h6 class="text-success"><?= number_format($stats['monthly_budget'] - $stats['monthly_expenses'], 0, ',', ' ') ?>DH</h6>
                                <small class="text-muted">Restant</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-wallet fs-1 text-muted mb-3"></i>
                            <p class="text-muted">Aucun budget défini pour ce mois</p>
                            <a href="budget.php" class="btn btn-primary">Définir un budget</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Dernières dépenses -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Dernières dépenses</h5>
                    <a href="budget.php#expenses" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_expenses)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-receipt-cutoff fs-1 text-muted mb-3"></i>
                            <p class="text-muted">Aucune dépense enregistrée</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_expenses as $expense): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= escape($expense['description']) ?></h6>
                                            <small class="text-muted">
                                                <span class="badge bg-secondary"><?= ucfirst($expense['category']) ?></span>
                                                <?= date('d/m/Y', strtotime($expense['expense_date'])) ?>
                                            </small>
                                        </div>
                                        <strong class="text-danger">-<?= number_format($expense['amount'], 2, ',', ' ') ?>DH</strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>