<?php
/*
 * api/modifier_commande.php
 * ---------------------------------------------------------------
 * Endpoint AJAX permettant à un client de modifier les articles
 * d'une commande déjà payée mais pas encore en préparation
 * (cf. phase 3 du sujet).
 *
 * /!\ Règle métier imposée par le prof d'Abdel /!\
 *   Une commande ne peut donner lieu qu'à UN SEUL paiement.
 *   Conséquence : si la modification fait monter le total
 *   au-dessus de ce qui a été payé initialement, on REFUSE
 *   la modification (sinon il faudrait un second paiement).
 *   Si le total diminue, le client est perdant : aucun
 *   remboursement (comportement explicitement autorisé par
 *   le PDF du sujet, page 9).
 *
 * Méthode : POST (JSON ou form-data)
 * Corps   : {
 *     "commande_id": 42,
 *     "articles":    [{ "plat_id": 1, "quantite": 2 },
 *                     { "menu_id": 3, "quantite": 1 }]
 *   }
 *
 * Réponse : {
 *     "succes":          true,
 *     "nouveau_total":   18.50,
 *     "total_paye":      22.00,
 *     "perte":           3.50,
 *     "message":         "Commande modifiée. Vous êtes perdant de 3.50 €."
 *   }
 *   OU en cas de refus :
 *   {
 *     "succes": false,
 *     "message": "Le nouveau total (25 €) dépasse le montant payé (22 €).
 *                 Un second paiement n'est pas autorisé."
 *   }
 *
 * Sécurité :
 *   - client connecté uniquement
 *   - la commande doit appartenir au client connecté
 *   - statut doit être 'a_preparer' OU 'en_attente' (payée mais
 *     pas encore en cuisine)
 *   - paiement_effectue doit être true
 *   - articles[] doit contenir au moins 1 ligne (on n'autorise
 *     pas une commande vide → annulation à faire séparément)
 *
 * Dépendances : includes/session.php, includes/data.php
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');

/* --- Authentification --------------------------------------- */

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

/* --- Lecture des données entrantes (JSON ou form) ----------- */

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
    echo json_encode(['succes' => false, 'message' => 'La commande ne peut pas être vide. Annulez plutôt la commande.']);
    exit;
}

/* --- Recherche de la commande ------------------------------ */

$commandes = lire_json('commandes.json');
$commande  = null;
$index     = -1;
foreach ($commandes as $i => $c) {
    if ($c['id'] == $commande_id) {
        $commande = $c;
        $index = $i;
        break;
    }
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

/* --- Validation et nettoyage des articles ----------------- */

$articles_propres = [];
$nouveau_total = 0.0;

foreach ($articles as $a) {
    $quantite = intval($a['quantite'] ?? 0);
    if ($quantite <= 0) continue;   // on saute les lignes vidées

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

/* --- /!\ Règle prof : interdiction de second paiement -------
 * Si le nouveau total dépasse ce qui a été payé, on refuse.
 * Le client doit retirer des articles avant de pouvoir valider.
 * -------------------------------------------------------- */

if ($nouveau_total > $total_paye) {
    echo json_encode([
        'succes'  => false,
        'message' => 'Le nouveau total (' . number_format($nouveau_total, 2, ',', ' ') . ' €) dépasse le montant payé ('
                    . number_format($total_paye, 2, ',', ' ') . ' €). Un second paiement n\'est pas autorisé. '
                    . 'Retirez des articles pour rester sous le montant payé.',
        'nouveau_total' => $nouveau_total,
        'total_paye'    => $total_paye,
    ]);
    exit;
}

/* --- Mise à jour de la commande ---------------------------- */
/* On garde le total payé inchangé (c'est ce que le client a
   réellement déboursé) mais on enregistre le nouveau total
   "effectif" dans un champ séparé. Le champ 'perte' permet de
   tracer combien le client a perdu en retirant des articles. */

$perte = round($total_paye - $nouveau_total, 2);

mettre_a_jour_commande($commande_id, [
    'articles'         => $articles_propres,
    'total_effectif'   => $nouveau_total,
    'perte_client'     => $perte,
    'date_modification'=> date('Y-m-d\TH:i:s'),
]);

$message = $perte > 0
    ? 'Commande modifiée. Vous êtes perdant de ' . number_format($perte, 2, ',', ' ')
        . ' € (pas de remboursement).'
    : 'Commande modifiée.';

echo json_encode([
    'succes'         => true,
    'commande_id'    => $commande_id,
    'nouveau_total'  => $nouveau_total,
    'total_paye'     => $total_paye,
    'perte'          => $perte,
    'message'        => $message,
]);
