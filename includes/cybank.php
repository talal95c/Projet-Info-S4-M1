<?php
/*
 * includes/cybank.php
 * ---------------------------------------------------------------
 * Simulation de l'API de paiement CYBank pour le projet scolaire.
 *
 * Dans un vrai projet, cybank_payer() ferait un appel HTTP vers
 * l'API externe. Ici, elle simule un paiement qui réussit toujours
 * tant que les données de carte sont valides en format.
 *
 * Fonctions disponibles :
 *   cybank_payer($montant, $numero_carte, $expiration, $cvv)
 *     → Valide le format des données bancaires :
 *          - numéro de carte : exactement 16 chiffres (espaces ignorés)
 *          - expiration      : format MM/AA
 *          - CVV             : exactement 3 chiffres
 *     → Retourne un tableau associatif :
 *          ['succes' => false, 'message' => '...']   en cas d'erreur de format
 *          ['succes' => true, 'transaction_id' => 'CYB-XXXXXXXX',
 *           'montant' => float, 'message' => 'Paiement accepté']  en cas de succès
 *     → Le transaction_id est généré aléatoirement (ex : CYB-A3F2B1C0)
 *       et stocké dans commandes.json pour traçabilité.
 */

function cybank_payer($montant, $numero_carte, $expiration, $cvv) {
    $carte_propre = preg_replace('/\s+/', '', $numero_carte);
    if (!preg_match('/^\d{16}$/', $carte_propre)) {
        return ['succes' => false, 'message' => 'Numéro de carte invalide (16 chiffres requis)'];
    }
    if (!preg_match('/^\d{2}\/\d{2}$/', $expiration)) {
        return ['succes' => false, 'message' => 'Date d\'expiration invalide (MM/AA)'];
    }
    if (!preg_match('/^\d{3}$/', $cvv)) {
        return ['succes' => false, 'message' => 'CVV invalide (3 chiffres)'];
    }

    $transaction_id = 'CYB-' . strtoupper(bin2hex(random_bytes(4)));

    return [
        'succes'         => true,
        'transaction_id' => $transaction_id,
        'montant'        => $montant,
        'message'        => 'Paiement accepté',
    ];
}
