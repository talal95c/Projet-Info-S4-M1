<?php
require_once '../includes/session.php';
require_once '../includes/data.php';

header('Content-Type: application/json');

if (!est_connecte() || $_SESSION['role'] !== 'livreur') {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['commande_id'], $input['action'])) {
    http_response_code(404);
    echo json_encode(['succes' => false, 'message' => 'Données manquantes']);
    exit;
}

$commande_id = intval($input['commande_id']);
$action = $input['action'];

if (!in_array($action, ['livree', 'abandonnee'])) {
    echo json_encode(['succes' => false, 'message' => 'Action invalide']);
    exit;
}

$commandes = lire_json('commandes.json');
$commande_index = -1;

for ($i = 0; $i < count($commandes); $i++) {
    if ($commandes[$i]['id'] === $commande_id) {
        $commande_index = $i;
        break;
    }
}

if ($commande_index === -1) {
    http_response_code(404);
    echo json_encode(['succes' => false, 'message' => 'Commande introuvable']);
    exit;
}

$commande = $commandes[$commande_index];

if ($commande['livreur_id'] !== $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Cette commande ne vous est pas assignée']);
    exit;
}

if ($commande['statut'] !== 'en_livraison') {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'La commande n\'est pas en cours de livraison']);
    exit;
}

$commandes[$commande_index]['statut'] = $action;
ecrire_json('commandes.json', $commandes);

echo json_encode([
    'succes' => true,
    'message' => $action === 'livree' 
        ? '✅ Commande marquée comme livrée !' 
        : '❌ Commande marquée comme abandonnée.'
]);
