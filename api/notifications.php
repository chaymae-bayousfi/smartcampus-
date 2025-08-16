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

$notifications = [];

// Vérifier les devoirs proches de l'échéance
$query = "SELECT title, due_date FROM assignments WHERE user_id = ? AND status = 'pending' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$upcoming_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($upcoming_assignments as $assignment) {
    $days_left = floor((strtotime($assignment['due_date']) - time()) / (60*60*24));
    $message = "Rappel: {$assignment['title']} est à rendre ";
    $message .= $days_left <= 0 ? "aujourd'hui" : "dans {$days_left} jour" . ($days_left > 1 ? 's' : '');
    
    $notifications[] = [
        'message' => $message,
        'type' => $days_left <= 0 ? 'danger' : 'warning'
    ];
}

// Vérifier les dépassements de budget
$query = "SELECT b.total_budget, COALESCE(SUM(e.amount), 0) as spent 
          FROM budgets b 
          LEFT JOIN expenses e ON e.user_id = b.user_id 
          AND MONTH(e.expense_date) = MONTH(CURDATE()) 
          AND YEAR(e.expense_date) = YEAR(CURDATE())
          WHERE b.user_id = ? AND b.month_year = DATE_FORMAT(CURDATE(), '%Y-%m')
          GROUP BY b.id";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$budget_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($budget_data) {
    $percentage = ($budget_data['spent'] / $budget_data['total_budget']) * 100;
    if ($percentage >= 90) {
        $notifications[] = [
            'message' => "Attention: Vous avez dépassé 90% de votre budget mensuel (" . number_format($percentage, 1) . "%)",
            'type' => 'danger'
        ];
    } elseif ($percentage >= 75) {
        $notifications[] = [
            'message' => "Attention: Vous avez atteint " . number_format($percentage, 1) . "% de votre budget mensuel",
            'type' => 'warning'
        ];
    }
}

echo json_encode($notifications);
?>