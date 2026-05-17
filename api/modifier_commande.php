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

if (!$input || !isset($input['commande_id'], $input['articles'])) {
    http_response_code(404);
    echo json_encode(['succes' => false, 'message' => 'Données manquantes']);
    exit;
}

$commande_id = intval($input['commande_id']);
$nouveaux_articles = $input['articles'];

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

if ($commande['statut'] !== 'en_attente') {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'La commande ne peut plus être modifiée (déjà en préparation)']);
    exit;
}

// Calcul du nouveau total
$nouveau_total = 0;
foreach ($nouveaux_articles as $article) {
    if (isset($article['menu_id'])) {
        $menu = trouver_menu_par_id($article['menu_id']);
        if ($menu) $nouveau_total += $menu['prix_total'] * $article['quantite'];
    } elseif (isset($article['plat_id'])) {
        $plat = trouver_plat_par_id($article['plat_id']);
        if ($plat) $nouveau_total += $plat['prix'] * $article['quantite'];
    }
}

// Appliquer la remise du client si elle existe
$user = trouver_utilisateur_par_id($_SESSION['user_id']);
$remise = intval($user['remise'] ?? 0);
if ($remise > 0) {
    $nouveau_total = $nouveau_total * (1 - $remise / 100);
}

if ($nouveau_total > $commande['total']) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Le nouveau total dépasse le montant déjà payé']);
    exit;
}

// Enregistrement
$commandes[$commande_index]['articles'] = $nouveaux_articles;
$commandes[$commande_index]['nouveau_total'] = $nouveau_total; // Optionnel : on peut garder trace du nouveau calcul
ecrire_json('commandes.json', $commandes);

echo json_encode([
    'succes' => true,
    'message' => 'Commande modifiée avec succès.'
]);
