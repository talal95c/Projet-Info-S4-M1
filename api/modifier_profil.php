<?php
// Mise à jour AJAX du profil client.

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');

if (!est_connecte()) {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Vous devez être connecté pour modifier votre profil.']);
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

$donnees = $_POST;
if (empty($donnees)) {
    $brut = file_get_contents('php://input');
    if ($brut) {
        $decode = json_decode($brut, true);
        if (is_array($decode)) $donnees = $decode;
    }
}

$telephone       = trim($donnees['telephone']       ?? '');
$adresse         = trim($donnees['adresse']         ?? '');
$code_interphone = trim($donnees['code_interphone'] ?? '');
$etage           = trim($donnees['etage']           ?? '');

if ($telephone === '') {
    echo json_encode(['succes' => false, 'champ' => 'telephone', 'message' => 'Le téléphone est obligatoire.']);
    exit;
}

$tel_propre = preg_replace('/[\s.\-]/', '', $telephone);
if (!preg_match('/^0[1-9]\d{8}$/', $tel_propre)) {
    echo json_encode(['succes' => false, 'champ' => 'telephone', 'message' => 'Numéro français invalide (10 chiffres, commence par 0).']);
    exit;
}

if ($adresse === '') {
    echo json_encode(['succes' => false, 'champ' => 'adresse', 'message' => 'L\'adresse est obligatoire.']);
    exit;
}
if (mb_strlen($adresse) < 5) {
    echo json_encode(['succes' => false, 'champ' => 'adresse', 'message' => 'L\'adresse doit faire au moins 5 caractères.']);
    exit;
}
if (mb_strlen($adresse) > 200) {
    echo json_encode(['succes' => false, 'champ' => 'adresse', 'message' => 'Adresse trop longue (max 200).']);
    exit;
}
if (mb_strlen($code_interphone) > 20) {
    echo json_encode(['succes' => false, 'champ' => 'code_interphone', 'message' => 'Code interphone trop long (max 20).']);
    exit;
}
if (mb_strlen($etage) > 100) {
    echo json_encode(['succes' => false, 'champ' => 'etage', 'message' => 'Étage trop long (max 100).']);
    exit;
}

mettre_a_jour_utilisateur($_SESSION['user_id'], [
    'telephone'       => $telephone,
    'adresse'         => $adresse,
    'code_interphone' => $code_interphone,
    'etage'           => $etage,
]);

$user = trouver_utilisateur_par_id($_SESSION['user_id']);
unset($user['mot_de_passe']);

echo json_encode([
    'succes'  => true,
    'message' => 'Profil mis à jour avec succès.',
    'user'    => $user,
]);
