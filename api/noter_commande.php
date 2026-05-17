<?php
require_once '../includes/session.php';
require_once '../includes/data.php';

header('Content-Type: application/json');

if (!est_connecte() || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['commande_id'], $input['note_livraison'], $input['note_produits'])) {
    http_response_code(404);
    echo json_encode(['succes' => false, 'message' => 'Données manquantes']);
    exit;
}

$commande_id = intval($input['commande_id']);
$note_livraison = intval($input['note_livraison']);
$note_produits = intval($input['note_produits']);
$commentaire_livraison = trim($input['commentaire_livraison'] ?? '');
$commentaire_produits = trim($input['commentaire_produits'] ?? '');

if ($note_livraison < 1 || $note_livraison > 5 || $note_produits < 1 || $note_produits > 5) {
    echo json_encode(['succes' => false, 'message' => 'Les notes doivent être entre 1 et 5']);
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

if ($commande['client_id'] !== $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Cette commande ne vous appartient pas']);
    exit;
}

if ($commande['statut'] !== 'livree') {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Vous ne pouvez noter qu\'une commande livrée']);
    exit;
}

if ($commande['avis'] !== null) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Vous avez déjà noté cette commande']);
    exit;
}

$avis = [
    'note_livraison' => $note_livraison,
    'commentaire_livraison' => $commentaire_livraison,
    'note_produits' => $note_produits,
    'commentaire_produits' => $commentaire_produits,
    'date' => date('Y-m-d H:i:s')
];

$commandes[$commande_index]['avis'] = $avis;
ecrire_json('commandes.json', $commandes);

echo json_encode([
    'succes' => true,
    'message' => 'Votre avis a été posté avec succès ! Merci.'
]);
