<?php
/*
 * retour_supplement.php
 * ---------------------------------------------------------------
 * Page de retour de CY Bank après le paiement du supplément de
 * modification de commande.
 *
 * Si le paiement est accepté :
 *   → on applique la modification en attente (nouveaux articles,
 *     nouveau total) sur la commande.
 * Si le paiement est refusé :
 *   → la commande reste inchangée (les anciens articles sont conservés),
 *     on supprime simplement la modification en attente.
 *
 * Dépendances : includes/session.php, includes/data.php,
 *               includes/cybank.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';
require_once 'includes/cybank.php';

verifier_connexion(['client']);

// Lecture des paramètres retournés par CY Bank
$params = [
    'transaction' => $_GET['transaction'] ?? '',
    'montant'     => $_GET['montant']     ?? '',
    'vendeur'     => $_GET['vendeur']     ?? '',
    'statut'      => $_GET['statut']      ?? ($_GET['status'] ?? ''),
    'control'     => $_GET['control']     ?? '',
];

$signature_valide = cybank_verifier_retour($params);
$statut_cybank    = $params['statut'];

// Recherche de la commande via le transaction_id du supplément
$commande = null;
$commandes = lire_json('commandes.json');

foreach ($commandes as $c) {
    if (!empty($c['modification_en_attente']['transaction_supplement'])
        && $c['modification_en_attente']['transaction_supplement'] === $params['transaction']
        && $c['client_id'] == $_SESSION['user_id']) {
        $commande = $c;
        break;
    }
}

$message = '';
$succes  = false;

if (!$signature_valide) {
    $message = 'Signature invalide : la requête ne provient pas de CY Bank.';
} elseif (!$commande) {
    $message = 'Commande introuvable ou supplément déjà traité.';
} elseif ($statut_cybank === 'accepted') {
    // Paiement accepté → on applique la modification
    $modif       = $commande['modification_en_attente'];
    $nouveau_total = (float)$modif['nouveau_total'];

    mettre_a_jour_commande($commande['id'], [
        'articles'                => $modif['articles'],
        'total'                   => $nouveau_total,
        'total_effectif'          => $nouveau_total,
        'date_modification'       => date('Y-m-d\TH:i:s'),
        'modification_en_attente' => null,
    ]);

    // Crédite les points de fidélité pour la différence payée
    $supplement = (float)$modif['supplement_montant'];
    $user = trouver_utilisateur_par_id($_SESSION['user_id']);
    $points_supplementaires = intval($supplement);
    if ($points_supplementaires > 0) {
        mettre_a_jour_utilisateur($_SESSION['user_id'], [
            'points_fidelite' => ($user['points_fidelite'] + $points_supplementaires),
        ]);
    }

    $succes  = true;
    $message = 'Supplément payé ! La commande #' . $commande['id'] . ' a bien été modifiée.';

} else {
    // Paiement refusé → on annule la modification en attente
    mettre_a_jour_commande($commande['id'], [
        'modification_en_attente' => null,
    ]);
    $message = 'Paiement refusé. La commande reste inchangée.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retour paiement supplément | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('profil') ?>
    </header>

    <main>
        <div style="max-width:650px; margin:3rem auto; padding:0 1rem;">

            <div style="background:white; border-radius:16px; padding:3rem; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,0.08);">

                <?php if ($succes): ?>
                    <div style="font-size:4rem; margin-bottom:1rem;">✅</div>
                    <h1 style="color:#015a17;">Modification confirmée !</h1>
                    <p style="color:#555; margin:1rem 0; font-size:1.05rem;">
                        <?= htmlspecialchars($message) ?>
                    </p>
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
                    <p style="font-size:0.9rem; color:#aaa; margin-bottom:2rem;">
                        Votre commande originale n'a pas été modifiée.
                    </p>
                    <a href="profil.php" class="btn-voir" style="display:inline-block; margin-right:0.5rem;">
                        Retour au profil
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
