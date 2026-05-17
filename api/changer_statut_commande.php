<?php
require_once '../includes/session.php';
require_once '../includes/data.php';

header('Content-Type: application/json');

if (!est_connecte() || !in_array($_SESSION['role'], ['restaurateur', 'admin'])) {
    echo json_encode(['succes' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['commande_id'], $input['action'])) {
    echo json_encode(['succes' => false, 'message' => 'Données manquantes']);
    exit;
}

$commande_id = intval($input['commande_id']);
$action      = $input['action'];
$livreur_id  = isset($input['livreur_id']) ? intval($input['livreur_id']) : 0;

$commandes = lire_json('commandes.json');
$commande_index = -1;

for ($i = 0; $i < count($commandes); $i++) {
    if ($commandes[$i]['id'] === $commande_id) {
        $commande_index = $i;
        break;
    }
}

if ($commande_index === -1) {
    echo json_encode(['succes' => false, 'message' => 'Commande introuvable']);
    exit;
}

$statut_actuel = $commandes[$commande_index]['statut'];
$nouveau_statut = $statut_actuel;

if ($action === 'mettre_en_preparation' && $statut_actuel === 'a_preparer') {
    $nouveau_statut = 'en_preparation';
} elseif ($action === 'marquer_prete' && $statut_actuel === 'en_preparation') {
    $nouveau_statut = 'prete';
} elseif ($action === 'marquer_prete' && $statut_actuel === 'a_preparer') {
    // Fallback if needed
    $nouveau_statut = 'prete';
} elseif ($action === 'assigner_livreur' && $statut_actuel === 'prete') {
    if ($livreur_id <= 0) {
        echo json_encode(['succes' => false, 'message' => 'Livreur invalide']);
        exit;
    }
    $nouveau_statut = 'en_livraison';
    $commandes[$commande_index]['livreur_id'] = $livreur_id;
} else {
    echo json_encode(['succes' => false, 'message' => 'Action invalide pour le statut actuel']);
    exit;
}

$commandes[$commande_index]['statut'] = $nouveau_statut;
ecrire_json('commandes.json', $commandes);

echo json_encode([
    'succes' => true,
    'nouveau_statut' => $nouveau_statut,
    'message' => 'Statut mis à jour'
]);
