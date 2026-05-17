<?php
/*
 * modifier_commande.php
 * ---------------------------------------------------------------
 * Page permettant à un client de modifier une commande payée
 * (ajout/suppression d'articles) tant qu'elle n'est pas en préparation.
 *
 * Dépendances : includes/session.php, includes/data.php,
 *               js/modifier_commande.js, api/modifier_commande.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['client']);

$commande_id = intval($_GET['id'] ?? 0);
if ($commande_id <= 0) {
    header('Location: profil.php');
    exit;
}

$commandes = lire_json('commandes.json');
$commande = null;
foreach ($commandes as $c) {
    if ($c['id'] == $commande_id) { $commande = $c; break; }
}

if (!$commande) {
    echo "Commande introuvable.";
    exit;
}

if ($commande['client_id'] !== $_SESSION['user_id']) {
    http_response_code(403);
    echo "Cette commande ne vous appartient pas.";
    exit;
}

// Vérifications de statut selon l'énoncé
if (empty($commande['paiement_effectue'])) {
    echo "Cette commande n'est pas encore payée. Rendez-vous sur votre profil pour payer.";
    exit;
}

// On ne peut modifier qu'une commande qui n'est pas encore passée "en préparation".
// (Donc 'a_preparer' ou 'en_attente')
if (!in_array($commande['statut'], ['a_preparer', 'en_attente'])) {
    echo "Cette commande ne peut plus être modifiée (elle est déjà en cours de préparation ou terminée).";
    exit;
}

$user = trouver_utilisateur_par_id($_SESSION['user_id']);
$total_paye = floatval($commande['total']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier ma commande | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="panier.css">
    <script src="js/theme.js"></script>
    <style>
        .modif-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .article-card { display: flex; justify-content: space-between; align-items: center; background: var(--white); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 0.5rem; }
        .article-info { flex: 1; }
        .article-actions { display: flex; align-items: center; gap: 1rem; }
        .btn-qty { background: #eee; border: none; padding: 0.5rem 0.8rem; border-radius: 4px; cursor: pointer; font-weight: bold; color: #333; }
        .btn-supprimer { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2rem; }
        .btn-qty:hover { background: #ddd; }
        .btn-supprimer:hover { color: #c0392b; }
    </style>
</head>
<body>
    <header>
        <?= nav_html('profil') ?>
    </header>

    <main class="modif-container">
        <h1>✏️ Modifier la commande #<?= $commande_id ?></h1>
        <p style="color: #666; margin-bottom: 2rem;">
            Ajustez les quantités. Attention, vous ne pouvez pas dépasser le montant initial payé (<strong><?= number_format($total_paye, 2, ',', ' ') ?> €</strong>).
        </p>

        <div id="modif-feedback" style="display:none; padding:1rem; border-radius:8px; margin-bottom:1rem; font-weight:bold;"></div>

        <div id="modif-commande" data-commande-id="<?= $commande_id ?>" data-total-paye="<?= $total_paye ?>">
            
            <div class="articles-list" style="margin-bottom: 2rem;">
                <?php foreach ($commande['articles'] as $article): 
                    $type = isset($article['menu_id']) ? 'menu' : 'plat';
                    $id_item = $type === 'menu' ? $article['menu_id'] : $article['plat_id'];
                    $item = $type === 'menu' ? trouver_menu_par_id($id_item) : trouver_plat_par_id($id_item);
                    
                    if (!$item) continue;
                    
                    // Calcul du prix unitaire (en tenant compte de la remise du client)
                    $prix_brut = floatval($type === 'menu' ? $item['prix_total'] : $item['prix']);
                    $remise = intval($user['remise'] ?? 0);
                    $prix = $remise > 0 ? $prix_brut * (1 - $remise / 100) : $prix_brut;
                ?>
                <div class="article-card ligne-article" data-type="<?= $type ?>" data-id="<?= $id_item ?>" data-prix="<?= $prix ?>">
                    <div class="article-info">
                        <strong><?= htmlspecialchars($item['nom']) ?></strong>
                        <?php if ($type === 'menu'): ?>
                            <span style="font-size:0.8rem; background:#eef5f0; color:#014d14; padding:2px 6px; border-radius:4px;">Menu</span>
                        <?php endif; ?>
                        <div style="font-size: 0.9rem; color: #666; margin-top: 4px;">
                            <?= number_format($prix, 2, ',', ' ') ?> € / unité
                        </div>
                    </div>
                    <div class="article-actions">
                        <div style="display:flex; align-items:center; gap:0.5rem; background:#f9f9f9; padding:4px; border-radius:6px;">
                            <button type="button" class="btn-qty btn-moins">-</button>
                            <span class="qte-affichage" style="font-weight:600; min-width:20px; text-align:center; color:#333;"><?= $article['quantite'] ?></span>
                            <button type="button" class="btn-qty btn-plus">+</button>
                        </div>
                        <div style="font-weight: bold; width: 70px; text-align: right;">
                            <span class="sous-total"></span>
                        </div>
                        <button type="button" class="btn-supprimer" title="Retirer">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="background: var(--white); padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem; color: #555;">
                    <span>Montant initialement payé :</span>
                    <strong><?= number_format($total_paye, 2, ',', ' ') ?> €</strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 1.2rem; margin-top: 1rem; border-top: 1px solid #eee; padding-top: 1rem;">
                    <strong>Nouveau total :</strong>
                    <strong id="nouveau-total" style="color: #014d14;"></strong>
                </div>

                <!-- Messages dynamiques gérés par le JS -->
                <div id="perte-info" style="display:none; background: #fff3cd; color: #856404; padding: 0.8rem; border-radius: 6px; margin-top: 1rem; font-size: 0.9rem;">
                    💡 Votre nouveau total est inférieur au montant payé. Conformément à nos CGV, la différence de <strong><span id="perte-montant"></span></strong> ne vous sera pas remboursée.
                </div>
                <div id="depassement-info" style="display:none; background: #f8d7da; color: #721c24; padding: 0.8rem; border-radius: 6px; margin-top: 1rem; font-size: 0.9rem;">
                    ⚠️ Dépassement de budget : votre nouveau panier excède le montant initialement payé de <strong><span id="depassement-montant"></span></strong>. Veuillez réduire les quantités.
                </div>

                <div style="margin-top: 2rem; text-align: right;">
                    <a href="profil.php" style="color: #666; text-decoration: none; margin-right: 1.5rem; font-weight: 500;">Annuler</a>
                    <button id="btn-enregistrer-modif" class="submit-btn" style="padding: 0.8rem 2rem; font-size: 1.05rem;">
                        💾 Enregistrer les modifications
                    </button>
                </div>
            </div>

        </div>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>

    <script src="js/modifier_commande.js"></script>
</body>
</html>
