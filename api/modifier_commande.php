<?php
/*
 * api/modifier_commande.php
 * ---------------------------------------------------------------
 * Modification d'une commande payée mais pas encore en préparation.
 *
 * Deux cas possibles :
 *   1. Nouveau total <= montant payé → modification directe (les articles
 *      sont mis à jour immédiatement, la différence est perdue pour le client).
 *   2. Nouveau total > montant payé → on sauvegarde la modification en
 *      "attente de supplément" et on retourne les infos pour que le JS
 *      redirige vers paiement_supplement.php.
 *
 * Dépendances : includes/session.php, includes/data.php
 */

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

$commande_id = intval($donnees['commande_id'] ?? 0);
$articles    = $donnees['articles']            ?? [];

if ($commande_id <= 0) {
    echo json_encode(['succes' => false, 'message' => 'Identifiant de commande invalide.']);
    exit;
}

if (!is_array($articles) || empty($articles)) {
    echo json_encode(['succes' => false, 'message' => 'La commande ne peut pas être vide.']);
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

if (empty($commande['paiement_effectue'])) {
    echo json_encode(['succes' => false, 'message' => 'Cette commande n\'a pas encore été payée.']);
    exit;
}

if (!in_array($commande['statut'], ['a_preparer', 'en_attente'], true)) {
    echo json_encode([
        'succes'  => false,
        'message' => 'Modification impossible : la commande est déjà ' . $commande['statut'] . '.',
    ]);
    exit;
}

// Calcul du nouveau total et construction du tableau articles propre
$articles_propres = [];
$nouveau_total = 0.0;

foreach ($articles as $a) {
    $quantite = intval($a['quantite'] ?? 0);
    if ($quantite <= 0) continue;

    if (isset($a['menu_id'])) {
        $menu_id = intval($a['menu_id']);
        $menu = trouver_menu_par_id($menu_id);
        if (!$menu) {
            echo json_encode(['succes' => false, 'message' => 'Menu #' . $menu_id . ' introuvable.']);
            exit;
        }
        $articles_propres[] = ['menu_id' => $menu_id, 'quantite' => $quantite];
        $nouveau_total += $menu['prix_total'] * $quantite;
    } else {
        $plat_id = intval($a['plat_id'] ?? 0);
        if ($plat_id <= 0) {
            echo json_encode(['succes' => false, 'message' => 'Article invalide.']);
            exit;
        }
        $plat = trouver_plat_par_id($plat_id);
        if (!$plat) {
            echo json_encode(['succes' => false, 'message' => 'Plat #' . $plat_id . ' introuvable.']);
            exit;
        }
        $articles_propres[] = ['plat_id' => $plat_id, 'quantite' => $quantite];
        $nouveau_total += $plat['prix'] * $quantite;
    }
}

if (empty($articles_propres)) {
    echo json_encode(['succes' => false, 'message' => 'La commande ne peut pas être vide.']);
    exit;
}

$nouveau_total = round($nouveau_total, 2);
$total_paye    = (float)$commande['total'];

// --- CAS 1 : le nouveau total dépasse le montant payé ---
// On enregistre la modification "en attente de supplément" et on
// retourne les infos pour que le JS redirige vers paiement_supplement.php.
if ($nouveau_total > $total_paye) {
    $supplement = round($nouveau_total - $total_paye, 2);

    // On stocke la modification en attente dans la commande
    mettre_a_jour_commande($commande_id, [
        'modification_en_attente' => [
            'articles'           => $articles_propres,
            'nouveau_total'      => $nouveau_total,
            'supplement_montant' => $supplement,
            'date'               => date('Y-m-d\TH:i:s'),
        ],
    ]);

    echo json_encode([
        'succes'             => true,
        'supplement'         => true,
        'supplement_montant' => $supplement,
        'commande_id'        => $commande_id,
        'message'            => 'Un supplément de ' . number_format($supplement, 2, ',', ' ') . ' € est requis.',
    ]);
    exit;
}

// --- CAS 2 : le nouveau total est inférieur ou égal au montant payé ---
// Modification directe sans paiement supplémentaire.
$perte = round($total_paye - $nouveau_total, 2);

mettre_a_jour_commande($commande_id, [
    'articles'          => $articles_propres,
    'total_effectif'    => $nouveau_total,
    'perte_client'      => $perte,
    'date_modification' => date('Y-m-d\TH:i:s'),
]);

$message = $perte > 0
    ? 'Commande modifiée. La différence de ' . number_format($perte, 2, ',', ' ') . ' € ne sera pas remboursée.'
    : 'Commande modifiée avec succès.';

echo json_encode([
    'succes'        => true,
    'supplement'    => false,
    'commande_id'   => $commande_id,
    'nouveau_total' => $nouveau_total,
    'total_paye'    => $total_paye,
    'perte'         => $perte,
    'message'       => $message,
]);
