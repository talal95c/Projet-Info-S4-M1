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
if (!isset($_SESSION['panier_menus'])) $_SESSION['panier_menus'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action  = $_POST['action'];
    $plat_id = isset($_POST['plat_id']) ? intval($_POST['plat_id']) : 0;
    $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;

    if ($action === 'supprimer' && $plat_id) {
        $nouveau_panier = [];
        foreach ($_SESSION['panier'] as $l) {
            if ($l['plat_id'] != $plat_id) $nouveau_panier[] = $l;
        }
        $_SESSION['panier'] = $nouveau_panier;
    }
    if ($action === 'supprimer_menu' && $menu_id) {
        $nouveau_panier = [];
        foreach ($_SESSION['panier_menus'] as $l) {
            if ($l['menu_id'] != $menu_id) $nouveau_panier[] = $l;
        }
        $_SESSION['panier_menus'] = $nouveau_panier;
    }

    if ($action === 'modifier' && $plat_id) {
        $qte = isset($_POST['quantite']) ? intval($_POST['quantite']) : 1;
        if ($qte <= 0) {
            $nouveau_panier = [];
            foreach ($_SESSION['panier'] as $l) {
                if ($l['plat_id'] != $plat_id) $nouveau_panier[] = $l;
            }
            $_SESSION['panier'] = $nouveau_panier;
        } else {
            foreach ($_SESSION['panier'] as &$ligne) {
                if ($ligne['plat_id'] == $plat_id) { $ligne['quantite'] = $qte; break; }
            }
        }
    }
    if ($action === 'modifier_menu' && $menu_id) {
        $qte = isset($_POST['quantite']) ? intval($_POST['quantite']) : 1;
        if ($qte <= 0) {
            $nouveau_panier = [];
            foreach ($_SESSION['panier_menus'] as $l) {
                if ($l['menu_id'] != $menu_id) $nouveau_panier[] = $l;
            }
            $_SESSION['panier_menus'] = $nouveau_panier;
        } else {
            foreach ($_SESSION['panier_menus'] as &$ligne) {
                if ($ligne['menu_id'] == $menu_id) { $ligne['quantite'] = $qte; break; }
            }
        }
    }

    if ($action === 'vider') {
        $_SESSION['panier'] = [];
        $_SESSION['panier_menus'] = [];
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
    <script src="js/theme.js"></script>
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
                <div class="card" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; align-items:center; border-radius:12px; padding:1rem 1.5rem; margin-bottom:0.8rem;">
                    <div style="flex:1;">
                        <strong><?= htmlspecialchars($l['item']['nom']) ?></strong>
                        <?php if ($l['type'] === 'menu'): ?>
                            <span style="background:#eef5f0; color:#014d14; border-radius:4px; font-size:0.72rem; padding:1px 6px; margin-left:6px;">Menu</span>
                        <?php endif; ?>
                        <div style="font-size:0.85rem; opacity:0.8;">
                            <?= number_format($l['type'] === 'menu' ? $l['item']['prix_total'] : $l['item']['prix'], 2, ',', ' ') ?> € / unité
                        </div>
                    </div>

                    <!-- Modifier quantité -->
                    <form method="POST" action="panier.php" style="display:flex; align-items:center; gap:6px; margin:0 1rem;">
                        <?php if ($l['type'] === 'menu'): ?>
                            <input type="hidden" name="action" value="modifier_menu">
                            <input type="hidden" name="menu_id" value="<?= $l['item']['id'] ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="modifier">
                            <input type="hidden" name="plat_id" value="<?= $l['item']['id'] ?>">
                        <?php endif; ?>
                        <input type="number" name="quantite" value="<?= $l['quantite'] ?>" min="1" max="20"
                               style="width:55px; padding:4px; border:1px solid #ddd; border-radius:6px; text-align:center;">
                        <button type="submit" class="btn-voir" style="font-size:0.75rem; padding:4px 10px;">OK</button>
                    </form>

                    <div style="font-weight:600; min-width:70px; text-align:right;"><?= number_format($l['sous_total'], 2, ',', ' ') ?> €</div>

                    <!-- Supprimer -->
                    <form method="POST" action="panier.php" style="margin-left:0.8rem;">
                        <?php if ($l['type'] === 'menu'): ?>
                            <input type="hidden" name="action" value="supprimer_menu">
                            <input type="hidden" name="menu_id" value="<?= $l['item']['id'] ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="plat_id" value="<?= $l['item']['id'] ?>">
                        <?php endif; ?>
                        <button type="submit" style="background:none; border:none; color:#dc3545; cursor:pointer; font-size:1.2rem;">✕</button>
                    </form>
                </div>
                <?php endforeach; ?>

                <!-- Résumé total -->
                <div class="card" style="border-radius:12px; padding:1.5rem; margin-top:1rem;">
                    <div style="display:flex; justify-content:space-between; font-size:1rem; opacity:0.8; margin-bottom:0.4rem;">
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
