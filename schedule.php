<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];

// Traitement des formulaires
if ($_POST) {
    if (isset($_POST['add_schedule'])) {
        $query = "INSERT INTO schedules (user_id, subject, day_of_week, start_time, end_time, room, teacher) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $userId,
            $_POST['subject'],
            $_POST['day_of_week'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['room'],
            $_POST['teacher']
        ]);
    } elseif (isset($_POST['add_assignment'])) {
        $query = "INSERT INTO assignments (user_id, title, description, subject, due_date, type) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $userId,
            $_POST['title'],
            $_POST['description'],
            $_POST['subject'],
            $_POST['due_date'],
            $_POST['type']
        ]);
    }
}

// Récupération de l'emploi du temps
$query = "SELECT * FROM schedules WHERE user_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des devoirs
$query = "SELECT * FROM assignments WHERE user_id = ? ORDER BY due_date, created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-calendar3 me-2"></i>Emploi du temps & Devoirs
            </h1>
        </div>
    </div>

    <!-- Onglets de navigation -->
    <nav>
        <div class="nav nav-tabs" role="tablist">
            <button class="nav-link ac tive" data-bs-toggle="tab" data-bs-target="#schedule-tab">
                <i class="bi bi-calendar-week me-1"></i>Emploi du temps
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignments-tab">
                <i class="bi bi-clipboard-check me-1"></i>Devoirs & Examens
            </button>
        </div>
    </nav>

    <div class="tab-content mt-4">
        <!-- Emploi du temps -->
        <div class="tab-pane fade show active" id="schedule-tab">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Planning hebdomadaire</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                <i class="bi bi-plus-lg"></i> Ajouter un cours
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Jour</th>
                                            <th>Heure</th>
                                            <th>Matière</th>
                                            <th>Salle</th>
                                            <th>Professeur</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php
                                                    $days = [
                                                        'Monday' => 'Lundi',
                                                        'Tuesday' => 'Mardi',
                                                        'Wednesday' => 'Mercredi',
                                                        'Thursday' => 'Jeudi',
                                                        'Friday' => 'Vendredi',
                                                        'Saturday' => 'Samedi',
                                                        'Sunday' => 'Dimanche'
                                                    ];
                                                    echo $days[$schedule['day_of_week']];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= date('H:i', strtotime($schedule['start_time'])) ?></strong>
                                                <small class="text-muted">- <?= date('H:i', strtotime($schedule['end_time'])) ?></small>
                                            </td>
                                            <td><?= escape($schedule['subject']) ?></td>
                                            <td><?= escape($schedule['room'] ?: '-') ?></td>
                                            <td><?= escape($schedule['teacher'] ?: '-') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSchedule(<?= $schedule['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($schedules)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="bi bi-calendar-x fs-1 text-muted mb-3 d-block"></i>
                                                <p class="text-muted">Aucun cours programmé</p>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                                    Ajouter votre premier cours
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Vue calendrier</h6>
                        </div>
                        <div class="card-body">
                            <div id="calendar-container"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Devoirs et examens -->
        <div class="tab-pane fade" id="assignments-tab">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Mes devoirs et examens</h5>
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                <i class="bi bi-plus-lg"></i> Ajouter un devoir
                            </button>
                        </div>
                        <div class="card-body">
                            <?php foreach ($assignments as $assignment): ?>
                            <div class="card mb-3 border-start border-4 <?php
                                $days_left = floor((strtotime($assignment['due_date']) - time()) / (60*60*24));
                                echo $days_left <= 1 ? 'border-danger' : ($days_left <= 7 ? 'border-warning' : 'border-success');
                            ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-2"><?= escape($assignment['title']) ?></h6>
                                            <div class="mb-2">
                                                <span class="badge bg-secondary me-2"><?= escape($assignment['subject']) ?></span>
                                                <span class="badge bg-info text-dark"><?= ucfirst($assignment['type']) ?></span>
                                            </div>
                                            <?php if ($assignment['description']): ?>
                                            <p class="text-muted small mb-2"><?= escape($assignment['description']) ?></p>
                                            <?php endif; ?>
                                            <div class="d-flex align-items-center text-muted small">
                                                <i class="bi bi-calendar-event me-1"></i>
                                                À rendre le <?= date('d/m/Y', strtotime($assignment['due_date'])) ?>
                                                <?php
                                                $days_left = floor((strtotime($assignment['due_date']) - time()) / (60*60*24));
                                                if ($days_left >= 0) {
                                                    echo " (" . ($days_left == 0 ? 'Aujourd\'hui' : $days_left . ' jour' . ($days_left > 1 ? 's' : '')) . ")";
                                                } else {
                                                    echo " (En retard de " . abs($days_left) . " jour" . (abs($days_left) > 1 ? 's' : '') . ")";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <button class="dropdown-item" onclick="markCompleted(<?= $assignment['id'] ?>)">
                                                        <i class="bi bi-check-circle me-1"></i> Marquer terminé
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger" onclick="deleteAssignment(<?= $assignment['id'] ?>)">
                                                        <i class="bi bi-trash me-1"></i> Supprimer
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (empty($assignments)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-clipboard-check fs-1 text-muted mb-3"></i>
                                <p class="text-muted">Aucun devoir enregistré</p>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                    Ajouter votre premier devoir
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Statistiques</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $pending = array_filter($assignments, fn($a) => $a['status'] === 'pending');
                            $completed = array_filter($assignments, fn($a) => $a['status'] === 'completed');
                            $overdue = array_filter($assignments, fn($a) => strtotime($a['due_date']) < time() && $a['status'] === 'pending');
                            ?>
                            <div class="row text-center">
                                <div class="col-12 mb-3">
                                    <div class="bg-primary text-white p-3 rounded">
                                        <h4><?= count($pending) ?></h4>
                                        <small>En cours</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-success text-white p-2 rounded">
                                        <h6><?= count($completed) ?></h6>
                                        <small>Terminés</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-danger text-white p-2 rounded">
                                        <h6><?= count($overdue) ?></h6>
                                        <small>En retard</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter un cours -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un cours</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Matière</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Jour</label>
                                <select class="form-select" name="day_of_week" required>
                                    <option value="Monday">Lundi</option>
                                    <option value="Tuesday">Mardi</option>
                                    <option value="Wednesday">Mercredi</option>
                                    <option value="Thursday">Jeudi</option>
                                    <option value="Friday">Vendredi</option>
                                    <option value="Saturday">Samedi</option>
                                    <option value="Sunday">Dimanche</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Début</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Fin</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Salle</label>
                                <input type="text" class="form-control" name="room">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Professeur</label>
                                <input type="text" class="form-control" name="teacher">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_schedule" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajouter un devoir -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un devoir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titre</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Matière</label>
                                <input type="text" class="form-control" name="subject" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" required>
                                    <option value="homework">Devoir</option>
                                    <option value="exam">Examen</option>
                                    <option value="project">Projet</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date limite</label>
                        <input type="date" class="form-control" name="due_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_assignment" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteSchedule(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce cours ?')) {
        fetch('api/delete-schedule.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        }).then(() => location.reload());
    }
}

function deleteAssignment(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce devoir ?')) {
        fetch('api/delete-assignment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        }).then(() => location.reload());
    }
}

function markCompleted(id) {
    fetch('api/complete-assignment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    }).then(() => location.reload());
}
</script>

<?php include 'includes/footer.php'; ?>