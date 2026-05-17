<?php
/*
 * admin.php
 * ---------------------------------------------------------------
 * Tableau de bord d'administration (rôle : admin).
 *
 * Affiche des statistiques globales (nombre d'utilisateurs,
 * nouveaux ce mois, total commandes) et la liste complète des
 * utilisateurs avec leurs informations.
 *
 * Trois actions POST sont disponibles depuis le tableau :
 *   - Bloquer / activer un compte    (toggle_user_id)
 *   - Changer le statut fidélité     (statut_user_id + nouveau_statut)
 *     valeurs possibles : bronze, argent, gold, platine, vip, premium
 *   - Définir un pourcentage de remise (remise_user_id + remise 0-100)
 *     appliqué lors du paiement dans paiement.php
 * Un admin ne peut pas modifier son propre compte.
 * Les comptes bloqués apparaissent en opacité réduite.
 *
 * Accès : admin uniquement
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['admin']);

$message = '';

// Phase 3 : le bloquer/débloquer est maintenant traité en AJAX
// via api/bloquer_utilisateur.php (cf. js/admin.js). On laisse
// quand même le traitement PHP en fallback pour les éventuels
// utilisateurs sans JS.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $id   = intval($_POST['toggle_user_id']);
    $user = trouver_utilisateur_par_id($id);
    if ($user && $id !== $_SESSION['user_id']) {
        mettre_a_jour_utilisateur($id, ['actif' => !$user['actif']]);
        $message = $user['actif'] ? 'Compte bloqué.' : 'Compte activé.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['statut_user_id'])) {
    $id     = intval($_POST['statut_user_id']);
    $statut = $_POST['nouveau_statut'] ?? '';
    $statuts_valides = ['bronze', 'argent', 'gold', 'platine', 'vip', 'premium'];
    if ($id !== $_SESSION['user_id'] && in_array($statut, $statuts_valides)) {
        mettre_a_jour_utilisateur($id, ['statut' => $statut]);
        $message = 'Statut mis à jour.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remise_user_id'])) {
    $id     = intval($_POST['remise_user_id']);
    $remise = max(0, min(100, intval($_POST['remise'] ?? 0)));
    if ($id !== $_SESSION['user_id']) {
        mettre_a_jour_utilisateur($id, ['remise' => $remise]);
        $message = 'Remise de ' . $remise . '% appliquée.';
    }
}

$utilisateurs = lire_json('utilisateurs.json');
$commandes    = lire_json('commandes.json');

$nb_commandes_par_client = [];
foreach ($commandes as $c) {
    $cid = $c['client_id'];
    $nb_commandes_par_client[$cid] = ($nb_commandes_par_client[$cid] ?? 0) + 1;
}

$total_users = count($utilisateurs);
$clients = [];
foreach ($utilisateurs as $u) {
    if ($u['role'] === 'client') $clients[] = $u;
}

$nouveaux = [];
foreach ($clients as $u) {
    if ($u['date_inscription'] >= date('Y-m-01')) $nouveaux[] = $u;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="admin.css">
    <script src="js/theme.js"></script>
</head>
<body>
    <header>
        <?= nav_html('admin') ?>
    </header>

    <main>
        <section class="page-header">
            <h1>Administration</h1>
            <h3>Gestion des utilisateurs</h3>
        </section>

        <?php if ($message): ?>
            <p style="background:#d4edda; color:#155724; padding:0.8rem 1.5rem; margin:1rem auto; max-width:900px; border-radius:8px; text-align:center;">
                ✅ <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <!-- Bandeau de feedback AJAX (blocage/déblocage) -->
        <div id="admin-feedback" class="admin-feedback" style="display:none;
             max-width:900px; margin:1rem auto; padding:0.8rem 1.5rem;
             border-radius:8px; text-align:center; font-weight:500;"></div>

        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_users ?></div>
                    <p>Utilisateurs totaux</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($nouveaux) ?></div>
                    <p>Nouveaux ce mois</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($commandes) ?></div>
                    <p>Commandes total</p>
                </div>
            </div>
        </section>

        <section class="users-section">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Commandes</th>
                        <th>Statut fidélité</th>
                        <th>Remise</th>
                        <th>État</th>
                        <th>Profil</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utilisateurs as $u): ?>
                    <tr class="user-row <?= !$u['actif'] ? 'user-bloque' : '' ?>" data-user-id="<?= $u['id'] ?>">
                        <td><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                        <td><?= htmlspecialchars($u['login']) ?></td>
                        <td><?= htmlspecialchars($u['role']) ?></td>
                        <td><?= $nb_commandes_par_client[$u['id']] ?? 0 ?></td>

                        <!-- Statut fidélité (modifiable pour les clients) -->
                        <td>
                            <?php if ($u['role'] === 'client' && $u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="admin.php" style="display:flex; gap:4px;">
                                <input type="hidden" name="statut_user_id" value="<?= $u['id'] ?>">
                                <select name="nouveau_statut" class="filtre-select" style="font-size:0.8rem; padding:3px;">
                                    <?php foreach (['bronze','argent','gold','platine','vip','premium'] as $s): ?>
                                        <option value="<?= $s ?>" <?= ($u['statut'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-voir" style="font-size:0.75rem; padding:3px 8px;">OK</button>
                            </form>
                            <?php else: ?>
                                <?= htmlspecialchars($u['statut'] ?? '-') ?>
                            <?php endif; ?>
                        </td>

                        <!-- Remise (modifiable pour les clients) -->
                        <td>
                            <?php if ($u['role'] === 'client' && $u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="admin.php" style="display:flex; gap:4px; align-items:center;">
                                <input type="hidden" name="remise_user_id" value="<?= $u['id'] ?>">
                                <input type="number" name="remise" min="0" max="100"
                                       value="<?= intval($u['remise'] ?? 0) ?>"
                                       style="width:55px; padding:3px; font-size:0.8rem;">
                                <span style="font-size:0.8rem;">%</span>
                                <button type="submit" class="btn-voir" style="font-size:0.75rem; padding:3px 8px;">OK</button>
                            </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>

                        <td><?= $u['actif'] ? '✅ Actif' : '🔒 Bloqué' ?></td>

                        <!-- Lien vers le profil -->
                        <td>
                            <?php if ($u['role'] === 'client'): ?>
                                <a href="profil.php?user_id=<?= $u['id'] ?>" class="btn-voir" style="font-size:0.75rem; padding:3px 10px;">👤 Voir</a>
                            <?php else: ?>
                                <em style="color:#aaa;">—</em>
                            <?php endif; ?>
                        </td>

                        <!-- Bloquer / Débloquer (en AJAX, phase 3) -->
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <!-- Le bouton est en AJAX (fetch). Il n'y a plus de form POST. -->
                                <button type="button"
                                        class="btn-voir btn-toggle-actif <?= $u['actif'] ? 'btn-bloquer' : 'btn-debloquer' ?>"
                                        data-user-id="<?= $u['id'] ?>">
                                    <?= $u['actif'] ? '🚫 Bloquer' : '✅ Débloquer' ?>
                                </button>
                            <?php else: ?>
                                <em style="color:#aaa;">Vous</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>

    <script src="js/admin.js"></script>
</body>
</html>
