<?php
// Marque une livraison comme effectuée ou abandonnée par le livreur.

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');

if (!est_connecte()) {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Vous devez être connecté.']);
    exit;
}

if (get_role() !== 'livreur') {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Action réservée aux livreurs.']);
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

$commande_id = intval($donnees['commande_id'] ?? 0);
$action      = $donnees['action']             ?? '';

if ($commande_id <= 0) {
    echo json_encode(['succes' => false, 'message' => 'Identifiant de commande invalide.']);
    exit;
}

if (!in_array($action, ['livree', 'abandonnee'], true)) {
    echo json_encode(['succes' => false, 'message' => 'Action invalide.']);
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

if ($commande['livreur_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Cette livraison ne vous est pas attribuée.']);
    exit;
}

if ($commande['statut'] !== 'en_livraison') {
    echo json_encode(['succes' => false, 'message' => 'Statut actuel : ' . $commande['statut'] . '. Impossible de modifier.']);
    exit;
}

mettre_a_jour_commande($commande_id, [
    'statut'   => $action,
    'date_fin' => date('Y-m-d\TH:i:s'),
]);

echo json_encode([
    'succes'  => true,
    'statut'  => $action,
    'message' => $action === 'livree'
        ? '✅ Livraison #' . $commande_id . ' marquée comme effectuée.'
        : '❌ Livraison #' . $commande_id . ' marquée comme abandonnée.',
]);
