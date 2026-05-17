<?php
require_once '../includes/session.php';
require_once '../includes/data.php';

header('Content-Type: application/json');

if (!est_connecte() || $_SESSION['role'] !== 'admin') {
    echo json_encode(['succes' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['user_id'])) {
    echo json_encode(['succes' => false, 'message' => 'ID manquant']);
    exit;
}

$id = intval($input['user_id']);
if ($id === $_SESSION['user_id']) {
    echo json_encode(['succes' => false, 'message' => 'Impossible de se bloquer soi-même']);
    exit;
}

$user = trouver_utilisateur_par_id($id);
if (!$user) {
    echo json_encode(['succes' => false, 'message' => 'Utilisateur introuvable']);
    exit;
}

$nouvel_etat = !$user['actif'];
mettre_a_jour_utilisateur($id, ['actif' => $nouvel_etat]);

$message = $nouvel_etat ? 'Utilisateur débloqué avec succès.' : 'Utilisateur bloqué avec succès.';

echo json_encode([
    'succes' => true,
    'actif'  => $nouvel_etat,
    'message'=> $message
]);
