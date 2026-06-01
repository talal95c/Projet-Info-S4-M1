<?php
/*
 * paiement_supplement.php
 * ---------------------------------------------------------------
 * Page de paiement du supplément quand le client a modifié sa
 * commande avec un montant supérieur à celui initialement payé.
 *
 * Flux :
 *   1. Le client arrive ici depuis modifier_commande.php (via JS)
 *      avec ?commande_id=X.
 *   2. On récupère la modification en attente dans commandes.json
 *      (champ 'modification_en_attente').
 *   3. On génère un formulaire CY Bank pour le montant du supplément.
 *   4. Le client clique sur "Payer" → redirection CY Bank.
 *   5. CY Bank redirige vers retour_supplement.php.
 *
 * Accès : client connecté (propriétaire de la commande).
 * Dépendances : includes/session.php, includes/data.php,
 *               includes/cybank.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';
require_once 'includes/cybank.php';

verifier_connexion(['client']);

$commande_id = intval($_GET['commande_id'] ?? 0);
if ($commande_id <= 0) {
    header('Location: profil.php');
    exit;
}

// Charger la commande
$commandes = lire_json('commandes.json');
$commande  = null;
foreach ($commandes as $c) {
    if ($c['id'] == $commande_id) { $commande = $c; break; }
}

// Vérifications de sécurité
if (!$commande || $commande['client_id'] != $_SESSION['user_id']) {
    header('Location: profil.php');
    exit;
}

// La modification en attente doit exister
if (empty($commande['modification_en_attente'])) {
    header('Location: profil.php');
    exit;
}

$modif      = $commande['modification_en_attente'];
$supplement = (float)$modif['supplement_montant'];

// Génération du formulaire CY Bank pour le supplément
$transaction_id = cybank_generer_transaction_id();
$url_retour     = cybank_url_retour_supplement($transaction_id, $commande_id);
$cybank_form    = cybank_form_html($transaction_id, $supplement, $url_retour);

// On enregistre le transaction_id du supplément dans la commande
mettre_a_jour_commande($commande_id, [
    'modification_en_attente' => array_merge($modif, [
        'transaction_supplement' => $transaction_id,
    ]),
]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement du supplément | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="auth.css">
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('profil') ?>
    </header>

    <main>
        <div style="max-width:650px; margin:3rem auto; padding:0 1rem;">

            <div style="background:white; border-radius:16px; padding:3rem; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,0.08);">
                <div style="font-size:4rem; margin-bottom:1rem;">💳</div>
                <h1 style="color:#856404;">Paiement du supplément</h1>
                <p style="color:#555; margin:1rem 0; font-size:1.05rem;">
                    Votre commande a été modifiée. Un supplément est nécessaire pour
                    couvrir la différence de prix.
                </p>

                <!-- Récap de la modification -->
                <div style="background:#f9f9f9; border-radius:10px; padding:1.2rem; margin:1.5rem 0; text-align:left;">
                    <p style="margin:0.3rem 0; color:#555; font-size:0.9rem;">
                        Montant initialement payé :
                        <strong><?= number_format((float)$commande['total'], 2, ',', ' ') ?> €</strong>
                    </p>
                    <p style="margin:0.3rem 0; color:#555; font-size:0.9rem;">
                        Nouveau total de la commande :
                        <strong><?= number_format((float)$modif['nouveau_total'], 2, ',', ' ') ?> €</strong>
                    </p>
                    <div style="border-top:2px solid #e0e0e0; margin-top:0.8rem; padding-top:0.8rem;">
                        <p style="margin:0; font-size:1.3rem; font-weight:700; color:#856404;">
                            Supplément à payer : <?= number_format($supplement, 2, ',', ' ') ?> €
                        </p>
                    </div>
                </div>

                <p style="color:#555; font-size:0.95rem; margin-bottom:1.5rem;">
                    Cliquez ci-dessous pour finaliser le paiement du supplément sur
                    la plateforme sécurisée <strong>CY Bank</strong>.
                </p>

                <?= $cybank_form ?>

                <p style="font-size:0.8rem; color:#aaa; margin-top:1.5rem;">
                    💡 Carte d'essai : 5555 1234 5678 9000 — CVV 555 — Date : au choix
                </p>
                <p style="margin-top:1rem;">
                    <a href="modifier_commande.php?id=<?= $commande_id ?>"
                       style="color:#aaa; font-size:0.85rem; text-decoration:underline;">
                        ← Annuler et revenir à la modification
                    </a>
                </p>
            </div>

        </div>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>
</body>
</html>
