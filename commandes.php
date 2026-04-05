<?php
/*
 * commandes.php
 * ---------------------------------------------------------------
 * Page de gestion des commandes pour le restaurateur (et l'admin).
 *
 * Affiche trois zones :
 *   - "En attente"   : commandes différées (date_souhaitee future),
 *                      avec bouton pour lancer la préparation manuellement
 *   - "À préparer"   : commandes à traiter immédiatement, avec un
 *                      dropdown pour sélectionner un livreur actif
 *                      et un bouton pour passer en livraison
 *   - "En livraison" : commandes en cours, avec le nom du livreur assigné
 *
 * Actions POST disponibles :
 *   commande_id + livreur_id → passe la commande en 'en_livraison'
 *                              et assigne le livreur choisi
 *   preparer_id              → passe une commande 'en_attente' en 'a_preparer'
 *
 * Accès : restaurateur et admin
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

verifier_connexion(['restaurateur', 'admin']);

$message = '';

// Passer une commande en livraison avec sélection du livreur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commande_id'], $_POST['livreur_id'])) {
    $id         = intval($_POST['commande_id']);
    $livreur_id = intval($_POST['livreur_id']);
    if ($id > 0 && $livreur_id > 0) {
        mettre_a_jour_commande($id, ['statut' => 'en_livraison', 'livreur_id' => $livreur_id]);
        $message = 'Commande #' . $id . ' passée en livraison.';
    }
}

$tous_utilisateurs = lire_json('utilisateurs.json');
$livreurs = array_filter($tous_utilisateurs, fn($u) => $u['role'] === 'livreur' && $u['actif']);

// Charger les commandes et les trier par statut
$toutes       = lire_json('commandes.json');
$a_preparer   = [];
$en_attente   = [];
$en_livraison = [];

foreach ($toutes as $c) {
    if ($c['statut'] === 'a_preparer')   $a_preparer[]   = $c;
    if ($c['statut'] === 'en_attente')   $en_attente[]   = $c;
    if ($c['statut'] === 'en_livraison') $en_livraison[] = $c;
}

// Passer une commande "en_attente" en "a_preparer" si sa date souhaitée est atteinte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preparer_id'])) {
    $id = intval($_POST['preparer_id']);
    mettre_a_jour_commande($id, ['statut' => 'a_preparer']);
    $message = 'Commande #' . $id . ' passée en préparation.';
    header('Location: commandes.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes | L'Île au Fruit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="commandes.css">
</head>
<body>
    <header>
        <?= nav_html('commandes') ?>
    </header>

    <main>
        <section class="page-header">
            <h1>Commandes</h1>
            <h3>Gestion des commandes en cours</h3>
        </section>

        <?php if ($message): ?>
            <p style="background:#d4edda; color:#155724; padding:0.8rem 1.5rem; margin:1rem; border-radius:8px; text-align:center;">
                ✅ <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <div class="commandes-container">

            <!-- Colonne : En attente (commandes différées) -->
            <?php if (!empty($en_attente)): ?>
            <section class="commandes-colonne" style="grid-column:1/-1;">
                <div class="colonne-titre" style="background:#fff3cd; color:#856404;">
                    <h2>📅 En attente <span class="badge-count"><?= count($en_attente) ?></span></h2>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:1rem;">
                <?php foreach ($en_attente as $c):
                    $client = trouver_utilisateur_par_id($c['client_id']);
                    $heure  = date('H:i', strtotime($c['date']));
                    $date_s = isset($c['date_souhaitee']) && $c['date_souhaitee']
                              ? date('d/m/Y à H:i', strtotime($c['date_souhaitee']))
                              : '—';
                ?>
                    <div class="commande-card" style="min-width:240px; flex:1;">
                        <div class="commande-header">
                            <span class="commande-id">#<?= $c['id'] ?></span>
                            <span class="commande-heure"><?= $heure ?></span>
                        </div>
                        <div class="commande-client">👤 <?= $client ? htmlspecialchars($client['prenom'] . ' ' . $client['nom']) : 'Client inconnu' ?></div>
                        <ul class="commande-items">
                            <?php foreach ($c['articles'] as $article):
                                $plat = trouver_plat_par_id($article['plat_id']);
                            ?>
                                <li>× <?= $article['quantite'] ?> <?= $plat ? htmlspecialchars($plat['nom']) : 'Plat #' . $article['plat_id'] ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div style="font-size:0.85rem; color:#856404; margin:4px 0;">📅 Souhaitée le : <strong><?= $date_s ?></strong></div>
                        <form method="POST" action="commandes.php" style="margin-top:0.5rem;">
                            <input type="hidden" name="preparer_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-statut btn-livraison" style="background:#856404;">▶ Lancer la préparation</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Colonne : À préparer -->
            <section class="commandes-colonne">
                <div class="colonne-titre a-preparer">
                    <h2>🍽️ À préparer <span class="badge-count"><?= count($a_preparer) ?></span></h2>
                </div>

                <?php if (empty($a_preparer)): ?>
                    <p style="padding:1rem; color:#888;">Aucune commande à préparer.</p>
                <?php endif; ?>

                <?php foreach ($a_preparer as $c):
                    $client = trouver_utilisateur_par_id($c['client_id']);
                    $heure  = date('H:i', strtotime($c['date']));
                ?>
                    <div class="commande-card">
                        <div class="commande-header">
                            <span class="commande-id">#<?= $c['id'] ?></span>
                            <span class="commande-heure"><?= $heure ?></span>
                        </div>
                        <div class="commande-client">👤 <?= $client ? htmlspecialchars($client['prenom'] . ' ' . $client['nom']) : 'Client inconnu' ?></div>
                        <ul class="commande-items">
                            <?php foreach ($c['articles'] as $article):
                                $plat = trouver_plat_par_id($article['plat_id']);
                            ?>
                                <li>× <?= $article['quantite'] ?> <?= $plat ? htmlspecialchars($plat['nom']) : 'Plat #' . $article['plat_id'] ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="commande-adresse">📍 <?= htmlspecialchars($c['adresse_livraison']) ?></div>

                        <form method="POST" action="commandes.php">
                            <input type="hidden" name="commande_id" value="<?= $c['id'] ?>">
                            <div style="margin:0.5rem 0;">
                                <label style="font-size:0.85rem; color:#555;">Livreur :</label>
                                <select name="livreur_id" style="width:100%; margin-top:4px; padding:6px; border-radius:6px; border:1px solid #ddd;" required>
                                    <option value="">-- Choisir un livreur --</option>
                                    <?php foreach ($livreurs as $l): ?>
                                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['prenom'] . ' ' . $l['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-statut btn-livraison">🚴 Passer en livraison</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- Colonne : En livraison -->
            <section class="commandes-colonne">
                <div class="colonne-titre en-livraison">
                    <h2>🚴 En livraison <span class="badge-count"><?= count($en_livraison) ?></span></h2>
                </div>

                <?php if (empty($en_livraison)): ?>
                    <p style="padding:1rem; color:#888;">Aucune commande en livraison.</p>
                <?php endif; ?>

                <?php foreach ($en_livraison as $c):
                    $client  = trouver_utilisateur_par_id($c['client_id']);
                    $livreur = trouver_utilisateur_par_id($c['livreur_id']);
                    $heure   = date('H:i', strtotime($c['date']));
                ?>
                    <div class="commande-card en-cours">
                        <div class="commande-header">
                            <span class="commande-id">#<?= $c['id'] ?></span>
                            <span class="commande-heure"><?= $heure ?></span>
                        </div>
                        <div class="commande-client">👤 <?= $client ? htmlspecialchars($client['prenom'] . ' ' . $client['nom']) : 'Client inconnu' ?></div>
                        <ul class="commande-items">
                            <?php foreach ($c['articles'] as $article):
                                $plat = trouver_plat_par_id($article['plat_id']);
                            ?>
                                <li>× <?= $article['quantite'] ?> <?= $plat ? htmlspecialchars($plat['nom']) : 'Plat #' . $article['plat_id'] ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="commande-adresse">📍 <?= htmlspecialchars($c['adresse_livraison']) ?></div>
                        <?php if ($livreur): ?>
                            <div style="font-size:0.85rem; color:#555; margin-top:4px;">🚴 <?= htmlspecialchars($livreur['prenom'] . ' ' . $livreur['nom']) ?></div>
                        <?php endif; ?>
                        <div class="statut-label">🚴 En cours de livraison...</div>
                    </div>
                <?php endforeach; ?>
            </section>

        </div>
    </main>

    <footer>
        <p>&copy; 2026 L'Île au Fruit - Tous droits réservés.</p>
        <p>123 Rue des Fruits, 75000 Paris | Tél : 01 23 45 67 89 | Email : contact@ileaufruit.fr</p>
    </footer>
</body>
</html>
