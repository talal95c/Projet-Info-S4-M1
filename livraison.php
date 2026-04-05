<?php
/*
 * livraison.php
 * ---------------------------------------------------------------
 * Page du livreur — interface optimisée pour smartphone.
 *
 * Recherche automatiquement la commande en cours assignée au
 * livreur connecté (statut 'en_livraison' et livreur_id = user_id).
 * Affiche toutes les informations nécessaires à la livraison :
 * adresse, code interphone, étage, téléphone, commentaire, liste
 * des articles et total. Fournit des liens directs vers Google Maps
 * et Waze avec l'adresse encodée.
 *
 * Deux actions POST :
 *   action='livree'     → passe la commande en statut 'livree'
 *   action='abandonnee' → passe la commande en statut 'abandonnee'
 *                         (adresse introuvable)
 * Si aucune commande n'est assignée, affiche un message d'attente.
 * Le CSS mobile-first (livraison.css) prévoit de grands boutons
 * (min 64px) pour une utilisation avec des gants.
 *
 * Accès : livreur uniquement
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['livreur']);

$livreur_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['commande_id'])) {
    $commande_id = intval($_POST['commande_id']);
    $action      = $_POST['action'];

    if ($action === 'livree') {
        mettre_a_jour_commande($commande_id, ['statut' => 'livree']);
        $message = '✅ Commande #' . $commande_id . ' marquée comme livrée !';
    } elseif ($action === 'abandonnee') {
        mettre_a_jour_commande($commande_id, ['statut' => 'abandonnee']);
        $message = '❌ Commande #' . $commande_id . ' marquée comme abandonnée.';
    }
}

$commande_en_cours = null;
foreach (lire_json('commandes.json') as $c) {
    if ($c['livreur_id'] == $livreur_id && $c['statut'] === 'en_livraison') {
        $commande_en_cours = $c;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraison | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="livraison.css">
</head>
<body>
    <header>
        <?= nav_html('livraison') ?>
    </header>

    <main>

        <?php if ($message): ?>
            <p style="background:#d4edda; color:#155724; padding:1rem; margin:1rem; border-radius:8px; text-align:center; font-weight:600;">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <?php if ($commande_en_cours): ?>

            <section class="page-header">
                <h1>Livraison #<?= $commande_en_cours['id'] ?></h1>
                <h3>Commande en cours</h3>
            </section>

            <section class="livraison-infos">
                <div class="info-card">
                    <h2>📍 Adresse</h2>
                    <p><?= htmlspecialchars($commande_en_cours['adresse_livraison']) ?></p>
                </div>

                <div class="info-card">
                    <h2>🔑 Code interphone</h2>
                    <p><?= htmlspecialchars($commande_en_cours['code_interphone']) ?: 'Aucun' ?></p>
                </div>

                <div class="info-card">
                    <h2>🏢 Étage</h2>
                    <p><?= htmlspecialchars($commande_en_cours['etage']) ?: 'Non précisé' ?></p>
                </div>

                <div class="info-card">
                    <h2>📞 Téléphone</h2>
                    <p><?= htmlspecialchars($commande_en_cours['telephone']) ?></p>
                </div>

                <?php if ($commande_en_cours['commentaire']): ?>
                <div class="info-card">
                    <h2>💬 Commentaires</h2>
                    <p><?= htmlspecialchars($commande_en_cours['commentaire']) ?></p>
                </div>
                <?php endif; ?>

                <div class="info-card">
                    <h2>🛍️ Articles</h2>
                    <p><?= noms_articles($commande_en_cours['articles']) ?></p>
                </div>

                <div class="info-card">
                    <h2>💰 Total</h2>
                    <p><?= number_format($commande_en_cours['total'], 2, ',', ' ') ?> €</p>
                </div>
            </section>

            <section class="livraison-boutons">
                <?php $adresse_url = urlencode($commande_en_cours['adresse_livraison']); ?>
                <a href="https://www.google.com/maps/search/?api=1&query=<?= $adresse_url ?>" class="btn-maps" target="_blank">📍 Ouvrir dans Maps</a>
                <a href="https://waze.com/ul?q=<?= $adresse_url ?>" class="btn-waze" target="_blank">🚗 Ouvrir dans Waze</a>

                <!-- Bouton : Livraison terminée -->
                <form method="POST" action="livraison.php" style="display:inline;">
                    <input type="hidden" name="action" value="livree">
                    <input type="hidden" name="commande_id" value="<?= $commande_en_cours['id'] ?>">
                    <button type="submit" class="btn-livree"
                        onclick="return confirm('Confirmer la livraison ?')">
                        ✅ Livraison terminée
                    </button>
                </form>

                <!-- Bouton : Livraison abandonnée -->
                <form method="POST" action="livraison.php" style="display:inline;">
                    <input type="hidden" name="action" value="abandonnee">
                    <input type="hidden" name="commande_id" value="<?= $commande_en_cours['id'] ?>">
                    <button type="submit" class="btn-abandonnee"
                        onclick="return confirm('Marquer cette livraison comme abandonnée (adresse introuvable) ?')">
                        ❌ Livraison abandonnée
                    </button>
                </form>
            </section>

        <?php else: ?>

            <section class="page-header">
                <h1>Mes livraisons</h1>
            </section>

            <section class="livraison-infos">
                <div class="info-card" style="grid-column:1/-1; text-align:center; padding:3rem;">
                    <h2>😴 Aucune livraison en cours</h2>
                    <p>Vous n'avez pas de commande assignée pour le moment.</p>
                </div>
            </section>

        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>
</body>
</html>
