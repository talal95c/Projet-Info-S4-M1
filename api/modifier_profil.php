<?php
require_once '../includes/session.php';
require_once '../includes/data.php';

header('Content-Type: application/json');

if (!est_connecte()) {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['succes' => false, 'message' => 'Données manquantes ou invalides']);
    exit;
}

$telephone = trim($input['telephone'] ?? '');
$adresse = trim($input['adresse'] ?? '');
$code_interphone = trim($input['code_interphone'] ?? '');
$etage = trim($input['etage'] ?? '');

// Server-side validation
if (empty($telephone) || strlen($telephone) !== 10 || !preg_match('/^0[1-9][0-9]{8}$/', $telephone)) {
    echo json_encode(['succes' => false, 'champ' => 'telephone', 'message' => 'Numéro de téléphone invalide']);
    exit;
}

if (strlen($adresse) < 5) {
    echo json_encode(['succes' => false, 'champ' => 'adresse', 'message' => 'Adresse trop courte']);
    exit;
}

if (strlen($code_interphone) > 20) {
    echo json_encode(['succes' => false, 'champ' => 'code_interphone', 'message' => 'Code interphone trop long']);
    exit;
}

if (strlen($etage) > 100) {
    echo json_encode(['succes' => false, 'champ' => 'etage', 'message' => 'Étage trop long']);
    exit;
}

$id = $_SESSION['user_id'];
mettre_a_jour_utilisateur($id, [
    'telephone' => $telephone,
    'adresse' => $adresse,
    'code_interphone' => $code_interphone,
    'etage' => $etage
]);

$user_mis_a_jour = trouver_utilisateur_par_id($id);

echo json_encode([
    'succes' => true,
    'message' => 'Profil mis à jour avec succès.',
    'user' => [
        'telephone' => $user_mis_a_jour['telephone'],
        'adresse' => $user_mis_a_jour['adresse'],
        'code_interphone' => $user_mis_a_jour['code_interphone'],
        'etage' => $user_mis_a_jour['etage']
    ]
]);
