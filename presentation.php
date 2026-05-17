<?php
/*
 * presentation.php
 * ---------------------------------------------------------------
 * Page de présentation de la carte (menu) du restaurant.
 *
 * Charge tous les plats disponibles depuis plats.json et les filtre
 * côté serveur selon les paramètres GET :
 *   - recherche  : filtre sur le nom ou la description du plat
 *   - categorie  : bowls, jus, desserts, plats, boissons
 *   - regime     : vegan, vegetarien, sans-gluten, sans-lactose
 *   - prix       : tranches 0-5€, 5-10€, 10-15€, 15€+
 * Les filtres sont envoyés via un formulaire GET (pas de JS requis).
 * Un bouton "Réinitialiser" apparaît si au moins un filtre est actif.
 *
 * Pour les clients connectés, chaque plat affiche un bouton
 * "+ Ajouter" qui envoie un POST pour ajouter l'article au panier
 * (stocké en $_SESSION['panier']). Un encart "Voir mon panier"
 * s'affiche en bas si le panier contient au moins un article.
 * Pour les visiteurs non connectés, le bouton renvoie vers connexion.php.
 *
 * Accès : tout le monde (panier réservé aux clients connectés)
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && est_connecte() && get_role() === 'client') {
    if (isset($_POST['plat_id'])) {
        $plat_id = intval($_POST['plat_id']);
        if (!isset($_SESSION['panier'])) $_SESSION['panier'] = [];
        $trouve = false;
        foreach ($_SESSION['panier'] as &$ligne) {
            if (isset($ligne['plat_id']) && $ligne['plat_id'] == $plat_id) {
                $ligne['quantite']++;
                $trouve = true;
                break;
            }
        }
        if (!$trouve) {
            $_SESSION['panier'][] = ['plat_id' => $plat_id, 'quantite' => 1];
        }
    } elseif (isset($_POST['menu_id'])) {
        $menu_id = intval($_POST['menu_id']);
        if (!isset($_SESSION['panier_menus'])) $_SESSION['panier_menus'] = [];
        $trouve = false;
        foreach ($_SESSION['panier_menus'] as &$ligne) {
            if ($ligne['menu_id'] == $menu_id) {
                $ligne['quantite']++;
                $trouve = true;
                break;
            }
        }
        if (!$trouve) {
            $_SESSION['panier_menus'][] = ['menu_id' => $menu_id, 'quantite' => 1];
        }
    }
    header('Location: presentation.php?' . http_build_query($_GET));
    exit;
}

$menus = lire_json('menus.json');
$menus = array_filter($menus, fn($m) => $m['disponible']);

$plats = lire_json('plats.json');
$plats = array_filter($plats, fn($p) => $p['disponible']);

$categorie = trim($_GET['categorie'] ?? '');
$regime    = trim($_GET['regime'] ?? '');
$prix      = trim($_GET['prix'] ?? '');
$recherche = trim($_GET['recherche'] ?? '');

if ($categorie !== '') {
    $plats = array_filter($plats, fn($p) => $p['categorie'] === $categorie);
}
if ($regime !== '') {
    $plats = array_filter($plats, fn($p) => in_array($regime, $p['tags']));
}
if ($prix !== '') {
    if ($prix === '0-5')  $plats = array_filter($plats, fn($p) => $p['prix'] < 5);
    elseif ($prix === '5-10')  $plats = array_filter($plats, fn($p) => $p['prix'] >= 5 && $p['prix'] <= 10);
    elseif ($prix === '10-15') $plats = array_filter($plats, fn($p) => $p['prix'] > 10 && $p['prix'] <= 15);
    elseif ($prix === '15+')   $plats = array_filter($plats, fn($p) => $p['prix'] > 15);
}
if ($recherche !== '') {
    $plats = array_filter($plats, fn($p) =>
        stripos($p['nom'], $recherche) !== false ||
        stripos($p['description'], $recherche) !== false
    );
}

$nb_panier = 0;
if (isset($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $l) $nb_panier += $l['quantite'];
}
if (isset($_SESSION['panier_menus'])) {
    foreach ($_SESSION['panier_menus'] as $l) $nb_panier += $l['quantite'];
}

$labels_tags = [
    'vegan'        => '🌱 Vegan',
    'vegetarien'   => '🥕 Végétarien',
    'sans-gluten'  => '🌾 Sans gluten',
    'sans-lactose' => '🥛 Sans lactose',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Produits | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="presentation.css">
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('presentation') ?>
    </header>

    <main>
        <section class="page-header">
            <h1>Notre carte</h1>
            <h3>Découvrez nos créations fraîches et gourmandes</h3>
        </section>

        <!-- Filtres via GET (rechargement de page, pas de JS) -->
        <section class="recherche-filtre">
            <form method="GET" action="presentation.php" class="recherche-container">

                <div class="barre-de-recherche">
                    <input type="text" name="recherche" placeholder="🔍 Rechercher un produit, un fruit..."
                           value="<?= htmlspecialchars($recherche) ?>">
                </div>

                <div class="filtre">
                    <div class="filtre-groupe">
                        <label for="categorie">Catégorie :</label>
                        <select name="categorie" id="categorie" class="filtre-select">
                            <option value="">Toutes les catégories</option>
                            <option value="bowls"    <?= $categorie === 'bowls'    ? 'selected' : '' ?>>🥗 Bowls Tropicaux</option>
                            <option value="jus"      <?= $categorie === 'jus'      ? 'selected' : '' ?>>🥤 Jus & Smoothies</option>
                            <option value="desserts" <?= $categorie === 'desserts' ? 'selected' : '' ?>>🍰 Desserts</option>
                            <option value="plats"    <?= $categorie === 'plats'    ? 'selected' : '' ?>>🍽️ Plats Gourmands</option>
                            <option value="boissons" <?= $categorie === 'boissons' ? 'selected' : '' ?>>☕ Boissons Chaudes</option>
                        </select>
                    </div>

                    <div class="filtre-groupe">
                        <label for="regime">Régime :</label>
                        <select name="regime" id="regime" class="filtre-select">
                            <option value="">Tous les régimes</option>
                            <option value="vegan"        <?= $regime === 'vegan'        ? 'selected' : '' ?>>🌱 Vegan</option>
                            <option value="vegetarien"   <?= $regime === 'vegetarien'   ? 'selected' : '' ?>>🥕 Végétarien</option>
                            <option value="sans-gluten"  <?= $regime === 'sans-gluten'  ? 'selected' : '' ?>>🌾 Sans gluten</option>
                            <option value="sans-lactose" <?= $regime === 'sans-lactose' ? 'selected' : '' ?>>🥛 Sans lactose</option>
                        </select>
                    </div>

                    <div class="filtre-groupe">
                        <label for="prix">Prix :</label>
                        <select name="prix" id="prix" class="filtre-select">
                            <option value="">Tous les prix</option>
                            <option value="0-5"   <?= $prix === '0-5'   ? 'selected' : '' ?>>Moins de 5€</option>
                            <option value="5-10"  <?= $prix === '5-10'  ? 'selected' : '' ?>>5€ - 10€</option>
                            <option value="10-15" <?= $prix === '10-15' ? 'selected' : '' ?>>10€ - 15€</option>
                            <option value="15+"   <?= $prix === '15+'   ? 'selected' : '' ?>>Plus de 15€</option>
                        </select>
                    </div>

                    <div class="filtre-groupe">
                        <label for="tri">Trier par :</label>
                        <select name="tri" id="tri" class="filtre-select">
                            <option value="">Ordre par défaut</option>
                            <option value="prix-asc">Prix croissant</option>
                            <option value="prix-desc">Prix décroissant</option>
                            <option value="nom-asc">Nom (A → Z)</option>
                            <option value="nom-desc">Nom (Z → A)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-filtrer">Filtrer</button>
                    <a href="presentation.php" class="btn-reset" id="btn-reset-filtres"
                       <?= ($categorie || $regime || $prix || $recherche) ? '' : 'style="display:none;"' ?>>
                        ✕ Réinitialiser
                    </a>
                </div>
            </form>
        </section>

        <!-- Indicateur de chargement AJAX (caché par défaut) -->
        <div id="ajax-loader" style="display:none; text-align:center; padding:1rem; color:#666;">
            ⏳ Chargement…
        </div>

        <!-- Section Menus configurés -->
        <section class="products-section">
            <h2 style="text-align:center; color:var(--green-dark); margin-bottom:1.5rem; font-size:1.6rem;">🍽️ Nos Menus</h2>
            <div class="product-grid">
                <?php foreach ($menus as $menu): ?>
                <div class="product-card">
                    <div class="product-info" style="padding:1.5rem;">
                        <h3><?= htmlspecialchars($menu['nom']) ?></h3>
                        <p class="product-description"><?= htmlspecialchars($menu['description']) ?></p>
                        <p style="font-size:0.82rem; color:#888; margin:0.4rem 0;">
                            🕐 <?= htmlspecialchars($menu['creneaux']) ?>
                            &nbsp;|&nbsp; 👥 <?= $menu['nb_personnes'] ?> pers.
                        </p>
                        <div style="margin-top:0.6rem;">
                            <?php foreach ($menu['plats_ids'] as $pid): ?>
                                <?php $p = trouver_plat_par_id($pid); ?>
                                <?php if ($p): ?>
                                    <span style="display:inline-block; background:#eef5f0; color:#014d14; border-radius:6px; padding:2px 8px; font-size:0.78rem; margin:2px;">
                                        <?= htmlspecialchars($p['nom']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="product-footer" style="margin-top:1rem;">
                            <span class="price"><?= number_format($menu['prix_total'], 2, ',', ' ') ?> €</span>
                            <?php if (est_connecte() && get_role() === 'client'): ?>
                                <form method="POST" action="presentation.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" style="display:inline;">
                                    <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                                    <button type="submit" class="btn-add">+ Ajouter</button>
                                </form>
                            <?php else: ?>
                                <a href="connexion.php" class="btn-add">Se connecter</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <hr style="border:none; border-top:2px solid #eef5f0; margin:2rem 1rem;">

        <!-- Section Plats à la carte (cible des filtres AJAX) -->
        <section class="products-section">
            <h2 style="text-align:center; color:var(--green-dark); margin-bottom:1.5rem; font-size:1.6rem;">🥗 Plats à la carte</h2>

            <!-- Message "aucun résultat" affiché quand la liste filtrée est vide -->
            <p id="message-aucun-plat"
               style="text-align:center; padding:3rem; color:#888; <?= empty($plats) ? '' : 'display:none;' ?>">
                Aucun produit trouvé pour ces filtres.
            </p>

            <div id="grille-plats" class="product-grid" <?= empty($plats) ? 'style="display:none;"' : '' ?>>
                <?php foreach ($plats as $plat): ?>
                <div class="product-card" data-category="<?= htmlspecialchars($plat['categorie']) ?>">
                    <div class="product-image">
                        <img src="<?= htmlspecialchars($plat['image']) ?>" alt="<?= htmlspecialchars($plat['nom']) ?>">
                    </div>
                    <div class="product-info">
                        <h3><?= htmlspecialchars($plat['nom']) ?></h3>
                        <p class="product-description"><?= htmlspecialchars($plat['description']) ?></p>
                        <?php if (!empty($plat['tags'])): ?>
                        <div class="product-tags">
                            <?php foreach ($plat['tags'] as $tag): ?>
                                <span class="tag <?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($labels_tags[$tag] ?? $tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="product-footer">
                            <span class="price"><?= number_format($plat['prix'], 2, ',', ' ') ?> €</span>
                            <?php if (est_connecte() && get_role() === 'client'): ?>
                                <form method="POST" action="presentation.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" style="display:inline;">
                                    <input type="hidden" name="plat_id" value="<?= $plat['id'] ?>">
                                    <button type="submit" class="btn-add">+ Ajouter</button>
                                </form>
                            <?php else: ?>
                                <a href="connexion.php" class="btn-add">Se connecter</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <!-- Fin section plats à la carte -->

        <?php if (est_connecte() && get_role() === 'client' && $nb_panier > 0): ?>
        <div style="text-align:center; padding:2rem;">
            <a href="panier.php" class="btn-voir" style="padding:0.8rem 2rem; font-size:1.1rem;">
                🛒 Voir mon panier (<?= $nb_panier ?> article<?= $nb_panier > 1 ? 's' : '' ?>)
            </a>
        </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>

    <script src="js/presentation.js"></script>
</body>
</html>
