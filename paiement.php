<?php
/*
 * paiement.php
 * ---------------------------------------------------------------
 * Page de préparation de la commande et redirection vers CY Bank
 * pour le paiement (rôle : client).
 *
 * /!\ Nouveau workflow (correction de la phase 2) /!\
 * On NE demande PLUS le numéro de carte sur notre site : c'est
 * CY Bank qui s'en charge sur sa propre interface.
 *
 * Étapes :
 *   1. Si le panier est vide → redirection panier.php.
 *   2. Affichage du récapitulatif + formulaire d'adresse de
 *      livraison + choix livraison maintenant/plus tard.
 *   3. POST du formulaire :
 *        - validation côté serveur (adresse non vide, etc.)
 *        - création de la commande dans commandes.json avec
 *          statut "en_attente_paiement", paiement_effectue=false
 *          et un identifiant de transaction unique
 *        - affichage du formulaire HTML qui POST vers CY Bank
 *          (transaction, montant, vendeur, retour, control)
 *   4. L'utilisateur clique sur le bouton "Payer via CY Bank" :
 *      il est redirigé sur la plateforme externe pour saisir
 *      sa carte. CY Bank redirige ensuite vers retour_paiement.php.
 *   5. retour_paiement.php vérifie la signature, marque la
 *      commande comme payée (ou refusée) et vide le panier.
 *
 * Carte d'essai (uniquement sur le site CY Bank) :
 *   N°: 5555 1234 5678 9000   CVV: 555   Date: n'importe
 *
 * Accès : client connecté uniquement
 * Dépendances : includes/session.php, includes/data.php,
 *               includes/cybank.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';
require_once 'includes/cybank.php';

verifier_connexion(['client']);

if (empty($_SESSION['panier']) && empty($_SESSION['panier_menus'])) {
    header('Location: panier.php');
    exit;
}

if (!isset($_SESSION['panier_menus'])) $_SESSION['panier_menus'] = [];

$user   = trouver_utilisateur_par_id($_SESSION['user_id']);
$remise = intval($user['remise'] ?? 0);

// --- Calcul du total (plats + menus) -------------------------- //

$total  = 0;
$lignes = [];

foreach ($_SESSION['panier'] as $ligne) {
    $plat = trouver_plat_par_id($ligne['plat_id']);
    if ($plat) {
        $sous_total = $plat['prix'] * $ligne['quantite'];
        $total += $sous_total;
        $lignes[] = ['type' => 'plat', 'item' => $plat, 'quantite' => $ligne['quantite'], 'sous_total' => $sous_total];
    }
}
foreach ($_SESSION['panier_menus'] as $ligne) {
    $menu = trouver_menu_par_id($ligne['menu_id']);
    if ($menu) {
        $sous_total = $menu['prix_total'] * $ligne['quantite'];
        $total += $sous_total;
        $lignes[] = ['type' => 'menu', 'item' => $menu, 'quantite' => $ligne['quantite'], 'sous_total' => $sous_total];
    }
}
$total_apres_remise = $remise > 0 ? $total * (1 - $remise / 100) : $total;

// --- Traitement POST : création commande + form CY Bank ------- //

$erreur     = '';
$cybank_form = '';     // HTML du formulaire CY Bank à afficher

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $adresse_livraison = trim($_POST['adresse_livraison'] ?? $user['adresse']);
    $code_interphone   = trim($_POST['code_interphone']   ?? $user['code_interphone']);
    $etage             = trim($_POST['etage']             ?? $user['etage']);
    $commentaire       = trim($_POST['commentaire']       ?? '');
    $type_livraison    = $_POST['type_livraison']         ?? 'maintenant';
    $date_souhaitee    = trim($_POST['date_souhaitee']    ?? '');

    if ($adresse_livraison === '') {
        $erreur = 'L\'adresse de livraison est obligatoire.';
    } elseif ($type_livraison === 'plus_tard' && $date_souhaitee === '') {
        $erreur = 'Veuillez choisir une date et heure de livraison.';
    } else {

        // Crée la commande en attente de paiement
        $articles = array_map(function($l) {
            if ($l['type'] === 'menu') {
                return ['menu_id' => $l['item']['id'], 'quantite' => $l['quantite']];
            }
            return ['plat_id' => $l['item']['id'], 'quantite' => $l['quantite']];
        }, $lignes);

        $transaction_id = cybank_generer_transaction_id();

        $nouvelle_commande = [
            'client_id'         => $_SESSION['user_id'],
            'livreur_id'        => null,
            'articles'          => $articles,
            'adresse_livraison' => $adresse_livraison,
            'code_interphone'   => $code_interphone,
            'etage'             => $etage,
            'telephone'         => $user['telephone'],
            'commentaire'       => $commentaire,
            'statut'            => 'en_attente_paiement',
            'type_livraison'    => $type_livraison,
            'date_souhaitee'    => ($type_livraison === 'plus_tard' ? $date_souhaitee : null),
            'date'              => date('Y-m-d\TH:i:s'),
            'total'             => round($total_apres_remise, 2),
            'paiement_effectue' => false,
            'transaction_id'    => $transaction_id,
            'avis'              => null,
        ];

        $id_commande = ajouter_commande($nouvelle_commande);

        // Génère le formulaire HTML qui POST vers CY Bank.
        // L'URL de retour pointe sur notre retour_paiement.php avec
        // l'ID de transaction en paramètre (pour retrouver la commande).
        $url_retour = cybank_url_retour($transaction_id);
        $cybank_form = cybank_form_html(
            $transaction_id,
            $total_apres_remise,
            $url_retour
        );
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
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('paiement') ?>
    </header>

    <main>
        <div style="max-width:650px; margin:2rem auto; padding:0 1rem;">

        <?php if ($cybank_form !== ''): ?>

            <!-- ÉTAPE 2 : redirection vers CY Bank -------------------- -->
            <div style="background:var(--white); border-radius:16px; padding:2.5rem; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,0.08);">
                <div style="font-size:3rem; margin-bottom:1rem;">🔒</div>
                <h1 style="color:#015a17;">Paiement sécurisé</h1>
                <p style="color:#555; margin:1rem 0;">
                    Votre commande est prête. Cliquez sur le bouton ci-dessous pour
                    finaliser le paiement sur la plateforme sécurisée <strong>CY Bank</strong>.
                </p>
                <p style="font-size:1.5rem; font-weight:700; color:#014d14; margin:1.5rem 0;">
                    Montant : <?= number_format($total_apres_remise, 2, ',', ' ') ?> €
                </p>

                <?= $cybank_form ?>

                <p style="font-size:0.8rem; color:#aaa; margin-top:1.5rem;">
                    💡 Carte d'essai : 5555 1234 5678 9000 — CVV 555 — Date : au choix
                </p>
                <p style="margin-top:1rem;">
                    <a href="panier.php" style="color:#aaa; font-size:0.85rem; text-decoration:underline;">
                        ← Annuler et retourner au panier
                    </a>
                </p>
            </div>

        <?php else: ?>

            <!-- ÉTAPE 1 : informations de livraison ----------------- -->
            <h1 style="margin-bottom:1.5rem;">💳 Finalisation de la commande</h1>

            <?php if ($erreur): ?>
                <p style="background:#f8d7da; color:#721c24; padding:0.8rem 1.2rem; border-radius:8px; margin-bottom:1rem;">
                    ⚠️ <?= htmlspecialchars($erreur) ?>
                </p>
            <?php endif; ?>

            <!-- Récapitulatif -->
            <div style="background:var(--white); border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:1.5rem;">
                <h2 style="margin:0 0 1rem;">📋 Récapitulatif</h2>
                <?php foreach ($lignes as $l): ?>
                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #f5f5f5; font-size:0.95rem;">
                    <span>
                        <?= htmlspecialchars($l['item']['nom']) ?> × <?= $l['quantite'] ?>
                        <?php if ($l['type'] === 'menu'): ?>
                            <span style="background:#eef5f0; color:#014d14; border-radius:4px; font-size:0.72rem; padding:1px 6px; margin-left:4px;">Menu</span>
                        <?php endif; ?>
                    </span>
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

            <form id="form-paiement" method="POST" action="paiement.php" novalidate>

                <!-- Quand souhaitez-vous être livré ? -->
                <div style="background:var(--white); border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:1.5rem;">
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
                <div style="background:var(--white); border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-bottom:1.5rem;">
                    <h2 style="margin:0 0 1rem;">📍 Adresse de livraison</h2>
                    <div class="form-group">
                        <label>Adresse *</label>
                        <input type="text" name="adresse_livraison"
                               value="<?= htmlspecialchars($user['adresse']) ?>"
                               maxlength="200" data-compteur required>
                    </div>
                    <div class="form-group">
                        <label>Code interphone</label>
                        <input type="text" name="code_interphone"
                               value="<?= htmlspecialchars($user['code_interphone']) ?>"
                               maxlength="20" data-compteur>
                    </div>
                    <div class="form-group">
                        <label>Étage</label>
                        <input type="text" name="etage"
                               value="<?= htmlspecialchars($user['etage']) ?>"
                               maxlength="100" data-compteur>
                    </div>
                    <div class="form-group">
                        <label>Commentaire (optionnel)</label>
                        <input type="text" name="commentaire" placeholder="Ex : Sonner deux fois..."
                               maxlength="200" data-compteur>
                    </div>
                </div>

                <button type="submit" class="submit-btn" style="width:100%; font-size:1.1rem; padding:1rem;">
                    ➡️ Procéder au paiement (<?= number_format($total_apres_remise, 2, ',', ' ') ?> €)
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

    <script src="js/common.js"></script>
    <script src="js/paiement.js"></script>
</body>
</html>
