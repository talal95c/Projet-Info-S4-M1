<?php
/*
 * api/paiement.php
 * ---------------------------------------------------------------
 * Page de validation du panier — intégration CY Bank.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Clé API fournie par la plateforme
require('getapikey.php');

// 2. Configuration demandée
$vendeur    = 'MI-1_C'; // Mon code vendeur (groupe)
$cybank_url = 'https://www.plateforme-smc.fr/cybank/index.php';

// URL de retour. On passe l'identifiant de session dans l'URL.
// A adapter selon votre configuration locale (port, dossier).
$url_retour = 'http://localhost:8000/api/retour_paiement.php?session=' . session_id();


// 3. Calcul du montant
$montant = 29.99; // Valeur par défaut pour tester

$data_php = __DIR__ . '/../includes/data.php';
if (file_exists($data_php) && (!empty($_SESSION['panier']) || !empty($_SESSION['panier_menus']))) {
    require_once $data_php;
    $total = 0;
    foreach ($_SESSION['panier'] ?? [] as $l) {
        $plat = trouver_plat_par_id($l['plat_id']);
        if ($plat) $total += $plat['prix'] * $l['quantite'];
    }
    foreach ($_SESSION['panier_menus'] ?? [] as $l) {
        $menu = trouver_menu_par_id($l['menu_id']);
        if ($menu) $total += $menu['prix_total'] * $l['quantite'];
    }
    // Application de la remise utilisateur si connecté
    if (!empty($_SESSION['user_id'])) {
        $user   = trouver_utilisateur_par_id($_SESSION['user_id']);
        $remise = intval($user['remise'] ?? 0);
        if ($remise > 0) $total = $total * (1 - $remise / 100);
    }
    $montant = $total;
}

// FORMATAGE EXIGÉ : 2 chiffres après la virgule avec un point, ex: 18000.99
$montant_formate = number_format($montant, 2, '.', '');

// 4. Génération de l'identifiant de transaction
// Chaîne alphanumérique au format [0-9a-zA-Z]{10,24}
$transaction = substr(preg_replace('/[^0-9a-zA-Z]/', '', uniqid('tx', true)), 0, 15);

// 5. Règle de hachage (valeur de contrôle) pour l'envoi
$api_key = getAPIKey($vendeur);
$control = md5($api_key . "#" . $transaction . "#" . $montant_formate . "#" . $vendeur . "#" . $url_retour . "#");

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement CY Bank</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; padding: 50px; text-align: center; }
        .box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-width: 400px; margin: 0 auto; }
        h1 { color: #333; }
        .btn { background: #0056b3; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 20px;}
        .btn:hover { background: #004494; }
        .info { text-align: left; background: #eef; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px;}
    </style>
</head>
<body>

<div class="box">
    <h1>CY Bank</h1>
    <p>Validation de votre commande</p>

    <div class="info">
        <strong>Montant à payer :</strong> <?= htmlspecialchars($montant_formate) ?> €<br>
        <strong>N° Transaction :</strong> <?= htmlspecialchars($transaction) ?>
    </div>

    <!-- Formulaire d'envoi vers l'interface CY Bank -->
    <form action="<?= htmlspecialchars($cybank_url) ?>" method="POST">
        <input type="hidden" name="transaction" value="<?= htmlspecialchars($transaction) ?>">
        <input type="hidden" name="montant" value="<?= htmlspecialchars($montant_formate) ?>">
        <input type="hidden" name="vendeur" value="<?= htmlspecialchars($vendeur) ?>">
        <input type="hidden" name="retour" value="<?= htmlspecialchars($url_retour) ?>">
        <input type="hidden" name="control" value="<?= htmlspecialchars($control) ?>">

        <button type="submit" class="btn">Valider et payer sur CY Bank</button>
    </form>
</div>

</body>
</html>
