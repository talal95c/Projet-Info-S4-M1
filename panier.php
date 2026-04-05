<?php
/*
 * panier.php
 * ---------------------------------------------------------------
 * Page panier du client connecté.
 *
 * Le panier est stocké en session ($_SESSION['panier']), sous la
 * forme d'un tableau de lignes {plat_id, quantite}.
 * Les articles sont ajoutés depuis presentation.php (bouton POST).
 *
 * Trois actions POST disponibles :
 *   action='modifier'  → met à jour la quantité d'un article
 *                        (supprime l'article si quantité <= 0)
 *   action='supprimer' → retire un article du panier
 *   action='vider'     → supprime tous les articles
 *
 * Calcule le sous-total par ligne, le total général et applique
 * automatiquement la remise (%) définie par l'admin sur le compte
 * client (champ 'remise' dans utilisateurs.json).
 * Lien vers paiement.php pour finaliser la commande.
 *
 * Accès : client connecté uniquement
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['client']);

if (!isset($_SESSION['panier'])) $_SESSION['panier'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $plat_id = intval($_POST['plat_id'] ?? 0);

    if ($_POST['action'] === 'supprimer') {
        $_SESSION['panier'] = array_filter($_SESSION['panier'], fn($l) => $l['plat_id'] != $plat_id);
        $_SESSION['panier'] = array_values($_SESSION['panier']);
    }

    if ($_POST['action'] === 'modifier') {
        $qte = intval($_POST['quantite'] ?? 1);
        if ($qte <= 0) {
            $_SESSION['panier'] = array_filter($_SESSION['panier'], fn($l) => $l['plat_id'] != $plat_id);
            $_SESSION['panier'] = array_values($_SESSION['panier']);
        } else {
            foreach ($_SESSION['panier'] as &$ligne) {
                if ($ligne['plat_id'] == $plat_id) {
                    $ligne['quantite'] = $qte;
                    break;
                }
            }
        }
    }

    if ($_POST['action'] === 'vider') {
        $_SESSION['panier'] = [];
    }

    header('Location: panier.php');
    exit;
}

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

$user   = trouver_utilisateur_par_id($_SESSION['user_id']);
$remise = intval($user['remise'] ?? 0);
$total_apres_remise = $remise > 0 ? $total * (1 - $remise / 100) : $total;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <header>
        <?= nav_html('panier') ?>
    </header>

    <main>
        <section class="page-header" style="max-width:700px; margin:2rem auto 1rem; padding:0 1rem;">
            <h1>🛒 Mon Panier</h1>
        </section>

        <?php if (empty($lignes)): ?>
            <div style="text-align:center; padding:4rem 1rem; color:#888;">
                <p style="font-size:1.2rem;">Votre panier est vide.</p>
                <a href="presentation.php" class="btn-voir" style="margin-top:1rem; display:inline-block;">Voir la carte</a>
            </div>

        <?php else: ?>
            <div style="max-width:700px; margin:0 auto; padding:0 1rem;">

                <!-- Liste des articles -->
                <?php foreach ($lignes as $l): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; background:white; border-radius:12px; padding:1rem 1.5rem; margin-bottom:0.8rem; box-shadow:0 4px 12px rgba(0,0,0,0.06);">
                    <div style="flex:1;">
                        <strong><?= htmlspecialchars($l['plat']['nom']) ?></strong>
                        <div style="color:#777; font-size:0.85rem;"><?= number_format($l['plat']['prix'], 2, ',', ' ') ?> € / unité</div>
                    </div>

                    <!-- Modifier quantité -->
                    <form method="POST" action="panier.php" style="display:flex; align-items:center; gap:6px; margin:0 1rem;">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="plat_id" value="<?= $l['plat']['id'] ?>">
                        <input type="number" name="quantite" value="<?= $l['quantite'] ?>" min="1" max="20"
                               style="width:55px; padding:4px; border:1px solid #ddd; border-radius:6px; text-align:center;">
                        <button type="submit" class="btn-voir" style="font-size:0.75rem; padding:4px 10px;">OK</button>
                    </form>

                    <div style="font-weight:600; min-width:70px; text-align:right;"><?= number_format($l['sous_total'], 2, ',', ' ') ?> €</div>

                    <!-- Supprimer -->
                    <form method="POST" action="panier.php" style="margin-left:0.8rem;">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="plat_id" value="<?= $l['plat']['id'] ?>">
                        <button type="submit" style="background:none; border:none; color:#dc3545; cursor:pointer; font-size:1.2rem;">✕</button>
                    </form>
                </div>
                <?php endforeach; ?>

                <!-- Résumé total -->
                <div style="background:white; border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.06); margin-top:1rem;">
                    <div style="display:flex; justify-content:space-between; font-size:1rem; color:#555; margin-bottom:0.4rem;">
                        <span>Sous-total</span>
                        <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                    </div>
                    <?php if ($remise > 0): ?>
                    <div style="display:flex; justify-content:space-between; font-size:1rem; color:#28a745; margin-bottom:0.4rem;">
                        <span>Remise (<?= $remise ?>%)</span>
                        <span>-<?= number_format($total - $total_apres_remise, 2, ',', ' ') ?> €</span>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between; font-size:1.2rem; font-weight:700; border-top:1px solid #eee; padding-top:0.8rem; margin-top:0.8rem;">
                        <span>Total</span>
                        <span><?= number_format($total_apres_remise, 2, ',', ' ') ?> €</span>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display:flex; gap:1rem; margin-top:1.5rem; flex-wrap:wrap;">
                    <a href="presentation.php" class="btn-voir" style="background:#aaa;">← Continuer mes achats</a>
                    <a href="paiement.php" class="btn-voir" style="flex:1; text-align:center; font-size:1rem;">💳 Passer la commande</a>
                </div>

                <form method="POST" action="panier.php" style="margin-top:0.8rem;">
                    <input type="hidden" name="action" value="vider">
                    <button type="submit" style="background:none; border:none; color:#dc3545; cursor:pointer; font-size:0.85rem; text-decoration:underline;">
                        Vider le panier
                    </button>
                </form>

            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>
</body>
</html>
