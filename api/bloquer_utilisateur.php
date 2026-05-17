<?php
// Bascule actif/bloqué d'un utilisateur. La déconnexion immédiate
// d'un utilisateur bloqué est gérée par verifier_session_active().

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');

if (!est_connecte()) {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Vous devez être connecté.']);
    exit;
}

if (get_role() !== 'admin') {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Action réservée aux administrateurs.']);
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

$user_id = intval($donnees['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['succes' => false, 'message' => 'Identifiant utilisateur invalide.']);
    exit;
}

if ($user_id === $_SESSION['user_id']) {
    echo json_encode(['succes' => false, 'message' => 'Vous ne pouvez pas bloquer votre propre compte.']);
    exit;
}

$user = trouver_utilisateur_par_id($user_id);
if (!$user) {
    echo json_encode(['succes' => false, 'message' => 'Utilisateur introuvable.']);
    exit;
}

$nouvel_etat = !$user['actif'];
mettre_a_jour_utilisateur($user_id, ['actif' => $nouvel_etat]);

echo json_encode([
    'succes'  => true,
    'user_id' => $user_id,
    'actif'   => $nouvel_etat,
    'message' => $nouvel_etat
        ? 'Compte de ' . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . ' débloqué.'
        : 'Compte de ' . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . ' bloqué. '
          . 'Sa session sera fermée à sa prochaine action sur le site.',
]);
