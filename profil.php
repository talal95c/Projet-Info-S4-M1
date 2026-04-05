<?php
/*
 * profil.php
 * ---------------------------------------------------------------
 * Page de profil du client connecté.
 *
 * Affiche les informations personnelles de l'utilisateur (email,
 * téléphone, adresse, code interphone, étage), son niveau de
 * fidélité (Bronze / Argent / Gold / Platine selon les points)
 * avec un cercle de progression SVG, et l'historique de ses
 * commandes avec les statuts (en préparation, en livraison,
 * livrée, abandonnée).
 *
 * En mode édition (?edit=1), les champs modifiables (téléphone,
 * adresse, code interphone, étage) deviennent des inputs. Le
 * formulaire POST appelle mettre_a_jour_utilisateur() pour
 * sauvegarder les modifications dans utilisateurs.json.
 *
 * Accès : client connecté uniquement
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['client']);

$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_profil') {
    $telephone      = trim($_POST['telephone'] ?? '');
    $adresse        = trim($_POST['adresse'] ?? '');
    $code_interphone = trim($_POST['code_interphone'] ?? '');
    $etage          = trim($_POST['etage'] ?? '');

    if (empty($telephone) || empty($adresse)) {
        $erreur = 'Le téléphone et l\'adresse sont obligatoires.';
    } else {
        mettre_a_jour_utilisateur($_SESSION['user_id'], [
            'telephone'       => $telephone,
            'adresse'         => $adresse,
            'code_interphone' => $code_interphone,
            'etage'           => $etage,
        ]);
        $message = 'Vos informations ont bien été mises à jour.';
    }
}

$user = trouver_utilisateur_par_id($_SESSION['user_id']);
$commandes = commandes_du_client($_SESSION['user_id']);

$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

$points = $user['points_fidelite'];
if ($points >= 300)      $niveau = '💎 Platine';
elseif ($points >= 200)  $niveau = '🥇 Gold';
elseif ($points >= 100)  $niveau = '🥈 Argent';
else                     $niveau = '🥉 Bronze';

$mode_edition = isset($_GET['edit']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil | L'Île au Fruit</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="profil.css">
</head>
<body>
    <header>
        <?= nav_html('profil') ?>
    </header>

    <main>
        <section class="profil-header">
            <div class="avatar"><?= $initiales ?></div>
            <div>
                <h1><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h1>
                <p>Membre depuis <?= date('F Y', strtotime($user['date_inscription'])) ?></p>
                <span class="badge">⭐ <?= $niveau ?></span>
            </div>
        </section>

        <?php if ($message): ?>
            <p style="background:#d4edda; color:#155724; padding:1rem; margin:1rem auto; max-width:800px; border-radius:8px; text-align:center;">
                ✅ <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>
        <?php if ($erreur): ?>
            <p style="background:#f8d7da; color:#721c24; padding:1rem; margin:1rem auto; max-width:800px; border-radius:8px; text-align:center;">
                ⚠️ <?= htmlspecialchars($erreur) ?>
            </p>
        <?php endif; ?>

        <div class="profil-grid">

            <section class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h2>📋 Mes informations</h2>
                    <?php if (!$mode_edition): ?>
                        <a href="profil.php?edit=1" class="btn-voir" style="font-size:0.85rem;">✏️ Modifier</a>
                    <?php endif; ?>
                </div>

                <?php if ($mode_edition): ?>
                <!-- Formulaire d'édition -->
                <form method="POST" action="profil.php">
                    <input type="hidden" name="action" value="modifier_profil">

                    <div class="info-ligne">
                        <span>📧 Email</span>
                        <div><span><?= htmlspecialchars($user['login']) ?></span>
                            <small style="color:#aaa;">(non modifiable)</small>
                        </div>
                    </div>

                    <div class="info-ligne">
                        <label for="telephone">📱 Téléphone</label>
                        <div>
                            <input type="tel" id="telephone" name="telephone"
                                   value="<?= htmlspecialchars($user['telephone']) ?>"
                                   class="input-edit" required>
                        </div>
                    </div>

                    <div class="info-ligne">
                        <label for="adresse">📍 Adresse</label>
                        <div>
                            <input type="text" id="adresse" name="adresse"
                                   value="<?= htmlspecialchars($user['adresse']) ?>"
                                   class="input-edit" required>
                        </div>
                    </div>

                    <div class="info-ligne">
                        <label for="code_interphone">🔔 Code interphone</label>
                        <div>
                            <input type="text" id="code_interphone" name="code_interphone"
                                   value="<?= htmlspecialchars($user['code_interphone']) ?>"
                                   class="input-edit">
                        </div>
                    </div>

                    <div class="info-ligne">
                        <label for="etage">🏢 Étage</label>
                        <div>
                            <input type="text" id="etage" name="etage"
                                   value="<?= htmlspecialchars($user['etage']) ?>"
                                   class="input-edit">
                        </div>
                    </div>

                    <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                        <button type="submit" class="btn-voir">💾 Enregistrer</button>
                        <a href="profil.php" class="btn-voir" style="background:#aaa;">Annuler</a>
                    </div>
                </form>

                <?php else: ?>
                <!-- Affichage normal -->
                <div class="info-ligne">
                    <span>📧 Email</span>
                    <div><span><?= htmlspecialchars($user['login']) ?></span></div>
                </div>
                <div class="info-ligne">
                    <span>📱 Téléphone</span>
                    <div><span><?= htmlspecialchars($user['telephone']) ?></span></div>
                </div>
                <div class="info-ligne">
                    <span>📍 Adresse</span>
                    <div><span><?= htmlspecialchars($user['adresse']) ?></span></div>
                </div>
                <div class="info-ligne">
                    <span>🔔 Code interphone</span>
                    <div><span><?= htmlspecialchars($user['code_interphone']) ?: 'Aucun' ?></span></div>
                </div>
                <div class="info-ligne">
                    <span>🏢 Étage</span>
                    <div><span><?= htmlspecialchars($user['etage']) ?: 'Non précisé' ?></span></div>
                </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>💎 Compte fidélité</h2>

                <div class="cercle-container">
                    <svg class="cercle-svg" viewBox="0 0 120 120">
                        <circle class="cercle-fond" cx="60" cy="60" r="54"/>
                        <circle class="cercle-progression" cx="60" cy="60" r="54"/>
                    </svg>
                    <div class="cercle-texte">
                        <div class="points"><?= $points ?></div>
                        <div class="label">points</div>
                    </div>
                </div>

                <?php if ($points < 300): ?>
                    <p class="fidelite-info">
                        Plus que <strong><?= 300 - $points ?> points</strong> pour le niveau Platine !
                    </p>
                <?php else: ?>
                    <p class="fidelite-info">🎉 Vous êtes au niveau maximum !</p>
                <?php endif; ?>

                <div class="niveaux">
                    <div class="niveau <?= $points >= 0   ? 'actif' : '' ?>">🥉 Bronze</div>
                    <div class="niveau <?= $points >= 100 ? 'actif' : '' ?>">🥈 Argent</div>
                    <div class="niveau <?= $points >= 200 ? 'actif' : '' ?>">🥇 Gold</div>
                    <div class="niveau <?= $points >= 300 ? 'actif' : '' ?>">💎 Platine</div>
                </div>
            </section>

            <section class="card card-full">
                <h2>📦 Mes commandes</h2>

                <?php if (empty($commandes)): ?>
                    <p style="color:#888; text-align:center; padding:2rem;">Vous n'avez pas encore de commande.</p>
                <?php else: ?>
                    <?php foreach ($commandes as $c): ?>
                        <div class="commande-item">
                            <div class="commande-info">
                                <span class="commande-date"><?= date('d/m/Y à H:i', strtotime($c['date'])) ?></span>
                                <span class="commande-detail"><?= htmlspecialchars(noms_articles($c['articles'])) ?></span>
                            </div>
                            <div class="commande-right">
                                <span class="commande-prix"><?= number_format($c['total'], 2, ',', ' ') ?> €</span>
                                <?php if ($c['statut'] === 'livree'): ?>
                                    <span class="commande-statut livree">✅ Livrée</span>
                                    <?php if (empty($c['avis'])): ?>
                                        <a href="avis.php" class="btn-voir" style="font-size:0.8rem;">⭐ Noter</a>
                                    <?php endif; ?>
                                <?php elseif ($c['statut'] === 'en_livraison'): ?>
                                    <span class="commande-statut en-cours">🚴 En livraison</span>
                                <?php elseif ($c['statut'] === 'abandonnee'): ?>
                                    <span class="commande-statut" style="color:#dc3545;">❌ Abandonnée</span>
                                <?php else: ?>
                                    <span class="commande-statut">🍽️ En préparation</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>
</body>
</html>
