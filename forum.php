<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];

// Traitement des formulaires
if ($_POST) {
    if (isset($_POST['create_post'])) {
        $query = "INSERT INTO forum_posts (user_id, title, content, category) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $userId,
            $_POST['title'],
            $_POST['content'],
            $_POST['category']
        ]);
    } elseif (isset($_POST['add_reply'])) {
        $query = "INSERT INTO forum_replies (post_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $_POST['post_id'],
            $userId,
            $_POST['content']
        ]);
    }
}

// Récupération des posts avec informations utilisateur et nombre de réponses
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT fp.*, u.first_name, u.last_name, u.field_of_study,
                 COUNT(fr.id) as reply_count,
                 MAX(COALESCE(fr.created_at, fp.created_at)) as last_activity
          FROM forum_posts fp 
          LEFT JOIN users u ON fp.user_id = u.id
          LEFT JOIN forum_replies fr ON fp.id = fr.post_id";

$conditions = [];
$params = [];

if ($category) {
    $conditions[] = "fp.category = ?";
    $params[] = $category;
}

if ($search) {
    $conditions[] = "(fp.title LIKE ? OR fp.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " GROUP BY fp.id ORDER BY last_activity DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'un post spécifique si demandé
$viewPost = null;
$replies = [];
if (isset($_GET['post_id'])) {
    $postId = $_GET['post_id'];
    
    // Post principal
    $query = "SELECT fp.*, u.first_name, u.last_name, u.field_of_study, u.academic_year
              FROM forum_posts fp 
              LEFT JOIN users u ON fp.user_id = u.id 
              WHERE fp.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$postId]);
    $viewPost = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Réponses
    if ($viewPost) {
        $query = "SELECT fr.*, u.first_name, u.last_name, u.field_of_study, u.academic_year
                  FROM forum_replies fr 
                  LEFT JOIN users u ON fr.user_id = u.id 
                  WHERE fr.post_id = ? 
                  ORDER BY fr.created_at ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([$postId]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Catégories du forum
$categories = [
    'question' => ['name' => 'Questions', 'icon' => 'question-circle', 'color' => 'primary'],
    'discussion' => ['name' => 'Discussions', 'icon' => 'chat-dots', 'color' => 'success'],
    'bon-plan' => ['name' => 'Bons plans', 'icon' => 'star', 'color' => 'warning'],
    'evenement' => ['name' => 'Événements', 'icon' => 'calendar-event', 'color' => 'info']
];

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-chat-dots me-2"></i>Forum étudiant
                <small class="text-muted">Entraide et discussions communautaires</small>
            </h1>
        </div>
    </div>

    <?php if (!$viewPost): ?>
    <!-- Vue principale du forum -->
    <div class="row g-4">
        <div class="col-lg-9">
            <!-- Barre de recherche et filtres -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= escape($search) ?>" placeholder="Rechercher dans les posts...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="category">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $key => $cat): ?>
                                <option value="<?= $key ?>" <?= $category === $key ? 'selected' : '' ?>>
                                    <?= $cat['name'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Liste des posts -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?= $category ? $categories[$category]['name'] : 'Tous les posts' ?>
                        <span class="badge bg-secondary ms-2"><?= count($posts) ?></span>
                    </h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                        <i class="bi bi-plus-lg"></i> Nouveau post
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($posts)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-chat-square-dots fs-1 text-muted mb-3"></i>
                        <h5>Aucun post trouvé</h5>
                        <p class="text-muted">
                            <?= $search || $category ? 'Essayez de modifier vos critères de recherche.' : 'Soyez le premier à lancer une discussion !' ?>
                        </p>
                        <?php if (!$search && !$category): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                            Créer le premier post
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($posts as $post): ?>
                        <div class="list-group-item forum-post">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="avatar bg-<?= $categories[$post['category']]['color'] ?> text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 50px; height: 50px;">
                                        <i class="bi bi-<?= $categories[$post['category']]['icon'] ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="?post_id=<?= $post['id'] ?>" class="text-decoration-none">
                                                    <?= escape($post['title']) ?>
                                                </a>
                                            </h6>
                                            <p class="mb-2 text-muted">
                                                <?= escape(substr($post['content'], 0, 200)) ?><?= strlen($post['content']) > 200 ? '...' : '' ?>
                                            </p>
                                            <div class="forum-meta d-flex align-items-center gap-3">
                                                <span class="badge bg-<?= $categories[$post['category']]['color'] ?>">
                                                    <?= $categories[$post['category']]['name'] ?>
                                                </span>
                                                <span>
                                                    <i class="bi bi-person me-1"></i>
                                                    <?= escape($post['first_name'] . ' ' . $post['last_name']) ?>
                                                </span>
                                                <?php if ($post['field_of_study']): ?>
                                                <span>
                                                    <i class="bi bi-book me-1"></i>
                                                    <?= escape($post['field_of_study']) ?>
                                                </span>
                                                <?php endif; ?>
                                                <span>
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($post['created_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="mb-1">
                                                <span class="badge bg-light text-dark">
                                                    <i class="bi bi-chat me-1"></i><?= $post['reply_count'] ?>
                                                </span>
                                            </div>
                                            <?php if ($post['last_activity'] !== $post['created_at']): ?>
                                            <small class="text-muted">
                                                Dernière activité<br>
                                                <?= date('d/m H:i', strtotime($post['last_activity'])) ?>
                                            </small>
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
            </div>
        </div>

        <div class="col-lg-3">
            <!-- Statistiques du forum -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Statistiques</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Compter les posts par catégorie
                    $query = "SELECT category, COUNT(*) as count FROM forum_posts GROUP BY category";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $categoryStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    ?>
                    
                    <?php foreach ($categories as $key => $cat): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <i class="bi bi-<?= $cat['icon'] ?> text-<?= $cat['color'] ?> me-2"></i>
                            <?= $cat['name'] ?>
                        </div>
                        <span class="badge bg-<?= $cat['color'] ?>">
                            <?= $categoryStats[$key] ?? 0 ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Règles de la communauté -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-shield-check me-2"></i>Règles de la communauté
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small text-muted">
                        <div class="mb-2">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Recherchez avant de poster
                        </div>
                        <div class="mb-2">
                            <i class="bi bi-x-circle text-danger me-1"></i>
                            Pas de spam ou contenu inapproprié
                        </div>
                        <div>
                            <i class="bi bi-heart text-primary me-1"></i>
                            Aidez-vous mutuellement !
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Vue détaillée d'un post -->
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="forum.php">Forum</a></li>
                    <li class="breadcrumb-item">
                        <a href="forum.php?category=<?= $viewPost['category'] ?>">
                            <?= $categories[$viewPost['category']]['name'] ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active"><?= escape($viewPost['title']) ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Post principal -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-2"><?= escape($viewPost['title']) ?></h5>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-<?= $categories[$viewPost['category']]['color'] ?>">
                                    <i class="bi bi-<?= $categories[$viewPost['category']]['icon'] ?> me-1"></i>
                                    <?= $categories[$viewPost['category']]['name'] ?>
                                </span>
                                <span class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('d/m/Y à H:i', strtotime($viewPost['created_at'])) ?>
                                </span>
                                <span class="text-muted">
                                    <i class="bi bi-chat me-1"></i>
                                    <?= count($replies) ?> réponse<?= count($replies) > 1 ? 's' : '' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px;">
                                <i class="bi bi-person fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="mb-3">
                                <h6 class="mb-1"><?= escape($viewPost['first_name'] . ' ' . $viewPost['last_name']) ?></h6>
                                <div class="text-muted small">
                                    <?php if ($viewPost['field_of_study']): ?>
                                    <i class="bi bi-book me-1"></i><?= escape($viewPost['field_of_study']) ?>
                                    <?php endif; ?>
                                    <?php if ($viewPost['academic_year']): ?>
                                    - Année <?= $viewPost['academic_year'] ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="post-content">
                                <?= nl2br(escape($viewPost['content'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Réponses -->
            <?php if (!empty($replies)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-chat-left-text me-2"></i>
                        <?= count($replies) ?> réponse<?= count($replies) > 1 ? 's' : '' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($replies as $index => $reply): ?>
                    <div class="reply-item <?= $index > 0 ? 'border-top pt-3' : '' ?>">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avatar bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 45px; height: 45px;">
                                    <i class="bi bi-person"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?= escape($reply['first_name'] . ' ' . $reply['last_name']) ?></h6>
                                        <div class="text-muted small">
                                            <?php if ($reply['field_of_study']): ?>
                                            <i class="bi bi-book me-1"></i><?= escape($reply['field_of_study']) ?>
                                            <?php endif; ?>
                                            <?php if ($reply['academic_year']): ?>
                                            - Année <?= $reply['academic_year'] ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?>
                                    </small>
                                </div>
                                <div class="reply-content">
                                    <?= nl2br(escape($reply['content'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($index < count($replies) - 1): ?>
                    <hr class="my-3">
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulaire de réponse -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-reply me-2"></i>Répondre à ce post
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="post_id" value="<?= $viewPost['id'] ?>">
                        <div class="mb-3">
                            <textarea class="form-control" name="content" rows="4" 
                                      placeholder="Écrivez votre réponse..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Soyez constructif et respectueux dans vos réponses
                            </small>
                            <button type="submit" name="add_reply" class="btn btn-success">
                                <i class="bi bi-send me-1"></i>Publier la réponse
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="forum.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-1"></i>Retour au forum
                        </a>
                        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#createPostModal">
                            <i class="bi bi-plus-lg me-1"></i>Nouveau post
                        </button>
                        <?php if ($viewPost['user_id'] == $userId): ?>
                        <button class="btn btn-outline-warning" onclick="editPost(<?= $viewPost['id'] ?>)">
                            <i class="bi bi-pencil me-1"></i>Modifier
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Auteur du post -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">À propos de l'auteur</h6>
                </div>
                <div class="card-body text-center">
                    <div class="avatar bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="bi bi-person fs-1"></i>
                    </div>
                    <h6><?= escape($viewPost['first_name'] . ' ' . $viewPost['last_name']) ?></h6>
                    <?php if ($viewPost['field_of_study']): ?>
                    <p class="text-muted mb-2">
                        <i class="bi bi-book me-1"></i>
                        <?= escape($viewPost['field_of_study']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($viewPost['academic_year']): ?>
                    <p class="text-muted">
                        <i class="bi bi-mortarboard me-1"></i>
                        Année <?= $viewPost['academic_year'] ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Créer un post -->
<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-chat-square-text me-2"></i>Créer un nouveau post
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Conseil :</strong> Choisissez un titre clair et descriptif. Plus votre post est précis, 
                        plus vous aurez de chances d'obtenir des réponses utiles !
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catégorie *</label>
                        <select class="form-select" name="category" required>
                            <?php foreach ($categories as $key => $cat): ?>
                            <option value="<?= $key ?>">
                                <?= $cat['name'] ?> - 
                                <?php
                                switch($key) {
                                    case 'question': echo 'Pour poser des questions et demander de l\'aide'; break;
                                    case 'discussion': echo 'Pour échanger et débattre sur divers sujets'; break;
                                    case 'bon-plan': echo 'Pour partager des astuces et bons plans'; break;
                                    case 'evenement': echo 'Pour annoncer ou rechercher des événements'; break;
                                }
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Titre *</label>
                        <input type="text" class="form-control" name="title" required 
                               placeholder="ex: Comment réviser efficacement les algorithmes ?">
                        <div class="form-text">Soyez précis et utilisez des mots-clés pertinents</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contenu *</label>
                        <textarea class="form-control" name="content" rows="8" required
                                  placeholder="Décrivez votre question, sujet de discussion, bon plan ou événement en détail..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="bi bi-question-circle me-2"></i>Pour une question :</h6>
                                    <ul class="small mb-0">
                                        <li>Contexte et niveau d'études</li>
                                        <li>Ce que vous avez déjà essayé</li>
                                        <li>Point précis où vous bloquez</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="bi bi-star me-2"></i>Pour un bon plan :</h6>
                                    <ul class="small mb-0">
                                        <li>Détails pratiques (lieu, prix, durée...)</li>
                                        <li>Votre retour d'expérience</li>
                                        <li>À qui cela peut intéresser</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_post" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Publier le post
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.forum-post {
    transition: all 0.2s ease;
}

.forum-post:hover {
    background-color: #f8f9fa;
    border-left: 4px solid var(--bs-primary);
}

.avatar {
    font-size: 1.2rem;
}

.reply-item {
    margin-bottom: 1.5rem;
}

.post-content, .reply-content {
    line-height: 1.6;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: #6c757d;
}
</style>

<script>
function editPost(postId) {
    smartCampus.showNotification('Fonctionnalité de modification en cours de développement', 'info');
}

// Auto-expansion du textarea
document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });

    // Suggestions de titre basées sur la catégorie
    const categorySelect = document.querySelector('select[name="category"]');
    const titleInput = document.querySelector('input[name="title"]');
    
    if (categorySelect && titleInput) {
        categorySelect.addEventListener('change', function() {
            const suggestions = {
                'question': ['Comment...?', 'Besoin d\'aide pour...', 'Quelqu\'un sait...?'],
                'discussion': ['Que pensez-vous de...?', 'Débat :', 'Votre avis sur...'],
                'bon-plan': ['Bon plan :', 'Astuce :', 'Recommandation :'],
                'evenement': ['Événement :', 'Recherche partenaires pour...', 'Qui vient à...?']
            };
            
            const placeholder = suggestions[this.value] ? 
                suggestions[this.value][Math.floor(Math.random() * suggestions[this.value].length)] :
                'Titre de votre post';
                
            titleInput.placeholder = placeholder;
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>-success me-1"></i>
                            Soyez respectueux et bienveillants
                        </div>
                        <div class="mb-2">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Utilisez les bonnes catégories
                        </div>
                        <div class="mb-2">
                            <i class="bi bi-check-circle text