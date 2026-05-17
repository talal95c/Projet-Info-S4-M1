<?php
// Enregistre l'avis du client sur une commande livrée (une seule fois).

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');

if (!est_connecte()) {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Vous devez être connecté.']);
    exit;
}

if (get_role() !== 'client') {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Action réservée aux clients.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['succes' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$donnees = $_POST;
if (empty($donnees)) {
    $brut = file_get_contents('php://input');
    if ($brut) {
        $decode = json_decode($brut, true);
        if (is_array($decode)) $donnees = $decode;
    }
}

$commande_id           = intval($donnees['commande_id']            ?? 0);
$note_livraison        = intval($donnees['note_livraison']         ?? 0);
$commentaire_livraison = trim(  $donnees['commentaire_livraison']  ?? '');
$note_produits         = intval($donnees['note_produits']          ?? 0);
$commentaire_produits  = trim(  $donnees['commentaire_produits']   ?? '');

if ($commande_id <= 0) {
    echo json_encode(['succes' => false, 'message' => 'Identifiant de commande invalide.']);
    exit;
}
if ($note_livraison < 1 || $note_livraison > 5) {
    echo json_encode(['succes' => false, 'message' => 'La note de livraison doit être entre 1 et 5.']);
    exit;
}
if ($note_produits < 1 || $note_produits > 5) {
    echo json_encode(['succes' => false, 'message' => 'La note des produits doit être entre 1 et 5.']);
    exit;
}
if (strlen($commentaire_livraison) > 500 || strlen($commentaire_produits) > 500) {
    echo json_encode(['succes' => false, 'message' => 'Commentaire trop long (max 500 caractères).']);
    exit;
}

$commandes = lire_json('commandes.json');
$commande = null;
foreach ($commandes as $c) {
    if ($c['id'] == $commande_id) { $commande = $c; break; }
}

if (!$commande) {
    echo json_encode(['succes' => false, 'message' => 'Commande introuvable.']);
    exit;
}

if ($commande['client_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Cette commande ne vous appartient pas.']);
    exit;
}

if ($commande['statut'] !== 'livree') {
    echo json_encode(['succes' => false, 'message' => 'Vous ne pouvez noter qu\'une commande livrée.']);
    exit;
}

if (!empty($commande['avis'])) {
    echo json_encode(['succes' => false, 'message' => 'Cette commande a déjà été notée.']);
    exit;
}

$avis = [
    'note_livraison'        => $note_livraison,
    'commentaire_livraison' => $commentaire_livraison,
    'note_produits'         => $note_produits,
    'commentaire_produits'  => $commentaire_produits,
    'date'                  => date('Y-m-d H:i:s'),
];

mettre_a_jour_commande($commande_id, ['avis' => $avis]);

echo json_encode([
    'succes'  => true,
    'message' => 'Merci pour votre avis sur la commande #' . $commande_id . ' !',
    'avis'    => $avis,
]);
