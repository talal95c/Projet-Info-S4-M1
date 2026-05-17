<?php
/*
 * modifier_commande.php
 * ---------------------------------------------------------------
 * Page d'édition d'une commande déjà payée mais pas encore en
 * préparation (phase 3 du sujet).
 *
 * Accédée depuis profil.php via un bouton "✏️ Modifier".
 * Affiche les articles de la commande avec des contrôles +/-
 * pour ajuster les quantités ou supprimer un article. Le total
 * se met à jour en temps réel côté client (JS).
 *
 * À la validation, le JS appelle api/modifier_commande.php qui
 * REFUSE toute augmentation au-delà du montant payé (règle prof :
 * un seul paiement par commande).
 *
 * Accès : client connecté + propriétaire de la commande
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['client']);

$commande_id = intval($_GET['id'] ?? 0);
if ($commande_id <= 0) {
    header('Location: profil.php');
    exit;
}

// Charge la commande et vérifie qu'elle est bien au client courant
$commandes = lire_json('commandes.json');
$commande = null;
foreach ($commandes as $c) {
    if ($c['id'] == $commande_id && $c['client_id'] == $_SESSION['user_id']) {
        $commande = $c;
        break;
    }
}

if (!$commande) {
    header('Location: profil.php');
    exit;
}

// Vérifie que la commande est modifiable (payée + pas en préparation)
$modifiable = !empty($commande['paiement_effectue'])
            && in_array($commande['statut'], ['a_preparer', 'en_attente'], true);

// Prépare un tableau d'articles enrichis avec nom et prix
$articles_enrichis = [];
foreach ($commande['articles'] as $a) {
    if (isset($a['menu_id'])) {
        $menu = trouver_menu_par_id($a['menu_id']);
        if ($menu) {
            $articles_enrichis[] = [
                'type'     => 'menu',
                'id'       => $menu['id'],
                'nom'      => $menu['nom'],
                'prix'     => $menu['prix_total'],
                'quantite' => $a['quantite'],
            ];
        }
    } else {
        $plat = trouver_plat_par_id($a['plat_id']);
        if ($plat) {
            $articles_enrichis[] = [
                'type'     => 'plat',
                'id'       => $plat['id'],
                'nom'      => $plat['nom'],
                'prix'     => $plat['prix'],
                'quantite' => $a['quantite'],
            ];
        }
    }
}

$total_paye = (float)$commande['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier ma commande | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="profil.css">
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('profil') ?>
    </header>

    <main style="max-width:700px; margin:2rem auto; padding:0 1rem;">

        <h1 style="margin-bottom:0.5rem;">✏️ Modifier ma commande #<?= $commande_id ?></h1>
        <p style="color:#666; margin-top:0;">
            Vous pouvez ajuster les quantités ou retirer des articles avant
            que la cuisine ne commence la préparation.
        </p>

        <?php if (!$modifiable): ?>
            <div class="profil-feedback profil-feedback-erreur" style="margin-top:1.5rem;">
                ⚠️ Cette commande ne peut plus être modifiée (statut actuel : <?= htmlspecialchars($commande['statut']) ?>).
            </div>
            <p style="margin-top:1rem;"><a href="profil.php">← Retour au profil</a></p>

        <?php else: ?>

            <!-- Rappel important de la règle métier -->
            <div style="background:#fff3cd; color:#856404; padding:1rem; border-radius:8px;
                        margin:1.5rem 0; font-size:0.9rem; border-left:4px solid #ffc107;">
                ℹ️ <strong>À savoir :</strong> votre paiement initial est définitif. Vous pouvez retirer
                des articles ou diminuer les quantités, mais le nouveau total ne peut pas dépasser
                le montant déjà payé (<strong><?= number_format($total_paye, 2, ',', ' ') ?> €</strong>).
                Aucun remboursement n'est effectué si vous diminuez la commande.
            </div>

            <!-- Bandeau de feedback AJAX -->
            <div id="modif-feedback" class="profil-feedback" style="display:none;"></div>

            <div class="card" style="padding:1.5rem;">
                <!-- Données du paiement passées au JS via data-attributes -->
                <div id="modif-commande"
                     data-commande-id="<?= $commande_id ?>"
                     data-total-paye="<?= $total_paye ?>">

                    <h2 style="margin-top:0;">🛒 Articles</h2>

                    <div id="liste-articles">
                        <?php foreach ($articles_enrichis as $a): ?>
                            <div class="ligne-article"
                                 data-type="<?= $a['type'] ?>"
                                 data-id="<?= $a['id'] ?>"
                                 data-prix="<?= $a['prix'] ?>"
                                 style="display:flex; align-items:center; justify-content:space-between;
                                        padding:0.8rem 0; border-bottom:1px solid #eee;">
                                <div>
                                    <strong><?= htmlspecialchars($a['nom']) ?></strong>
                                    <small style="color:#888; display:block;"><?= number_format($a['prix'], 2, ',', ' ') ?> € l'unité</small>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <button type="button" class="btn-qte btn-moins"
                                            style="width:32px; height:32px; border-radius:50%;
                                                   border:1px solid #ccc; background:white; cursor:pointer;
                                                   font-size:1.2rem; line-height:1;">−</button>
                                    <span class="qte-affichage" style="min-width:30px; text-align:center; font-weight:600;">
                                        <?= $a['quantite'] ?>
                                    </span>
                                    <button type="button" class="btn-qte btn-plus"
                                            style="width:32px; height:32px; border-radius:50%;
                                                   border:1px solid #ccc; background:white; cursor:pointer;
                                                   font-size:1.2rem; line-height:1;">+</button>
                                    <span class="sous-total" style="min-width:75px; text-align:right; font-weight:700; color:#014d14;">
                                        <?= number_format($a['prix'] * $a['quantite'], 2, ',', ' ') ?> €
                                    </span>
                                    <button type="button" class="btn-supprimer"
                                            style="background:none; border:none; cursor:pointer; font-size:1.1rem;
                                                   color:#c0392b; padding:4px;"
                                            title="Supprimer cet article">🗑️</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Récapitulatif du total -->
                    <div style="margin-top:1.5rem; padding-top:1rem; border-top:2px solid #eee;
                                display:flex; justify-content:space-between; font-size:1.05rem;">
                        <span>Total payé :</span>
                        <strong><?= number_format($total_paye, 2, ',', ' ') ?> €</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:1.2rem; font-weight:700;
                                margin-top:0.3rem;">
                        <span>Nouveau total :</span>
                        <strong id="nouveau-total" style="color:#014d14;"></strong>
                    </div>
                    <div id="perte-info" style="display:none; text-align:right;
                                                color:#856404; font-size:0.85rem; margin-top:0.3rem;">
                        Vous serez perdant de <span id="perte-montant"></span>
                    </div>
                    <div id="depassement-info" style="display:none; text-align:right;
                                                color:#c0392b; font-size:0.9rem; margin-top:0.3rem; font-weight:600;">
                        ⚠️ Dépassement de <span id="depassement-montant"></span> — modification impossible
                    </div>

                    <!-- Boutons de validation -->
                    <div style="display:flex; gap:1rem; margin-top:2rem;">
                        <button type="button" id="btn-enregistrer-modif" class="btn-voir"
                                style="flex:1; padding:0.8rem;">
                            💾 Enregistrer les modifications
                        </button>
                        <a href="profil.php" class="btn-voir" style="background:#aaa; padding:0.8rem;">
                            Annuler
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>

    <script src="js/modifier_commande.js"></script>
</body>
</html>
