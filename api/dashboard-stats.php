<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];

$stats = [];

// Nombre de devoirs en cours
$query = "SELECT COUNT(*) as count FROM assignments WHERE user_id = ? AND status = 'pending' AND due_date >= CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['pending-assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Dépenses du mois actuel
$query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = ? AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['monthly-expenses'] = number_format($stmt->fetch(PDO::FETCH_ASSOC)['total'], 0, ',', ' ');

// Groupes d'étude actifs
$query = "SELECT COUNT(*) as count FROM group_members gm JOIN study_groups sg ON gm.group_id = sg.id WHERE gm.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['active-groups'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo json_encode($stats);
?>