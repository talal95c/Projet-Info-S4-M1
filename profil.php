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

verifier_connexion(['client', 'admin']);

$message = '';
$erreur  = '';

// Détermine l'utilisateur à afficher
// - admin avec ?user_id=X → affiche le profil du client X (lecture seule)
// - client → affiche son propre profil (éditable)
$role_connecte = get_role();
$vue_admin = false;

if ($role_connecte === 'admin') {
    $user_id_cible = intval($_GET['user_id'] ?? 0);
    if ($user_id_cible <= 0) {
        header('Location: admin.php');
        exit;
    }
    $vue_admin = true;
} else {
    $user_id_cible = $_SESSION['user_id'];
}

if (!$vue_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_profil') {
    $telephone      = trim($_POST['telephone'] ?? '');
    $adresse        = trim($_POST['adresse'] ?? '');
    $code_interphone = trim($_POST['code_interphone'] ?? '');
    $etage          = trim($_POST['etage'] ?? '');

    if (empty($telephone) || empty($adresse)) {
        $erreur = 'Le téléphone et l\'adresse sont obligatoires.';
    } else {
        mettre_a_jour_utilisateur($user_id_cible, [
            'telephone'       => $telephone,
            'adresse'         => $adresse,
            'code_interphone' => $code_interphone,
            'etage'           => $etage,
        ]);
        $message = 'Vos informations ont bien été mises à jour.';
    }
}

$user = trouver_utilisateur_par_id($user_id_cible);
$commandes = commandes_du_client($user_id_cible);

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
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('profil') ?>
    </header>

    <main>
        <?php if ($vue_admin): ?>
        <div style="background:#fff3cd; color:#856404; padding:0.8rem 1.5rem; text-align:center; font-size:0.9rem;">
            👁️ Vue administrateur — profil de <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
            &nbsp;|&nbsp; <a href="admin.php" style="color:#856404; font-weight:600;">← Retour à l'administration</a>
        </div>
        <?php endif; ?>

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
                    <?php if (!$vue_admin): ?>
                        <!-- Phase 3 : le bouton bascule en JS sans recharger.
                             Le href reste un fallback no-JS vers ?edit=1.    -->
                        <a id="btn-modifier-profil" href="profil.php?edit=1"
                           class="btn-voir" style="font-size:0.85rem;
                           <?= $mode_edition ? 'display:none;' : '' ?>">
                            ✏️ Modifier
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Zone d'alerte AJAX (succès / erreur), pilotée par js/profil.js -->
                <div id="profil-feedback" class="profil-feedback" style="display:none;"></div>

                <?php if (!$vue_admin): ?>
                <!-- Bloc d'AFFICHAGE (visible par défaut, masqué en mode édition) -->
                <div id="bloc-affichage-profil" <?= $mode_edition ? 'style="display:none;"' : '' ?>>
                    <div class="info-ligne">
                        <span>📧 Email</span>
                        <div><span><?= htmlspecialchars($user['login']) ?></span></div>
                    </div>
                    <div class="info-ligne">
                        <span>📱 Téléphone</span>
                        <div><span data-affichage="telephone"><?= htmlspecialchars($user['telephone']) ?></span></div>
                    </div>
                    <div class="info-ligne">
                        <span>📍 Adresse</span>
                        <div><span data-affichage="adresse"><?= htmlspecialchars($user['adresse']) ?></span></div>
                    </div>
                    <div class="info-ligne">
                        <span>🔔 Code interphone</span>
                        <div><span data-affichage="code_interphone"><?= htmlspecialchars($user['code_interphone']) ?: 'Aucun' ?></span></div>
                    </div>
                    <div class="info-ligne">
                        <span>🏢 Étage</span>
                        <div><span data-affichage="etage"><?= htmlspecialchars($user['etage']) ?: 'Non précisé' ?></span></div>
                    </div>
                </div>

                <!-- Bloc d'ÉDITION (masqué par défaut, affiché par JS au clic Modifier) -->
                <!-- Le form garde method=POST + action=profil.php comme fallback no-JS. -->
                <form id="formulaire-profil" method="POST" action="profil.php"
                      <?= $mode_edition ? '' : 'style="display:none;"' ?> novalidate>
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
                                   maxlength="15" data-compteur
                                   class="input-edit" required>
                        </div>
                    </div>

                    <div class="info-ligne">
                        <label for="adresse">📍 Adresse</label>
                        <div>
                            <input type="text" id="adresse" name="adresse"
                                   value="<?= htmlspecialchars($user['adresse']) ?>"
                                   maxlength="200" data-compteur
                                   class="input-edit" required>
                        </div>
                    </div>

                    <div class="info-ligne">
                        <label for="code_interphone">🔔 Code interphone</label>
                        <div>
                            <input type="text" id="code_interphone" name="code_interphone"
                                   value="<?= htmlspecialchars($user['code_interphone']) ?>"
                                   maxlength="20" data-compteur
                                   class="input-edit">
                        </div>
                    </div>

                    <div class="info-ligne">
                        <label for="etage">🏢 Étage</label>
                        <div>
                            <input type="text" id="etage" name="etage"
                                   value="<?= htmlspecialchars($user['etage']) ?>"
                                   maxlength="100" data-compteur
                                   class="input-edit">
                        </div>
                    </div>

                    <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                        <button type="submit" class="btn-voir" id="btn-enregistrer-profil">
                            💾 Enregistrer
                        </button>
                        <a href="profil.php" id="btn-annuler-profil"
                           class="btn-voir" style="background:#aaa;">Annuler</a>
                    </div>
                </form>
                <?php else: ?>
                <!-- Vue administrateur : lecture seule -->
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

                <?php
                    $progress  = min($points, 300) / 300;
                    $dashoffset = round(339.3 * (1 - $progress), 2);
                ?>
                <div class="cercle-container">
                    <svg class="cercle-svg" viewBox="0 0 120 120">
                        <circle class="cercle-fond" cx="60" cy="60" r="54"/>
                        <circle class="cercle-progression" cx="60" cy="60" r="54"
                                style="stroke-dashoffset: <?= $dashoffset ?>"/>
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
                                <?php elseif ($c['statut'] === 'prete'): ?>
                                    <span class="commande-statut" style="background:#d1ecf1; color:#0c5460;">📦 Prête</span>
                                <?php elseif ($c['statut'] === 'abandonnee'): ?>
                                    <span class="commande-statut" style="color:#dc3545;">❌ Abandonnée</span>
                                <?php elseif ($c['statut'] === 'a_preparer' || $c['statut'] === 'en_attente'): ?>
                                    <span class="commande-statut">🍽️ <?= $c['statut'] === 'en_attente' ? 'Programmée' : 'En préparation' ?></span>
                                    <?php if (!empty($c['paiement_effectue'])): ?>
                                        <!-- Phase 3 : seule la commande payée mais pas encore en cuisine
                                             peut être modifiée (cf. sujet + règle prof "un seul paiement"). -->
                                        <a href="modifier_commande.php?id=<?= $c['id'] ?>"
                                           class="btn-voir" style="font-size:0.8rem; background:#3da874;">
                                            ✏️ Modifier
                                        </a>
                                    <?php endif; ?>
                                <?php elseif ($c['statut'] === 'paiement_refuse'): ?>
                                    <span class="commande-statut" style="color:#dc3545;">❌ Paiement refusé</span>
                                <?php else: ?>
                                    <span class="commande-statut">🍽️ <?= htmlspecialchars($c['statut']) ?></span>
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

    <script src="js/common.js"></script>
    <script src="js/profil.js"></script>
</body>
</html>
