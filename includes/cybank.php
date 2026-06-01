<?php
// Intégration CY Bank (paiement externe).
// Le fichier getapikey.php doit être présent à côté (téléchargé depuis
// https://www.plateforme-smc.fr/cybank/getapikey.zip).

require_once __DIR__ . '/getapikey.php';

define('CYBANK_URL', 'https://www.plateforme-smc.fr/cybank/index.php');
define('CYBANK_VENDEUR', 'MI-1_C');

function cybank_generer_transaction_id() {
    return bin2hex(random_bytes(8)) . strtoupper(bin2hex(random_bytes(2)));
}

function cybank_formater_montant($montant) {
    return number_format((float)$montant, 2, '.', '');
}

function cybank_calculer_control($transaction, $montant, $retour) {
    $api_key = getAPIKey(CYBANK_VENDEUR);
    return md5($api_key . '#' . $transaction . '#' . $montant . '#' . CYBANK_VENDEUR . '#' . $retour . '#');
}

function cybank_calculer_control_retour($transaction, $montant, $statut) {
    $api_key = getAPIKey(CYBANK_VENDEUR);
    return md5($api_key . '#' . $transaction . '#' . $montant . '#' . CYBANK_VENDEUR . '#' . $statut . '#');
}

function cybank_form_html($transaction, $montant, $retour) {
    $montant_formate = cybank_formater_montant($montant);
    $control = cybank_calculer_control($transaction, $montant_formate, $retour);

    return '
    <form action="' . CYBANK_URL . '" method="POST" id="form-cybank">
        <input type="hidden" name="transaction" value="' . htmlspecialchars($transaction) . '">
        <input type="hidden" name="montant"     value="' . htmlspecialchars($montant_formate) . '">
        <input type="hidden" name="vendeur"     value="' . htmlspecialchars(CYBANK_VENDEUR) . '">
        <input type="hidden" name="retour"      value="' . htmlspecialchars($retour) . '">
        <input type="hidden" name="control"     value="' . htmlspecialchars($control) . '">
        <button type="submit" class="btn-cybank">
            💳 Payer ' . $montant_formate . ' € via CY Bank
        </button>
    </form>';
}

function cybank_verifier_retour($params) {
    $champs = ['transaction', 'montant', 'vendeur', 'statut', 'control'];
    foreach ($champs as $c) {
        if (!isset($params[$c]) || $params[$c] === '') return false;
    }
    if ($params['vendeur'] !== CYBANK_VENDEUR) return false;

    $attendu = cybank_calculer_control_retour(
        $params['transaction'],
        $params['montant'],
        $params['statut']
    );
    return hash_equals($attendu, $params['control']);
}

function cybank_url_retour($transaction_id) {
    $protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $dir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $protocole . '://' . $host . $dir . '/retour_paiement.php?session=' . urlencode($transaction_id);
}

// URL de retour pour le paiement d'un supplément de modification de commande.
function cybank_url_retour_supplement($transaction_id, $commande_id) {
    $protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $dir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $protocole . '://' . $host . $dir . '/retour_supplement.php?commande_id=' . intval($commande_id);
}

