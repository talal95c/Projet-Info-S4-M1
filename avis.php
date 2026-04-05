<?php
/*
 * avis.php
 * ---------------------------------------------------------------
 * Page de notation d'une commande livrée (rôle : client).
 *
 * Cherche automatiquement la première commande du client dont le
 * statut est 'livree' et dont le champ 'avis' est null.
 * Affiche un formulaire avec deux blocs de notation par étoiles
 * (1 à 5) : un pour la livraison, un pour les produits, chacun
 * accompagné d'un champ commentaire facultatif.
 * À la soumission, l'avis est sauvegardé dans commandes.json via
 * mettre_a_jour_commande(). Le formulaire disparaît une fois noté.
 * Contient un script JS inline pour l'interaction des étoiles
 * (clic → coloration + mise à jour du champ caché).
 *
 * Accès : client connecté uniquement
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['client']);

$message = '';
$commande_a_noter = null;

$commandes = commandes_du_client($_SESSION['user_id']);
foreach ($commandes as $c) {
    if ($c['statut'] === 'livree' && $c['avis'] === null) {
        $commande_a_noter = $c;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $commande_a_noter) {
    $note_livraison       = intval($_POST['note_livraison']);
    $commentaire_livraison = trim($_POST['commentaire_livraison']);
    $note_produits        = intval($_POST['note_produits']);
    $commentaire_produits  = trim($_POST['commentaire_produits']);

    $avis = [
        'note_livraison'        => $note_livraison,
        'commentaire_livraison' => $commentaire_livraison,
        'note_produits'         => $note_produits,
        'commentaire_produits'  => $commentaire_produits,
        'date'                  => date('Y-m-d H:i:s')
    ];

    mettre_a_jour_commande($commande_a_noter['id'], ['avis' => $avis]);
    $message = 'Merci pour votre avis sur la commande #' . $commande_a_noter['id'] . ' !';
    $commande_a_noter = null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notation | L'Île au Fruit</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="avis.css">
</head>
<body>
    <header>
        <?= nav_html('avis') ?>
    </header>

    <main class="avis-main">
        <section class="avis-card">
            <h1>⭐ Votre avis</h1>
            <p class="avis-subtitle">Notez votre commande et la qualité de la livraison</p>

            <?php if ($message): ?>
                <div class="message succes" style="margin-bottom:1.5rem;">✅ <?= $message ?></div>
                <p style="text-align:center;"><a href="profil.php">← Retour à mon profil</a></p>

            <?php elseif ($commande_a_noter): ?>

                <p style="margin-bottom:1rem; color:#666;">
                    Commande #<?= $commande_a_noter['id'] ?> du <?= date('d/m/Y', strtotime($commande_a_noter['date'])) ?><br>
                    <small><?= htmlspecialchars(noms_articles($commande_a_noter['articles'])) ?></small>
                </p>

                <form class="auth-form" method="POST" action="avis.php">

                    <div class="avis-section-titre">🚴 Livraison</div>

                    <div class="input-group">
                        <label>Note de la livraison</label>
                        <div class="etoiles" id="etoiles-livraison">
                            <span class="etoile" data-value="1">★</span>
                            <span class="etoile" data-value="2">★</span>
                            <span class="etoile" data-value="3">★</span>
                            <span class="etoile" data-value="4">★</span>
                            <span class="etoile" data-value="5">★</span>
                        </div>
                        <input type="hidden" name="note_livraison" id="note-livraison" required>
                    </div>

                    <div class="input-group">
                        <label>Commentaire sur la livraison</label>
                        <textarea name="commentaire_livraison" placeholder="Rapidité, état du colis, livreur..."></textarea>
                    </div>

                    <div class="avis-section-titre">🍽️ Produits</div>

                    <div class="input-group">
                        <label>Note des produits</label>
                        <div class="etoiles" id="etoiles-produits">
                            <span class="etoile" data-value="1">★</span>
                            <span class="etoile" data-value="2">★</span>
                            <span class="etoile" data-value="3">★</span>
                            <span class="etoile" data-value="4">★</span>
                            <span class="etoile" data-value="5">★</span>
                        </div>
                        <input type="hidden" name="note_produits" id="note-produits" required>
                    </div>

                    <div class="input-group">
                        <label>Votre avis sur les produits</label>
                        <textarea name="commentaire_produits" placeholder="Fraîcheur, goût, présentation..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">Poster mon avis</button>
                </form>

            <?php else: ?>
                <p style="text-align:center; color:#888; padding:2rem;">
                    😊 Vous n'avez aucune commande à noter pour le moment.
                </p>
                <p style="text-align:center;"><a href="profil.php">← Retour à mon profil</a></p>
            <?php endif; ?>

        </section>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>

    <script>
    document.querySelectorAll('.etoiles').forEach(function(bloc) {
        var etoiles = bloc.querySelectorAll('.etoile');
        var input   = document.getElementById('note-' + bloc.id.replace('etoiles-', ''));

        etoiles.forEach(function(etoile) {
            etoile.addEventListener('click', function() {
                var val = this.getAttribute('data-value');
                input.value = val;
                etoiles.forEach(function(e) {
                    e.classList.toggle('active', e.getAttribute('data-value') <= val);
                });
            });
        });
    });
    </script>
</body>
</html>
