<?php
/*
 * api/favoris.php
 * ---------------------------------------------------------------
 * Endpoint AJAX pour ajouter ou retirer un menu des favoris du
 * client connecté.
 *
 * Méthode : POST
 * Corps JSON : { "menu_id": <int> }
 *
 * Retour JSON :
 *   { "succes": true, "favori": true/false, "menu_id": <int> }
 *   - favori = true  → le menu vient d'être ajouté aux favoris
 *   - favori = false → le menu vient d'être retiré des favoris
 *
 * Les favoris sont stockés dans le champ "favoris" de l'utilisateur
 * dans utilisateurs.json (tableau d'entiers : les ids de menus).
 *
 * Accès : client connecté uniquement.
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
    echo json_encode(['succes' => false, 'message' => 'Méthode non autorisée (POST attendu).']);
    exit;
}

// Lecture des données
$donnees = $_POST;
if (empty($donnees)) {
    $brut = file_get_contents('php://input');
    if ($brut) {
        $decode = json_decode($brut, true);
        if (is_array($decode)) $donnees = $decode;
    }
}

$menu_id = intval($donnees['menu_id'] ?? 0);
$plat_id = intval($donnees['plat_id'] ?? 0);

if ($menu_id <= 0 && $plat_id <= 0) {
    echo json_encode(['succes' => false, 'message' => 'Identifiant invalide.']);
    exit;
}

$user = trouver_utilisateur_par_id($_SESSION['user_id']);

if ($menu_id > 0) {
    // Vérifier que le menu existe
    $menu = trouver_menu_par_id($menu_id);
    if (!$menu) {
        echo json_encode(['succes' => false, 'message' => 'Menu introuvable.']);
        exit;
    }

    // Récupérer les favoris actuels de l'utilisateur
    $favoris = $user['favoris'] ?? [];

    // Toggle : ajouter ou retirer
    $est_favori = in_array($menu_id, $favoris);

    if ($est_favori) {
        // Retirer des favoris
        $favoris = array_values(array_filter($favoris, fn($id) => $id !== $menu_id));
        $nouveau_statut = false;
    } else {
        // Ajouter aux favoris
        $favoris[] = $menu_id;
        $nouveau_statut = true;
    }

    // Sauvegarder
    mettre_a_jour_utilisateur($_SESSION['user_id'], ['favoris' => $favoris]);

    echo json_encode([
        'succes'  => true,
        'favori'  => $nouveau_statut,
        'menu_id' => $menu_id,
        'message' => $nouveau_statut
            ? htmlspecialchars($menu['nom']) . ' ajouté à vos favoris.'
            : htmlspecialchars($menu['nom']) . ' retiré de vos favoris.',
    ]);
    exit;
} else {
    // Vérifier que le plat existe
    $plat = trouver_plat_par_id($plat_id);
    if (!$plat) {
        echo json_encode(['succes' => false, 'message' => 'Plat introuvable.']);
        exit;
    }

    // Récupérer les favoris plats actuels de l'utilisateur
    $favoris_plats = $user['favoris_plats'] ?? [];

    // Toggle : ajouter ou retirer
    $est_favori = in_array($plat_id, $favoris_plats);

    if ($est_favori) {
        // Retirer des favoris
        $favoris_plats = array_values(array_filter($favoris_plats, fn($id) => $id !== $plat_id));
        $nouveau_statut = false;
    } else {
        // Ajouter aux favoris
        $favoris_plats[] = $plat_id;
        $nouveau_statut = true;
    }

    // Sauvegarder
    mettre_a_jour_utilisateur($_SESSION['user_id'], ['favoris_plats' => $favoris_plats]);

    echo json_encode([
        'succes'  => true,
        'favori'  => $nouveau_statut,
        'plat_id' => $plat_id,
        'message' => $nouveau_statut
            ? htmlspecialchars($plat['nom']) . ' ajouté à vos favoris.'
            : htmlspecialchars($plat['nom']) . ' retiré de vos favoris.',
    ]);
    exit;
}
