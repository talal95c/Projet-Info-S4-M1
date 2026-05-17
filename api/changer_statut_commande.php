<?php
// Transitions de statut côté restaurateur :
// en_attente → a_preparer → prete → en_livraison

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');

if (!est_connecte()) {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Vous devez être connecté.']);
    exit;
}

if (!in_array(get_role(), ['restaurateur', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Action réservée au restaurateur.']);
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
$livreur_id  = intval($donnees['livreur_id']  ?? 0);

if ($commande_id <= 0) {
    echo json_encode(['succes' => false, 'message' => 'Identifiant de commande invalide.']);
    exit;
}

$actions_autorisees = ['demarrer_preparation', 'marquer_prete', 'assigner_livreur'];
if (!in_array($action, $actions_autorisees, true)) {
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

$transitions = [
    'demarrer_preparation' => ['de' => 'en_attente', 'vers' => 'a_preparer',  'libelle' => 'préparation démarrée'],
    'marquer_prete'        => ['de' => 'a_preparer', 'vers' => 'prete',       'libelle' => 'marquée prête'],
    'assigner_livreur'     => ['de' => 'prete',      'vers' => 'en_livraison','libelle' => 'assignée et passée en livraison'],
];

$t = $transitions[$action];

if ($commande['statut'] !== $t['de']) {
    echo json_encode([
        'succes'  => false,
        'message' => 'Transition impossible : la commande est actuellement "' . $commande['statut']
                    . '" et doit être "' . $t['de'] . '" pour cette action.',
    ]);
    exit;
}

$maj = ['statut' => $t['vers']];

if ($action === 'assigner_livreur') {
    if ($livreur_id <= 0) {
        echo json_encode(['succes' => false, 'message' => 'Veuillez choisir un livreur.']);
        exit;
    }
    $livreur = trouver_utilisateur_par_id($livreur_id);
    if (!$livreur || $livreur['role'] !== 'livreur' || !$livreur['actif']) {
        echo json_encode(['succes' => false, 'message' => 'Livreur invalide ou inactif.']);
        exit;
    }
    $maj['livreur_id'] = $livreur_id;
}

mettre_a_jour_commande($commande_id, $maj);

echo json_encode([
    'succes'         => true,
    'commande_id'    => $commande_id,
    'nouveau_statut' => $t['vers'],
    'livreur_id'     => $maj['livreur_id'] ?? null,
    'message'        => 'Commande #' . $commande_id . ' ' . $t['libelle'] . '.',
]);
