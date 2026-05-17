<?php
/*
 * retour_paiement.php
 * ---------------------------------------------------------------
 * Page de retour appelée par CY Bank après une tentative de
 * paiement (réussie ou non).
 *
 * CY Bank ajoute à l'URL fournie dans le paramètre 'retour' lors
 * de l'envoi initial les paramètres suivants :
 *   transaction, montant, vendeur, statut, control
 *
 * On reçoit ces paramètres en GET et on doit :
 *   1. Vérifier la signature MD5 (control) pour s'assurer que la
 *      requête vient bien de CY Bank et n'a pas été manipulée.
 *   2. Retrouver la commande créée précédemment dans
 *      commandes.json via le transaction_id.
 *   3. Selon le statut :
 *        - 'accepted' → marquer la commande comme payée, vider le
 *                       panier, créditer les points fidélité
 *        - 'declined' → marquer la commande comme annulée
 *   4. Afficher un écran de confirmation ou d'échec.
 *
 * /!\ Si la signature est invalide on REFUSE la mise à jour :
 * c'est un faux retour (ou une attaque).
 *
 * Dépendances : includes/session.php, includes/data.php,
 *               includes/cybank.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';
require_once 'includes/cybank.php';

verifier_connexion(['client']);

// === Lecture et vérification des paramètres CY Bank ============ //

$params = [
    'transaction' => $_GET['transaction'] ?? '',
    'montant'     => $_GET['montant']     ?? '',
    'vendeur'     => $_GET['vendeur']     ?? '',
    'statut'      => $_GET['statut']      ?? ($_GET['status'] ?? ''), // CYBank utilise "status" dans certains exemples
    'control'     => $_GET['control']     ?? '',
];

$signature_valide = cybank_verifier_retour($params);
$statut_cybank    = $params['statut'];   // 'accepted' ou 'declined'

// === Recherche de la commande correspondante =================== //

$commande = null;
$commandes = lire_json('commandes.json');

foreach ($commandes as $c) {
    if (isset($c['transaction_id']) && $c['transaction_id'] === $params['transaction']
        && $c['client_id'] == $_SESSION['user_id']) {
        $commande = $c;
        break;
    }
}

// === Mise à jour de la commande =============================== //

$action_effectuee = false;
$message          = '';
$succes           = false;

if (!$signature_valide) {
    $message = 'Signature invalide : la requête ne provient pas de CY Bank ou a été altérée.';
} elseif (!$commande) {
    $message = 'Commande introuvable.';
} elseif ($commande['statut'] !== 'en_attente_paiement') {
    // Évite de traiter deux fois le même retour (sécurité)
    $message = 'Cette commande a déjà été traitée.';
    $succes  = ($commande['paiement_effectue'] ?? false);
} elseif ($statut_cybank === 'accepted') {

    // Paiement accepté → mise à jour de la commande
    // Statut final selon le type de livraison choisi en amont
    $statut_final = ($commande['type_livraison'] === 'plus_tard') ? 'en_attente' : 'a_preparer';

    mettre_a_jour_commande($commande['id'], [
        'statut'            => $statut_final,
        'paiement_effectue' => true,
        'date_paiement'     => date('Y-m-d\TH:i:s'),
    ]);

    // Crédite les points fidélité (1 point par euro)
    $user = trouver_utilisateur_par_id($_SESSION['user_id']);
    $points_gagnes = intval($commande['total']);
    mettre_a_jour_utilisateur($_SESSION['user_id'], [
        'points_fidelite' => ($user['points_fidelite'] + $points_gagnes),
    ]);

    // Vide le panier
    $_SESSION['panier']       = [];
    $_SESSION['panier_menus'] = [];

    $succes  = true;
    $message = 'Paiement accepté ! Votre commande #' . $commande['id'] . ' est confirmée.';
    $action_effectuee = true;

} else {
    // Paiement refusé → on annule la commande
    mettre_a_jour_commande($commande['id'], [
        'statut'            => 'paiement_refuse',
        'paiement_effectue' => false,
    ]);
    $message = 'Paiement refusé. Votre commande a été annulée.';
    $action_effectuee = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retour de paiement | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('paiement') ?>
    </header>

    <main>
        <div style="max-width:650px; margin:3rem auto; padding:0 1rem;">

            <div style="background:white; border-radius:16px; padding:3rem; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,0.08);">

                <?php if ($succes): ?>
                    <div style="font-size:4rem; margin-bottom:1rem;">✅</div>
                    <h1 style="color:#015a17;">Commande confirmée !</h1>
                    <p style="color:#555; margin:1rem 0; font-size:1.05rem;">
                        <?= htmlspecialchars($message) ?>
                    </p>
                    <?php if ($commande): ?>
                    <p style="color:#888; font-size:0.9rem;">
                        Montant payé : <strong><?= number_format($commande['total'], 2, ',', ' ') ?> €</strong>
                        &nbsp;•&nbsp; Transaction : <code><?= htmlspecialchars($params['transaction']) ?></code>
                    </p>
                    <p style="font-size:0.9rem; color:#aaa; margin-bottom:2rem;">
                        Vous pouvez suivre l'état de votre commande depuis votre profil.
                    </p>
                    <?php endif; ?>
                    <a href="profil.php" class="btn-voir" style="display:inline-block; margin-right:0.5rem;">
                        Voir mes commandes
                    </a>
                    <a href="presentation.php" class="btn-voir" style="background:#aaa; display:inline-block;">
                        Continuer mes achats
                    </a>

                <?php else: ?>
                    <div style="font-size:4rem; margin-bottom:1rem;">❌</div>
                    <h1 style="color:#c0392b;">Paiement non finalisé</h1>
                    <p style="color:#555; margin:1rem 0; font-size:1.05rem;">
                        <?= htmlspecialchars($message) ?>
                    </p>
                    <?php if ($action_effectuee && $commande): ?>
                    <p style="color:#888; font-size:0.9rem;">
                        Transaction : <code><?= htmlspecialchars($params['transaction']) ?></code>
                    </p>
                    <?php endif; ?>
                    <p style="font-size:0.9rem; color:#aaa; margin-bottom:2rem;">
                        Aucun montant n'a été débité. Vous pouvez réessayer.
                    </p>
                    <a href="panier.php" class="btn-voir" style="display:inline-block; margin-right:0.5rem;">
                        Retour au panier
                    </a>
                    <a href="presentation.php" class="btn-voir" style="background:#aaa; display:inline-block;">
                        Voir le menu
                    </a>
                <?php endif; ?>

            </div>

        </div>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>
</body>
</html>
