<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];

// Traitement des formulaires
if ($_POST) {
    if (isset($_POST['add_expense'])) {
        $query = "INSERT INTO expenses (user_id, category, amount, description, expense_date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $userId,
            $_POST['category'],
            $_POST['amount'],
            $_POST['description'],
            $_POST['expense_date']
        ]);
    } elseif (isset($_POST['set_budget'])) {
        $monthYear = date('Y-m');
        $query = "INSERT INTO budgets (user_id, month_year, total_budget, logement_budget, transport_budget, restauration_budget, loisirs_budget, etudes_budget, autres_budget) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  total_budget = ?, logement_budget = ?, transport_budget = ?, restauration_budget = ?, loisirs_budget = ?, etudes_budget = ?, autres_budget = ?";
        $stmt = $db->prepare($query);
        $values = [
            $userId, $monthYear,
            $_POST['total_budget'], $_POST['logement_budget'], $_POST['transport_budget'],
            $_POST['restauration_budget'], $_POST['loisirs_budget'], $_POST['etudes_budget'], $_POST['autres_budget'],
            $_POST['total_budget'], $_POST['logement_budget'], $_POST['transport_budget'],
            $_POST['restauration_budget'], $_POST['loisirs_budget'], $_POST['etudes_budget'], $_POST['autres_budget']
        ];
        $stmt->execute($values);
    }
}

// Récupération des dépenses du mois actuel
$currentMonth = date('Y-m');
$query = "SELECT * FROM expenses WHERE user_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = ? ORDER BY expense_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$userId, $currentMonth]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération du budget du mois actuel
$query = "SELECT * FROM budgets WHERE user_id = ? AND month_year = ?";
$stmt = $db->prepare($query);
$stmt->execute([$userId, $currentMonth]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcul des totaux par catégorie
$categoryTotals = [];
$totalSpent = 0;
foreach ($expenses as $expense) {
    $category = $expense['category'];
    $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + $expense['amount'];
    $totalSpent += $expense['amount'];
}

// Catégories avec leurs couleurs
$categories = [
    'logement' => ['name' => 'Logement', 'color' => 'primary'],
    'transport' => ['name' => 'Transport', 'color' => 'success'],
    'restauration' => ['name' => 'Restauration', 'color' => 'warning'],
    'loisirs' => ['name' => 'Loisirs', 'color' => 'info'],
    'etudes' => ['name' => 'Études', 'color' => 'secondary'],
    'autres' => ['name' => 'Autres', 'color' => 'dark']
];

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-wallet2 me-2"></i>Gestion budgétaire
                <small class="text-muted">- <?= date('F Y') ?></small>
            </h1>
        </div>
    </div>

    <!-- Vue d'ensemble du budget -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #0d6efd, #6610f2);">
                <div class="card-body text-center">
                    <h3><?= number_format($budget['total_budget'] ?? 0, 0, ',', ' ') ?>DH</h3>
                    <p class="mb-0">Budget mensuel</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #dc3545, #fd7e14);">
                <div class="card-body text-center">
                    <h3><?= number_format($totalSpent, 0, ',', ' ') ?>DH</h3>
                    <p class="mb-0">Dépenses</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #198754, #20c997);">
                <div class="card-body text-center">
                    <h3><?= number_format(($budget['total_budget'] ?? 0) - $totalSpent, 0, ',', ' ') ?>DH</h3>
                    <p class="mb-0">Reste</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #6f42c1, #e0aaff);">
                <div class="card-body text-center">
                    <?php $percentage = $budget ? ($totalSpent / $budget['total_budget']) * 100 : 0; ?>
                    <h3><?= number_format($percentage, 1) ?>%</h3>
                    <p class="mb-0">Utilisé</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglets -->
    <nav>
        <div class="nav nav-tabs" role="tablist">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview-tab">
                <i class="bi bi-graph-up me-1"></i>Aperçu
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#expenses-tab">
                <i class="bi bi-receipt me-1"></i>Dépenses
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#budget-tab">
                <i class="bi bi-piggy-bank me-1"></i>Budget
            </button>
        </div>
    </nav>

    <div class="tab-content mt-4">
        <!-- Aperçu -->
        <div class="tab-pane fade show active" id="overview-tab">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Répartition par catégorie</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($categories as $key => $category): ?>
                            <?php 
                            $spent = $categoryTotals[$key] ?? 0;
                            $budgetAmount = $budget[$key . '_budget'] ?? 0;
                            $percentage = $budgetAmount > 0 ? ($spent / $budgetAmount) * 100 : 0;
                            ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <i class="bi bi-circle-fill text-<?= $category['color'] ?> me-2"></i>
                                        <strong><?= $category['name'] ?></strong>
                                    </div>
                                    <div>
                                        <span class="text-<?= $percentage > 100 ? 'danger' : 'muted' ?>">
                                            <?= number_format($spent, 2, ',', ' ') ?>DH / <?= number_format($budgetAmount, 0, ',', ' ') ?>DH
                                        </span>
                                    </div>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?= $category['color'] ?>" 
                                         style="width: <?= min($percentage, 100) ?>%"></div>
                                </div>
                                <small class="text-muted"><?= number_format($percentage, 1) ?>% utilisé</small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Graphique des dépenses</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="expenseChart" width="250" height="250"></canvas>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Conseils budgétaires</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($percentage > 90): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Attention !</strong> Vous avez dépassé 90% de votre budget.
                            </div>
                            <?php elseif ($percentage > 75): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <strong>Prudence !</strong> Vous avez atteint <?= number_format($percentage, 1) ?>% de votre budget.
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Bien joué !</strong> Votre budget est sous contrôle.
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <h6>💡 Conseils intelligents :</h6>
                                <ul class="small">
                                    <?php if (($categoryTotals['restauration'] ?? 0) > ($budget['restauration_budget'] ?? 0) * 0.8): ?>
                                    <li>Pensez à cuisiner plus souvent pour réduire les frais de restauration</li>
                                    <?php endif; ?>
                                    <?php if (($categoryTotals['loisirs'] ?? 0) > ($budget['loisirs_budget'] ?? 0) * 0.9): ?>
                                    <li>Explorez les activités gratuites sur le campus</li>
                                    <?php endif; ?>
                                    <li>Définissez un budget hebdomadaire pour mieux contrôler vos dépenses</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dépenses -->
        <div class="tab-pane fade" id="expenses-tab">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Historique des dépenses</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                <i class="bi bi-plus-lg"></i> Ajouter une dépense
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($expenses)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-receipt-cutoff fs-1 text-muted mb-3"></i>
                                <p class="text-muted">Aucune dépense enregistrée ce mois</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                    Ajouter votre première dépense
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Catégorie</th>
                                            <th>Description</th>
                                            <th>Montant</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($expense['expense_date'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $categories[$expense['category']]['color'] ?>">
                                                    <?= $categories[$expense['category']]['name'] ?>
                                                </span>
                                            </td>
                                            <td><?= escape($expense['description']) ?></td>
                                            <td><strong>-<?= number_format($expense['amount'], 2, ',', ' ') ?>DH</strong></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(<?= $expense['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Résumé mensuel</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border p-2 rounded">
                                        <h5 class="text-primary"><?= count($expenses) ?></h5>
                                        <small>Transactions</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border p-2 rounded">
                                        <h5 class="text-danger"><?= number_format($totalSpent / max(count($expenses), 1), 0) ?>DH</h5>
                                        <small>Moyenne</small>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="mt-4">Top catégories</h6>
                            <?php 
                            arsort($categoryTotals);
                            $topCategories = array_slice($categoryTotals, 0, 3, true);
                            ?>
                            <?php foreach ($topCategories as $cat => $amount): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge bg-<?= $categories[$cat]['color'] ?>"><?= $categories[$cat]['name'] ?></span>
                                <strong><?= number_format($amount, 0) ?>DH</strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget -->
        <div class="tab-pane fade" id="budget-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Configuration du budget mensuel</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Budget total mensuel</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="total_budget" 
                                               value="<?= $budget['total_budget'] ?? '' ?>" step="0.01" required>
                                        <span class="input-group-text">DH</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Conseil :</strong> Utilisez la règle 50/30/20 : 50% besoins essentiels, 30% loisirs, 20% épargne.
                                </div>
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3">Répartition par catégorie</h6>
                        <div class="row g-3">
                            <?php foreach ($categories as $key => $category): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-circle-fill text-<?= $category['color'] ?> me-2"></i>
                                            <strong><?= $category['name'] ?></strong>
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" 
                                                   name="<?= $key ?>_budget" 
                                                   value="<?= $budget[$key . '_budget'] ?? '' ?>" 
                                                   step="0.01" placeholder="0.00">
                                            <span class="input-group-text">DH</span>
                                        </div>
                                        <?php if (isset($categoryTotals[$key])): ?>
                                        <small class="text-muted">
                                            Dépensé ce mois : <?= number_format($categoryTotals[$key], 2) ?>DH
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" name="set_budget" class="btn btn-success">
                                <i class="bi bi-piggy-bank me-2"></i>
                                <?= $budget ? 'Mettre à jour le budget' : 'Définir le budget' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter une dépense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une dépense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Catégorie</label>
                        <select class="form-select" name="category" required>
                            <?php foreach ($categories as $key => $category): ?>
                            <option value="<?= $key ?>"><?= $category['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Montant</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="amount" step="0.01" required>
                            <span class="input-group-text">DH</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_expense" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Graphique des dépenses
const canvas = document.getElementById('expenseChart');
if (canvas) {
    const ctx = canvas.getContext('2d');
    const data = <?= json_encode(array_values($categoryTotals)) ?>;
    const labels = <?= json_encode(array_map(fn($k) => $categories[$k]['name'], array_keys($categoryTotals))) ?>;
    const colors = <?= json_encode(array_map(fn($k) => $categories[$k]['color'], array_keys($categoryTotals))) ?>;
    
    drawPieChart(ctx, data, labels, colors);
}

function drawPieChart(ctx, data, labels, colors) {
    const total = data.reduce((sum, val) => sum + val, 0);
    if (total === 0) return;
    
    const centerX = ctx.canvas.width / 2;
    const centerY = ctx.canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 20;
    
    let currentAngle = 0;
    const colorMap = {
        'primary': '#0d6efd', 'success': '#198754', 'warning': '#ffc107',
        'info': '#0dcaf0', 'secondary': '#6c757d', 'dark': '#212529'
    };
    
    data.forEach((value, index) => {
        const sliceAngle = (value / total) * 2 * Math.PI;
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
        ctx.lineTo(centerX, centerY);
        ctx.fillStyle = colorMap[colors[index]] || '#6c757d';
        ctx.fill();
        
        currentAngle += sliceAngle;
    });
}

function deleteExpense(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette dépense ?')) {
        fetch('api/delete-expense.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        }).then(() => location.reload());
    }
}
</script>

<?php include 'includes/footer.php'; ?>