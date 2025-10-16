<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];

// Traitement des formulaires
if ($_POST) {
    if (isset($_POST['create_group'])) {
        $query = "INSERT INTO study_groups (name, subject, description, max_members, created_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $_POST['name'],
            $_POST['subject'],
            $_POST['description'],
            $_POST['max_members'],
            $userId
        ]);
        
        $groupId = $db->lastInsertId();
        
        // Ajouter le créateur comme membre
        $query = "INSERT INTO group_members (group_id, user_id) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$groupId, $userId]);
        
    } elseif (isset($_POST['join_group'])) {
        $groupId = $_POST['group_id'];
        
        // Vérifier si le groupe n'est pas plein
        $query = "SELECT sg.max_members, COUNT(gm.id) as current_members 
                  FROM study_groups sg 
                  LEFT JOIN group_members gm ON sg.id = gm.group_id 
                  WHERE sg.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$groupId]);
        $groupInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($groupInfo['current_members'] < $groupInfo['max_members']) {
            $query = "INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$groupId, $userId]);
        }
    }
}

// Récupération des groupes existants avec le nombre de membres
$query = "SELECT sg.*, 
                 COUNT(gm.id) as member_count,
                 u.first_name, u.last_name,
                 EXISTS(SELECT 1 FROM group_members WHERE group_id = sg.id AND user_id = ?) as is_member
          FROM study_groups sg 
          LEFT JOIN group_members gm ON sg.id = gm.group_id 
          LEFT JOIN users u ON sg.created_by = u.id
          GROUP BY sg.id 
          ORDER BY sg.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$allGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des groupes dont l'utilisateur est membre
$query = "SELECT sg.*, 
                 COUNT(gm.id) as member_count,
                 u.first_name, u.last_name
          FROM study_groups sg 
          JOIN group_members gm_user ON sg.id = gm_user.group_id AND gm_user.user_id = ?
          LEFT JOIN group_members gm ON sg.id = gm.group_id 
          LEFT JOIN users u ON sg.created_by = u.id
          GROUP BY sg.id 
          ORDER BY sg.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$myGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Algorithme de matching simple pour recommandations
function getRecommendedGroups($allGroups, $userId, $db) {
    // Récupérer le profil de l'utilisateur
    $query = "SELECT field_of_study, academic_year FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $recommendations = [];
    
    foreach ($allGroups as $group) {
        if ($group['is_member'] || $group['member_count'] >= $group['max_members']) {
            continue;
        }
        
        $score = 0;
        
        // Bonus si même domaine d'études
        if ($userProfile['field_of_study'] && strpos(strtolower($group['subject']), strtolower($userProfile['field_of_study'])) !== false) {
            $score += 30;
        }
        
        // Bonus pour groupes pas trop pleins (meilleure interaction)
        $fillRatio = $group['member_count'] / $group['max_members'];
        if ($fillRatio < 0.7) {
            $score += 20;
        }
        
        // Bonus pour groupes récents (plus actifs)
        $daysOld = (time() - strtotime($group['created_at'])) / (60*60*24);
        if ($daysOld < 7) {
            $score += 15;
        }
        
        // Bonus si le nom du groupe contient des mots-clés pertinents
        $keywords = ['exam', 'revision', 'projet', 'cours', 'etude'];
        foreach ($keywords as $keyword) {
            if (strpos(strtolower($group['name'] . ' ' . $group['description']), $keyword) !== false) {
                $score += 10;
                break;
            }
        }
        
        if ($score > 0) {
            $group['match_score'] = $score;
            $recommendations[] = $group;
        }
    }
    
    // Trier par score décroissant
    usort($recommendations, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    return array_slice($recommendations, 0, 5); // Top 5
}

$recommendedGroups = getRecommendedGroups($allGroups, $userId, $db);

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-people me-2"></i>Groupes d'étude
                <small class="text-muted">Matching intelligent pour réviser ensemble</small>
            </h1>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?= count($myGroups) ?></h4>
                    <p class="mb-0">Mes groupes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?= count($allGroups) ?></h4>
                    <p class="mb-0">Groupes disponibles</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?= count($recommendedGroups) ?></h4>
                    <p class="mb-0">Recommandations</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <button class="btn btn-dark btn-sm w-100" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                        <i class="bi bi-plus-lg"></i> Créer un groupe
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglets -->
    <nav>
        <div class="nav nav-tabs" role="tablist">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#recommended-tab">
                <i class="bi bi-stars me-1"></i>Recommandés pour vous
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#my-groups-tab">
                <i class="bi bi-person-check me-1"></i>Mes groupes (<?= count($myGroups) ?>)
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#all-groups-tab">
                <i class="bi bi-grid me-1"></i>Tous les groupes
            </button>
        </div>
    </nav>

    <div class="tab-content mt-4">
        <!-- Groupes recommandés -->
        <div class="tab-pane fade show active" id="recommended-tab">
            <?php if (empty($recommendedGroups)): ?>
            <div class="text-center py-5">
                <i class="bi bi-robot fs-1 text-muted mb-3"></i>
                <h5>Aucune recommandation pour le moment</h5>
                <p class="text-muted">Complétez votre profil ou explorez les groupes existants !</p>
                <a href="profile.php" class="btn btn-primary me-2">Compléter mon profil</a>
                <button class="btn btn-outline-primary" data-bs-toggle="tab" data-bs-target="#all-groups-tab">
                    Voir tous les groupes
                </button>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($recommendedGroups as $group): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-success group-card">
                        <div class="card-header bg-gradient text-white d-flex justify-content-between align-items-center" 
                             style="background: linear-gradient(135deg, #198754, #20c997) !important;">
                            <h6 class="mb-0"><?= escape($group['subject']) ?></h6>
                            <span class="badge bg-light text-dark"><?= $group['match_score'] ?>% match</span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= escape($group['name']) ?></h5>
                            <p class="card-text text-muted"><?= escape($group['description']) ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-person me-1"></i>
                                    <?= $group['member_count'] ?>/<?= $group['max_members'] ?> membres
                                </small>
                                <small class="text-muted">
                                    par <?= escape($group['first_name']) ?>
                                </small>
                            </div>

                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?= ($group['member_count'] / $group['max_members']) * 100 ?>%"></div>
                            </div>
                            
                            <div class="d-grid">
                                <?php if ($group['member_count'] >= $group['max_members']): ?>
                                <button class="btn btn-secondary" disabled>Groupe complet</button>
                                <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                    <button type="submit" name="join_group" class="btn btn-success">
                                        <i class="bi bi-person-plus me-1"></i>Rejoindre
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Créé <?= date('d/m/Y', strtotime($group['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mes groupes -->
        <div class="tab-pane fade" id="my-groups-tab">
            <?php if (empty($myGroups)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                <h5>Vous n'avez rejoint aucun groupe</h5>
                <p class="text-muted">Rejoignez des groupes d'étude pour réviser ensemble !</p>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    Créer un groupe
                </button>
                <button class="btn btn-outline-primary" data-bs-toggle="tab" data-bs-target="#all-groups-tab">
                    Parcourir les groupes
                </button>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($myGroups as $group): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-primary">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= escape($group['subject']) ?></h6>
                            <?php if ($group['created_by'] == $userId): ?>
                            <span class="badge bg-warning text-dark">Créateur</span>
                            <?php else: ?>
                            <span class="badge bg-light text-primary">Membre</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= escape($group['name']) ?></h5>
                            <p class="card-text text-muted"><?= escape($group['description']) ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-people me-1"></i>
                                    <?= $group['member_count'] ?> membre<?= $group['member_count'] > 1 ? 's' : '' ?>
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= date('d/m', strtotime($group['created_at'])) ?>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewGroupMembers(<?= $group['id'] ?>)">
                                    <i class="bi bi-people me-1"></i>Voir les membres
                                </button>
                                <?php if ($group['created_by'] == $userId): ?>
                                <button class="btn btn-outline-warning btn-sm" onclick="manageGroup(<?= $group['id'] ?>)">
                                    <i class="bi bi-gear me-1"></i>Gérer le groupe
                                </button>
                                <?php else: ?>
                                <button class="btn btn-outline-danger btn-sm" onclick="leaveGroup(<?= $group['id'] ?>)">
                                    <i class="bi bi-box-arrow-right me-1"></i>Quitter
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tous les groupes -->
        <div class="tab-pane fade" id="all-groups-tab">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchGroups" placeholder="Rechercher par matière, nom...">
                    </div>
                </div>
                <div class="col-md-6">
                    <select class="form-select" id="filterSubject">
                        <option value="">Toutes les matières</option>
                        <option value="Informatique">Informatique</option>
                        <option value="Mathématiques">Mathématiques</option>
                        <option value="Physique">Physique</option>
                        <option value="Chimie">Chimie</option>
                        <option value="Économie">Économie</option>
                        <option value="Littérature">Littérature</option>
                        <option value="Histoire">Histoire</option>
                    </select>
                </div>
            </div>

            <div class="row g-4" id="allGroupsContainer">
                <?php foreach ($allGroups as $group): ?>
                <div class="col-md-6 col-lg-4 group-item" data-subject="<?= escape($group['subject']) ?>" data-name="<?= escape($group['name']) ?>">
                    <div class="card h-100 <?= $group['is_member'] ? 'border-success' : '' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= escape($group['subject']) ?></h6>
                            <?php if ($group['is_member']): ?>
                            <span class="badge bg-success">Membre</span>
                            <?php elseif ($group['member_count'] >= $group['max_members']): ?>
                            <span class="badge bg-danger">Complet</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Disponible</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= escape($group['name']) ?></h5>
                            <p class="card-text text-muted"><?= escape($group['description']) ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-person me-1"></i>
                                    <?= $group['member_count'] ?>/<?= $group['max_members'] ?> membres
                                </small>
                                <small class="text-muted">
                                    par <?= escape($group['first_name']) ?>
                                </small>
                            </div>

                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar" 
                                     style="width: <?= ($group['member_count'] / $group['max_members']) * 100 ?>%"></div>
                            </div>
                            
                            <div class="d-grid">
                                <?php if ($group['is_member']): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="bi bi-check-circle me-1"></i>Déjà membre
                                </button>
                                <?php elseif ($group['member_count'] >= $group['max_members']): ?>
                                <button class="btn btn-secondary" disabled>Groupe complet</button>
                                <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                    <button type="submit" name="join_group" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-1"></i>Rejoindre
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Créé <?= date('d/m/Y', strtotime($group['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($allGroups)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                <h5>Aucun groupe d'étude</h5>
                <p class="text-muted">Soyez le premier à créer un groupe d'étude !</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    Créer le premier groupe
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Créer un groupe -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-people me-2"></i>Créer un nouveau groupe d'étude
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Conseils :</strong> Un bon groupe d'étude compte 3-6 membres avec des objectifs similaires. 
                        Choisissez un nom explicite et une description claire pour attirer les bonnes personnes !
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Nom du groupe *</label>
                                <input type="text" class="form-control" name="name" required 
                                       placeholder="ex: Révisions Algorithmes L3">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nombre max de membres</label>
                                <select class="form-select" name="max_members" required>
                                    <option value="3">3 membres</option>
                                    <option value="4">4 membres</option>
                                    <option value="5">5 membres</option>
                                    <option value="6" selected>6 membres</option>
                                    <option value="8">8 membres</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Matière *</label>
                        <input type="text" class="form-control" name="subject" required 
                               placeholder="ex: Algorithmes et structures de données"
                               list="subjectsList">
                        <datalist id="subjectsList">
                            <option value="Informatique">
                            <option value="Mathématiques">
                            <option value="Physique">
                            <option value="Chimie">
                            <option value="Économie">
                            <option value="Littérature">
                            <option value="Histoire">
                            <option value="Biologie">
                            <option value="Psychologie">
                            <option value="Droit">
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4" 
                                  placeholder="Décrivez l'objectif du groupe, le niveau requis, les méthodes de travail envisagées..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="bi bi-target me-2"></i>Objectifs suggérés :</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="obj1">
                                        <label class="form-check-label" for="obj1">Préparation d'examen</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="obj2">
                                        <label class="form-check-label" for="obj2">Projet de groupe</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="obj3">
                                        <label class="form-check-label" for="obj3">Révisions régulières</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="obj4">
                                        <label class="form-check-label" for="obj4">Aide aux devoirs</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="bi bi-clock me-2"></i>Disponibilités :</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="time1">
                                        <label class="form-check-label" for="time1">Matins (8h-12h)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="time2">
                                        <label class="form-check-label" for="time2">Après-midis (14h-18h)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="time3">
                                        <label class="form-check-label" for="time3">Soirées (18h-22h)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="time4">
                                        <label class="form-check-label" for="time4">Week-ends</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_group" class="btn btn-primary">
                        <i class="bi bi-people me-1"></i>Créer le groupe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Recherche et filtrage des groupes
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchGroups');
    const filterSelect = document.getElementById('filterSubject');
    const groupItems = document.querySelectorAll('.group-item');

    function filterGroups() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedSubject = filterSelect.value.toLowerCase();

        groupItems.forEach(item => {
            const name = item.dataset.name.toLowerCase();
            const subject = item.dataset.subject.toLowerCase();
            
            const matchesSearch = name.includes(searchTerm) || subject.includes(searchTerm);
            const matchesSubject = !selectedSubject || subject.includes(selectedSubject);
            
            if (matchesSearch && matchesSubject) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterGroups);
    filterSelect.addEventListener('change', filterGroups);

    // Auto-complétion pour les objectifs et disponibilités
    const checkboxes = document.querySelectorAll('#createGroupModal .form-check-input');
    const descriptionTextarea = document.querySelector('textarea[name="description"]');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateDescription();
        });
    });

    function updateDescription() {
        const selectedObjectives = [];
        const selectedTimes = [];
        
        // Objectifs
        if (document.getElementById('obj1').checked) selectedObjectives.push('préparation d\'examen');
        if (document.getElementById('obj2').checked) selectedObjectives.push('projet de groupe');
        if (document.getElementById('obj3').checked) selectedObjectives.push('révisions régulières');
        if (document.getElementById('obj4').checked) selectedObjectives.push('aide aux devoirs');
        
        // Disponibilités
        if (document.getElementById('time1').checked) selectedTimes.push('matins');
        if (document.getElementById('time2').checked) selectedTimes.push('après-midis');
        if (document.getElementById('time3').checked) selectedTimes.push('soirées');
        if (document.getElementById('time4').checked) selectedTimes.push('week-ends');
        
        let description = '';
        if (selectedObjectives.length > 0) {
            description += 'Objectifs : ' + selectedObjectives.join(', ') + '. ';
        }
        if (selectedTimes.length > 0) {
            description += 'Disponible en ' + selectedTimes.join(', ') + '.';
        }
        
        if (description && !descriptionTextarea.value) {
            descriptionTextarea.value = description;
        }
    }
});

function viewGroupMembers(groupId) {
    // Faire un appel AJAX pour récupérer les membres
    fetch(`api/group-members.php?group_id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            let membersList = data.map(member => 
                `<div class="d-flex align-items-center mb-2">
                    <i class="bi bi-person-circle fs-4 me-2"></i>
                    <div>
                        <strong>${member.first_name} ${member.last_name}</strong>
                        <br><small class="text-muted">${member.field_of_study || 'Étudiant'}</small>
                    </div>
                </div>`
            ).join('');
            
            smartCampus.showModal('Membres du groupe', membersList);
        });
}

function manageGroup(groupId) {
    smartCampus.showNotification('Fonctionnalité de gestion en cours de développement', 'info');
}

function leaveGroup(groupId) {
    if (confirm('Êtes-vous sûr de vouloir quitter ce groupe ?')) {
        fetch('api/leave-group.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({group_id: groupId})
        }).then(() => location.reload());
    }
}

// Extension pour SmartCampus
if (typeof smartCampus !== 'undefined') {
    smartCampus.showModal = function(title, content) {
        const modalHtml = `
            <div class="modal fade" id="dynamicModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">${content}</div>
                    </div>
                </div>
            </div>
        `;
        
        // Supprimer l'ancien modal s'il existe
        const existingModal = document.getElementById('dynamicModal');
        if (existingModal) existingModal.remove();
        
        // Ajouter le nouveau modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Afficher le modal
        const modal = new bootstrap.Modal(document.getElementById('dynamicModal'));
        modal.show();
        
        // Supprimer le modal après fermeture
        document.getElementById('dynamicModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    };
}
</script>

<?php include 'includes/footer.php'; ?>