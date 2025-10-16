<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];

// Traitement des formulaires
if ($_POST) {
    if (isset($_POST['create_event'])) {
        $query = "INSERT INTO events (title, description, event_date, location, created_by, max_participants) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['event_date'],
            $_POST['location'],
            $userId,
            $_POST['max_participants'] ?: null
        ]);
    } elseif (isset($_POST['join_event'])) {
        $eventId = $_POST['event_id'];
        
        // Vérifier si l'événement n'est pas complet
        $query = "SELECT max_participants, COUNT(ep.id) as current_participants 
                  FROM events e 
                  LEFT JOIN event_participants ep ON e.id = ep.event_id 
                  WHERE e.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$eventId]);
        $eventInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$eventInfo['max_participants'] || $eventInfo['current_participants'] < $eventInfo['max_participants']) {
            $query = "INSERT IGNORE INTO event_participants (event_id, user_id) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$eventId, $userId]);
        }
    } elseif (isset($_POST['leave_event'])) {
        $query = "DELETE FROM event_participants WHERE event_id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$_POST['event_id'], $userId]);
    }
}

// Récupération des événements avec informations sur la participation
$filter = $_GET['filter'] ?? 'all';
$currentDate = date('Y-m-d H:i:s');

$query = "SELECT e.*, 
                 COUNT(ep.id) as participant_count,
                 u.first_name, u.last_name,
                 EXISTS(SELECT 1 FROM event_participants WHERE event_id = e.id AND user_id = ?) as is_participant,
                 CASE 
                     WHEN e.event_date < ? THEN 'past'
                     WHEN e.event_date <= DATE_ADD(?, INTERVAL 7 DAY) THEN 'soon'
                     ELSE 'future'
                 END as status
          FROM events e 
          LEFT JOIN event_participants ep ON e.id = ep.event_id 
          LEFT JOIN users u ON e.created_by = u.id";

$conditions = [];
$params = [$userId, $currentDate, $currentDate];

switch ($filter) {
    case 'upcoming':
        $conditions[] = "e.event_date >= ?";
        $params[] = $currentDate;
        break;
    case 'past':
        $conditions[] = "e.event_date < ?";
        $params[] = $currentDate;
        break;
    case 'my_events':
        $conditions[] = "e.created_by = ?";
        $params[] = $userId;
        break;
    case 'joined':
        $query .= " JOIN event_participants ep_user ON e.id = ep_user.event_id AND ep_user.user_id = ?";
        array_unshift($params, $userId);
        break;
}

if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " GROUP BY e.id ORDER BY e.event_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques rapides
$query = "SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN event_date >= ? THEN 1 ELSE 0 END) as upcoming_events,
            SUM(CASE WHEN created_by = ? THEN 1 ELSE 0 END) as my_events,
            COUNT(DISTINCT ep.user_id) as total_participants
          FROM events e
          LEFT JOIN event_participants ep ON e.id = ep.event_id";
$stmt = $db->prepare($query);
$stmt->execute([$currentDate, $userId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Mes participations
$query = "SELECT COUNT(*) as joined_events FROM event_participants WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$joinedCount = $stmt->fetch(PDO::FETCH_ASSOC)['joined_events'];

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-calendar-event me-2"></i>Événements étudiants
                <small class="text-muted">Découvrez et participez à la vie de campus</small>
            </h1>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?= $stats['upcoming_events'] ?></h4>
                    <p class="mb-0">Événements à venir</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?= $joinedCount ?></h4>
                    <p class="mb-0">Mes participations</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?= $stats['my_events'] ?></h4>
                    <p class="mb-0">Mes événements</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <button class="btn btn-dark btn-sm w-100" data-bs-toggle="modal" data-bs-target="#createEventModal">
                        <i class="bi bi-plus-lg"></i> Créer un événement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="btn-group" role="group">
                <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="bi bi-calendar3 me-1"></i>Tous les événements
                </a>
                <a href="?filter=upcoming" class="btn <?= $filter === 'upcoming' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="bi bi-calendar-plus me-1"></i>À venir
                </a>
                <a href="?filter=joined" class="btn <?= $filter === 'joined' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="bi bi-person-check me-1"></i>Mes participations
                </a>
                <a href="?filter=my_events" class="btn <?= $filter === 'my_events' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="bi bi-gear me-1"></i>Mes événements
                </a>
                <a href="?filter=past" class="btn <?= $filter === 'past' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="bi bi-archive me-1"></i>Passés
                </a>
            </div>
        </div>
    </div>

    <!-- Liste des événements -->
    <?php if (empty($events)): ?>
    <div class="text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
        <h5>Aucun événement trouvé</h5>
        <p class="text-muted">
            <?php
            switch($filter) {
                case 'upcoming': echo 'Aucun événement prévu prochainement.'; break;
                case 'past': echo 'Aucun événement passé.'; break;
                case 'my_events': echo 'Vous n\'avez créé aucun événement.'; break;
                case 'joined': echo 'Vous ne participez à aucun événement.'; break;
                default: echo 'Aucun événement disponible.'; break;
            }
            ?>
        </p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
            Créer le premier événement
        </button>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($events as $event): ?>
        <div class="col-lg-6">
            <div class="card h-100 event-card <?= $event['status'] === 'past' ? 'opacity-75' : '' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><?= escape($event['title']) ?></h6>
                        <small class="text-muted">
                            par <?= escape($event['first_name'] . ' ' . $event['last_name']) ?>
                        </small>
                    </div>
                    <div>
                        <?php if ($event['status'] === 'past'): ?>
                        <span class="badge bg-secondary">Terminé</span>
                        <?php elseif ($event['status'] === 'soon'): ?>
                        <span class="badge bg-warning text-dark">Bientôt</span>
                        <?php else: ?>
                        <span class="badge bg-success">À venir</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Date et lieu -->
                    <div class="event-date bg-primary text-white p-3 rounded mb-3">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="text-center">
                                    <div class="h4 mb-0"><?= date('d', strtotime($event['event_date'])) ?></div>
                                    <div class="small"><?= strftime('%b', strtotime($event['event_date'])) ?></div>
                                </div>
                            </div>
                            <div class="col">
                                <div>
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('H:i', strtotime($event['event_date'])) ?>
                                </div>
                                <?php if ($event['location']): ?>
                                <div>
                                    <i class="bi bi-geo-alt me-1"></i>
                                    <?= escape($event['location']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <p class="text-muted mb-3">
                        <?= escape(substr($event['description'], 0, 150)) ?><?= strlen($event['description']) > 150 ? '...' : '' ?>
                    </p>

                    <!-- Participants -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="bi bi-people me-1"></i>
                            <strong><?= $event['participant_count'] ?></strong>
                            <?php if ($event['max_participants']): ?>
                            / <?= $event['max_participants'] ?> participants
                            <?php else: ?>
                            participant<?= $event['participant_count'] > 1 ? 's' : '' ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($event['max_participants']): ?>
                        <div class="progress" style="width: 100px; height: 6px;">
                            <div class="progress-bar" 
                                 style="width: <?= min(($event['participant_count'] / $event['max_participants']) * 100, 100) ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($event['is_participant']): ?>
                            <span class="badge bg-success me-2">
                                <i class="bi bi-check-circle me-1"></i>Inscrit
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($event['created_by'] == $userId): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-star-fill me-1"></i>Organisateur
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewEventDetails(<?= $event['id'] ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            
                            <?php if ($event['status'] !== 'past'): ?>
                                <?php if ($event['is_participant']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    <button type="submit" name="leave_event" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Se désinscrire de cet événement ?')">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                                <?php elseif (!$event['max_participants'] || $event['participant_count'] < $event['max_participants']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    <button type="submit" name="join_event" class="btn btn-sm btn-success">
                                        <i class="bi bi-person-plus"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="bi bi-person-x"></i> Complet
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($event['created_by'] == $userId): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick="manageEvent(<?= $event['id'] ?>)">
                                <i class="bi bi-gear"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Créer un événement -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-plus me-2"></i>Créer un événement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Idées d'événements :</strong> Session de révision, soirée jeux, sortie culturelle, 
                        conférence, atelier pratique, rencontre sportive, afterwork étudiant...
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Titre de l'événement *</label>
                        <input type="text" class="form-control" name="title" required 
                               placeholder="ex: Session de révision Mathématiques L2">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4" 
                                  placeholder="Décrivez l'événement, son objectif, le programme prévu..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date et heure *</label>
                                <input type="datetime-local" class="form-control" name="event_date" required 
                                       min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Lieu</label>
                                <input type="text" class="form-control" name="location" 
                                       placeholder="Bibliothèque universitaire, Amphi A, Cafétéria...">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre maximum de participants</label>
                                <select class="form-select" name="max_participants">
                                    <option value="">Illimité</option>
                                    <option value="5">5 participants</option>
                                    <option value="10">10 participants</option>
                                    <option value="15">15 participants</option>
                                    <option value="20">20 participants</option>
                                    <option value="30">30 participants</option>
                                    <option value="50">50 participants</option>
                                </select>
                                <div class="form-text">Laissez vide pour un événement sans limite</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="bi bi-info-circle me-2"></i>Types d'événements populaires :</h6>
                                    <div class="small">
                                        <span class="badge bg-primary me-1">Académique</span>
                                        <span class="badge bg-success me-1">Social</span>
                                        <span class="badge bg-warning me-1">Culturel</span>
                                        <span class="badge bg-info me-1">Sportif</span>
                                        <span class="badge bg-secondary">Professionnel</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="bi bi-clock me-2"></i>Conseils de timing :</h6>
                                    <ul class="small mb-0">
                                        <li>Évitez les heures de cours</li>
                                        <li>Week-ends populaires pour les sorties</li>
                                        <li>Soirées en semaine pour les révisions</li>
                                        <li>Pauses déjeuner pour les rencontres courtes</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="bi bi-people me-2"></i>Engagement communautaire :</h6>
                                    <ul class="small mb-0">
                                        <li>Proposez du contenu de qualité</li>
                                        <li>Soyez ponctuel et préparé</li>
                                        <li>Encouragez la participation</li>
                                        <li>Recueillez les retours</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_event" class="btn btn-primary">
                        <i class="bi bi-calendar-plus me-1"></i>Créer l'événement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Détails de l'événement -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventDetailsTitle">Détails de l'événement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventDetailsContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<style>
.event-card {
    transition: all 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.event-date {
    background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
}

.progress {
    border-radius: 10px;
}

.badge {
    font-size: 0.75rem;
}

.btn-group .btn {
    border-radius: 0.375rem;
    margin-left: 2px;
}

.btn-group .btn:first-child {
    margin-left: 0;
}
</style>

<script>
function viewEventDetails(eventId) {
    // Faire un appel AJAX pour récupérer les détails de l'événement
    fetch(`api/event-details.php?event_id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('eventDetailsTitle').textContent = data.title;
            
            const participants = data.participants.map(p => 
                `<div class="d-flex align-items-center mb-2">
                    <div class="avatar bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                        <i class="bi bi-person"></i>
                    </div>
                    <div>
                        <strong>${p.first_name} ${p.last_name}</strong>
                        ${p.field_of_study ? `<br><small class="text-muted">${p.field_of_study}</small>` : ''}
                    </div>
                </div>`
            ).join('');
            
            const content = `
                <div class="row">
                    <div class="col-md-8">
                        <h6><i class="bi bi-calendar3 me-2"></i>Informations</h6>
                        <p><strong>Date :</strong> ${new Date(data.event_date).toLocaleString('fr-FR')}</p>
                        ${data.location ? `<p><strong>Lieu :</strong> ${data.location}</p>` : ''}
                        ${data.max_participants ? `<p><strong>Places :</strong> ${data.participant_count}/${data.max_participants}</p>` : ''}
                        
                        <h6 class="mt-4"><i class="bi bi-file-text me-2"></i>Description</h6>
                        <p>${data.description || 'Aucune description fournie.'}</p>
                        
                        <h6 class="mt-4"><i class="bi bi-person me-2"></i>Organisateur</h6>
                        <p>${data.organizer_name} ${data.organizer_field ? `- ${data.organizer_field}` : ''}</p>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="bi bi-people me-2"></i>Participants (${data.participant_count})</h6>
                        <div class="participant-list" style="max-height: 300px; overflow-y: auto;">
                            ${participants || '<p class="text-muted">Aucun participant pour le moment.</p>'}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('eventDetailsContent').innerHTML = content;
            
            const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            smartCampus.showNotification('Erreur lors du chargement des détails', 'danger');
        });
}

function manageEvent(eventId) {
    smartCampus.showNotification('Fonctionnalité de gestion des événements en cours de développement', 'info');
    // Ici on pourrait ouvrir une modal de gestion avec options :
    // - Modifier l'événement
    // - Voir la liste des participants
    // - Envoyer un message aux participants
    // - Annuler l'événement
}

// Validation du formulaire
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#createEventModal form');
    const dateInput = form.querySelector('input[name="event_date"]');
    
    // Suggestions automatiques basées sur l'heure
    dateInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const now = new Date();
        const locationInput = form.querySelector('input[name="location"]');
        
        // Suggestions de lieu basées sur l'heure
        if (!locationInput.value) {
            const hour = selectedDate.getHours();
            let suggestion = '';
            
            if (hour >= 8 && hour <= 12) {
                suggestion = 'Bibliothèque universitaire';
            } else if (hour >= 12 && hour <= 14) {
                suggestion = 'Cafétéria / Restaurant universitaire';
            } else if (hour >= 14 && hour <= 18) {
                suggestion = 'Salle de cours / Amphi';
            } else if (hour >= 18 && hour <= 22) {
                suggestion = 'Salle commune / Foyer étudiant';
            }
            
            if (suggestion) {
                locationInput.placeholder = `Suggestion: ${suggestion}`;
            }
        }
    });

    // Auto-complétion pour les titres d'événements
    const titleInput = form.querySelector('input[name="title"]');
    const suggestions = [
        'Session de révision',
        'Afterwork étudiant',
        'Soirée jeux de société',
        'Sortie culturelle',
        'Conférence métier',
        'Atelier pratique',
        'Rencontre sportive',
        'Groupe d\'étude',
        'Café débat',
        'Visite d\'entreprise'
    ];
    
    titleInput.addEventListener('input', function() {
        // Ici on pourrait implémenter une auto-complétion plus sophistiquée
    });
});

// Notifications pour les événements à venir
function checkUpcomingEvents() {
    const now = new Date();
    const events = <?= json_encode($events) ?>;
    
    events.forEach(event => {
        if (event.is_participant) {
            const eventDate = new Date(event.event_date);
            const timeDiff = eventDate - now;
            const hoursDiff = timeDiff / (1000 * 60 * 60);
            
            // Notifier 2 heures avant l'événement
            if (hoursDiff > 0 && hoursDiff <= 2 && !sessionStorage.getItem(`notified_${event.id}`)) {
                smartCampus.showNotification(
                    `Rappel: "${event.title}" commence dans ${Math.round(hoursDiff)}h`, 
                    'info'
                );
                sessionStorage.setItem(`notified_${event.id}`, 'true');
            }
        }
    });
}

// Vérifier les événements à venir toutes les 30 minutes
setInterval(checkUpcomingEvents, 30 * 60 * 1000);
checkUpcomingEvents(); // Vérification initiale
</script>

<?php include 'includes/footer.php'; ?>