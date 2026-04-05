<?php
/*
 * init.php
 * ---------------------------------------------------------------
 * Script d'initialisation à exécuter UNE SEULE FOIS après
 * l'installation du projet sur un nouveau serveur.
 *
 * Lit utilisateurs.json, hache les mots de passe en clair avec
 * bcrypt (PASSWORD_DEFAULT) et réécrit le fichier. Protégé contre
 * les doubles-exécutions : ignore les mots de passe déjà hashés
 * (ceux qui commencent par '$2y$').
 * Affiche un tableau récapitulatif de tous les comptes disponibles.
 *
 * Comptes par défaut (tous dans utilisateurs.json) :
 *   clients      → client123
 *   admins       → admin123
 *   restaurateur → resto123
 *   livreur      → livreur123
 *
 * URL : http://localhost/Projet-Info-S4-M1/init.php
 * À supprimer ou protéger après usage.
 *
 * Dépendances : includes/data.php
 */

require_once 'includes/data.php';

echo "<h2>🔧 Initialisation des mots de passe</h2>";

$utilisateurs = lire_json('utilisateurs.json');

$mots_de_passe = [
    1 => 'client123',
    2 => 'client123',
    3 => 'client123',
    4 => 'client123',
    5 => 'client123',
    6 => 'admin123',
    7 => 'admin123',
    8 => 'resto123',
    9 => 'livreur123',
];

foreach ($utilisateurs as &$u) {
    $id = $u['id'];
    if (isset($mots_de_passe[$id])) {
        $mdp_en_clair = $mots_de_passe[$id];
        if (empty($u['mot_de_passe']) || !str_starts_with($u['mot_de_passe'], '$2y$')) {
            $u['mot_de_passe'] = password_hash($mdp_en_clair, PASSWORD_DEFAULT);
            echo "<p>✅ Hashé : <strong>" . htmlspecialchars($u['login']) . "</strong> (mdp: <code>" . $mdp_en_clair . "</code>)</p>";
        } else {
            echo "<p>⏭️ Déjà hashé : <strong>" . htmlspecialchars($u['login']) . "</strong></p>";
        }
    }
}
unset($u);

ecrire_json('utilisateurs.json', $utilisateurs);

echo "<hr><h3>✅ Terminé !</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse;font-family:monospace;'>";
echo "<tr><th>Login</th><th>Mot de passe</th><th>Rôle</th></tr>";
echo "<tr><td>marie.dupont@email.fr</td><td>client123</td><td>client</td></tr>";
echo "<tr><td>douglas.costa@email.com</td><td>client123</td><td>client</td></tr>";
echo "<tr><td>neymar.jr10@email.com</td><td>client123</td><td>client</td></tr>";
echo "<tr><td>mark.evans@email.com</td><td>client123</td><td>client</td></tr>";
echo "<tr><td>roberto.hongo@email.com</td><td>client123</td><td>client</td></tr>";
echo "<tr><td>admin@ileaufruit.fr</td><td>admin123</td><td>admin</td></tr>";
echo "<tr><td>admin2@ileaufruit.fr</td><td>admin123</td><td>admin</td></tr>";
echo "<tr><td>restaurateur@ileaufruit.fr</td><td>resto123</td><td>restaurateur</td></tr>";
echo "<tr><td>livreur@ileaufruit.fr</td><td>livreur123</td><td>livreur</td></tr>";
echo "</table>";
echo "<br><p>⚠️ <strong>Pensez à supprimer ce fichier après usage !</strong></p>";
echo "<p><a href='index.php'>→ Accueil</a> | <a href='connexion.php'>→ Connexion</a></p>";
