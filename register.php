<?php
require_once 'config/database.php';

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $university = trim($_POST['university']);
    $field_of_study = trim($_POST['field_of_study']);
    $academic_year = (int)$_POST['academic_year'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérification de l'unicité
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Ce nom d\'utilisateur ou cette adresse email existe déjà.';
        } else {
            // Insertion du nouvel utilisateur
            $query = "INSERT INTO users (username, email, password, first_name, last_name, university, field_of_study, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $university, $field_of_study, $academic_year])) {
                $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
                
                // Auto-connexion
                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Erreur lors de l\'inscription. Veuillez réessayer.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Inscription</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= escape($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= escape($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= escape($_POST['first_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= escape($_POST['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= escape($_POST['username'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Adresse email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= escape($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Au moins 6 caractères</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="university" class="form-label">Université</label>
                            <input type="text" class="form-control" id="university" name="university" 
                                   value="<?= escape($_POST['university'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="field_of_study" class="form-label">Domaine d'études</label>
                                    <input type="text" class="form-control" id="field_of_study" name="field_of_study" 
                                           value="<?= escape($_POST['field_of_study'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label">Année d'études</label>
                                    <select class="form-select" id="academic_year" name="academic_year">
                                        <option value="1" <?= ($_POST['academic_year'] ?? '') == '1' ? 'selected' : '' ?>>1ère année</option>
                                        <option value="2" <?= ($_POST['academic_year'] ?? '') == '2' ? 'selected' : '' ?>>2ème année</option>
                                        <option value="3" <?= ($_POST['academic_year'] ?? '') == '3' ? 'selected' : '' ?>>3ème année</option>
                                        <option value="4" <?= ($_POST['academic_year'] ?? '') == '4' ? 'selected' : '' ?>>4ème année</option>
                                        <option value="5" <?= ($_POST['academic_year'] ?? '') == '5' ? 'selected' : '' ?>>5ème année</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>S'inscrire
                            </button>
                        </div>
                    </form>
                    
                    <hr>
                    <div class="text-center">
                        <p class="mb-0">Déjà un compte ? 
                            <a href="login.php" class="text-decoration-none">Se connecter</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>