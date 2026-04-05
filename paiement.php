<?php
/*
 * paiement.php
 * ---------------------------------------------------------------
 * Page de paiement et de validation de la commande (rôle : client).
 *
 * Redirige vers panier.php si le panier est vide.
 * Affiche trois blocs dans le formulaire POST :
 *   1. Choix de livraison : "dès que possible" ou "plus tard"
 *      (sélecteur datetime-local). Si "plus tard", le statut de
 *      la commande créée sera 'en_attente' au lieu de 'a_preparer'.
 *   2. Adresse de livraison (pré-remplie avec les données du profil,
 *      modifiable : adresse, interphone, étage, commentaire).
 *   3. Formulaire de carte bancaire (numéro 16 chiffres, expiration
 *      MM/AA, CVV 3 chiffres) traité par cybank_payer().
 *
 * En cas de succès du paiement :
 *   - crée la commande dans commandes.json via ajouter_commande()
 *   - ajoute 1 point de fidélité par euro dépensé
 *   - vide le panier session
 *   - affiche une page de confirmation
 * La remise (%) éventuellement attribuée par l'admin est appliquée
 * au total avant l'appel à CYBank.
 *
 * Accès : client connecté uniquement
 * Dépendances : includes/session.php, includes/data.php, includes/cybank.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';
require_once 'includes/cybank.php';

verifier_connexion(['client']);

if (empty($_SESSION['panier'])) {
    header('Location: panier.php');
    exit;
}

$user  = trouver_utilisateur_par_id($_SESSION['user_id']);
$remise = intval($user['remise'] ?? 0);

// Calculer le total
$total = 0;
$lignes = [];
foreach ($_SESSION['panier'] as $ligne) {
    $plat = trouver_plat_par_id($ligne['plat_id']);
    if ($plat) {
        $sous_total = $plat['prix'] * $ligne['quantite'];
        $total += $sous_total;
        $lignes[] = ['plat' => $plat, 'quantite' => $ligne['quantite'], 'sous_total' => $sous_total];
    }
}
$total_apres_remise = $remise > 0 ? $total * (1 - $remise / 100) : $total;

$erreur  = '';
$succes  = false;

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_carte = trim($_POST['numero_carte'] ?? '');
    $expiration   = trim($_POST['expiration'] ?? '');
    $cvv          = trim($_POST['cvv'] ?? '');
    $adresse_livraison = trim($_POST['adresse_livraison'] ?? $user['adresse']);
    $code_interphone   = trim($_POST['code_interphone'] ?? $user['code_interphone']);
    $etage             = trim($_POST['etage'] ?? $user['etage']);
    $commentaire       = trim($_POST['commentaire'] ?? '');
    $type_livraison    = $_POST['type_livraison'] ?? 'maintenant';
    $date_souhaitee    = trim($_POST['date_souhaitee'] ?? '');

    // Appel simulation CYBank
    $resultat = cybank_payer($total_apres_remise, $numero_carte, $expiration, $cvv);

    if (!$resultat['succes']) {
        $erreur = $resultat['message'];
    } else {
        if ($type_livraison === 'plus_tard' && $date_souhaitee !== '') {
            $statut_commande = 'en_attente';
        } else {
            $statut_commande = 'a_preparer';
            $date_souhaitee  = null;
        }

        $articles = array_map(fn($l) => ['plat_id' => $l['plat']['id'], 'quantite' => $l['quantite']], $lignes);

        $nouvelle_commande = [
            'client_id'        => $_SESSION['user_id'],
            'livreur_id'       => null,
            'articles'         => $articles,
            'adresse_livraison' => $adresse_livraison,
            'code_interphone'  => $code_interphone,
            'etage'            => $etage,
            'telephone'        => $user['telephone'],
            'commentaire'      => $commentaire,
            'statut'           => $statut_commande,
            'date_souhaitee'   => $date_souhaitee,
            'date'             => date('Y-m-d\TH:i:s'),
            'total'            => round($total_apres_remise, 2),
            'paiement_effectue' => true,
            'transaction_id'   => $resultat['transaction_id'],
            'avis'             => null,
        ];

        ajouter_commande($nouvelle_commande);

        $points_gagnes = intval($total_apres_remise);
        mettre_a_jour_utilisateur($_SESSION['user_id'], [
            'points_fidelite' => ($user['points_fidelite'] + $points_gagnes),
        ]);

        $_SESSION['panier'] = [];
        $succes = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <header>
        <?= nav_html('paiement') ?>
    </header>

    <main>
        <div style="max-width:650px; margin:2rem auto; padding:0 1rem;">

        <?php if ($succes): ?>

            <!-- Confirmation de commande -->
            <div style="background:white; border-radius:16px; padding:3rem; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,0.08);">
                <div style="font-size:3rem; margin-bottom:1rem;">✅</div>
                <h1 style="color:#015a17;">Commande confirmée !</h1>
                <p style="color:#555; margin:1rem 0;">Votre commande a bien été enregistrée et sera préparée dès que possible.</p>
                <p style="font-size:0.9rem; color:#aaa; margin-bottom:2rem;">Vous pouvez suivre son état depuis votre profil.</p>
                <a href="profil.php" class="btn-voir" style="display:inline-block;">Voir mes commandes</a>
            </div>

        <?php else: ?>

            <h1 style="margin-bottom:1.5rem;">💳 Paiement</h1>

            <?php if ($erreur): ?>
                <p style="background:#f8d7da; color:#721c24; padding:0.8rem 1.2rem; border-radius:8px; margin-bottom:1rem;">
                    ⚠️ <?= htmlspecialchars($erreur) ?>
                </p>
            <?php endif; ?>

            <!-- Récapitulatif -->
            <div style="background:white; border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:1.5rem;">
                <h2 style="margin:0 0 1rem;">📋 Récapitulatif</h2>
                <?php foreach ($lignes as $l): ?>
                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #f5f5f5; font-size:0.95rem;">
                    <span><?= htmlspecialchars($l['plat']['nom']) ?> × <?= $l['quantite'] ?></span>
                    <span><?= number_format($l['sous_total'], 2, ',', ' ') ?> €</span>
                </div>
                <?php endforeach; ?>
                <?php if ($remise > 0): ?>
                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; color:#28a745; font-size:0.9rem;">
                    <span>Remise (<?= $remise ?>%)</span>
                    <span>-<?= number_format($total - $total_apres_remise, 2, ',', ' ') ?> €</span>
                </div>
                <?php endif; ?>
                <div style="display:flex; justify-content:space-between; font-weight:700; font-size:1.1rem; margin-top:0.8rem; padding-top:0.8rem; border-top:2px solid #eee;">
                    <span>Total à payer</span>
                    <span><?= number_format($total_apres_remise, 2, ',', ' ') ?> €</span>
                </div>
            </div>

            <form method="POST" action="paiement.php">

                <!-- Quand souhaitez-vous être livré ? -->
                <div style="background:white; border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:1.5rem;">
                    <h2 style="margin:0 0 1rem;">🕐 Quand souhaitez-vous être livré ?</h2>
                    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:600;">
                            <input type="radio" name="type_livraison" value="maintenant" checked
                                   onchange="document.getElementById('bloc_date').style.display='none'">
                            ⚡ Dès que possible
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:600;">
                            <input type="radio" name="type_livraison" value="plus_tard"
                                   onchange="document.getElementById('bloc_date').style.display='block'">
                            📅 Plus tard
                        </label>
                    </div>
                    <div id="bloc_date" style="display:none;">
                        <label style="font-size:0.9rem; color:#555; display:block; margin-bottom:4px;">Date et heure souhaitées</label>
                        <input type="datetime-local" name="date_souhaitee"
                               min="<?= date('Y-m-d\TH:i') ?>"
                               style="padding:8px; border:1px solid #ddd; border-radius:8px; font-family:Poppins,sans-serif;">
                    </div>
                </div>

                <!-- Adresse de livraison -->
                <div style="background:white; border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:1.5rem;">
                    <h2 style="margin:0 0 1rem;">📍 Adresse de livraison</h2>
                    <div class="form-group">
                        <label>Adresse</label>
                        <input type="text" name="adresse_livraison" value="<?= htmlspecialchars($user['adresse']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Code interphone</label>
                        <input type="text" name="code_interphone" value="<?= htmlspecialchars($user['code_interphone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Étage</label>
                        <input type="text" name="etage" value="<?= htmlspecialchars($user['etage']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Commentaire (optionnel)</label>
                        <input type="text" name="commentaire" placeholder="Ex : Sonner deux fois...">
                    </div>
                </div>

                <!-- Carte bancaire (CYBank) -->
                <div style="background:white; border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:1.5rem;">
                    <h2 style="margin:0 0 0.4rem;">💳 Carte bancaire</h2>
                    <p style="font-size:0.8rem; color:#aaa; margin:0 0 1rem;">Paiement sécurisé via CYBank</p>
                    <div class="form-group">
                        <label>Numéro de carte (16 chiffres)</label>
                        <input type="text" name="numero_carte" placeholder="1234 5678 9012 3456"
                               maxlength="19" required>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Date d'expiration (MM/AA)</label>
                            <input type="text" name="expiration" placeholder="12/27" maxlength="5" required>
                        </div>
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" name="cvv" placeholder="123" maxlength="3" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="width:100%; font-size:1.1rem; padding:1rem;">
                    ✅ Confirmer et payer <?= number_format($total_apres_remise, 2, ',', ' ') ?> €
                </button>

            </form>

            <div style="text-align:center; margin-top:1rem;">
                <a href="panier.php" style="color:#aaa; font-size:0.85rem; text-decoration:underline;">← Retour au panier</a>
            </div>

        <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>
</body>
</html>
