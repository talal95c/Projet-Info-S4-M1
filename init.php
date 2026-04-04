<?php
// init.php — Script à exécuter UNE SEULE FOIS pour initialiser les mots de passe
// ⚠️ À exécuter via le navigateur : http://localhost/Projet-Info-S4-M1/init.php
// ⚠️ Supprimer ou protéger ce fichier après usage !

require_once 'includes/data.php';

echo "<h2>🔧 Initialisation des mots de passe</h2>";

$utilisateurs = lire_json('utilisateurs.json');

// Définir les mots de passe par rôle
$mots_de_passe = [
    // id => mot_de_passe en clair
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
        // Hasher le mot de passe seulement s'il est vide ou pas encore hashé
        if (empty($u['mot_de_passe']) || !str_starts_with($u['mot_de_passe'], '$2y$')) {
            $u['mot_de_passe'] = password_hash($mdp_en_clair, PASSWORD_DEFAULT);
            echo "<p>✅ Mot de passe hashé pour : <strong>" . htmlspecialchars($u['login']) . "</strong> (mdp: <code>" . $mdp_en_clair . "</code>)</p>";
        } else {
            echo "<p>⏭️ Déjà hashé : <strong>" . htmlspecialchars($u['login']) . "</strong></p>";
        }
    }
}
unset($u); // Libérer la référence

ecrire_json('utilisateurs.json', $utilisateurs);

echo "<hr>";
echo "<h3>✅ Initialisation terminée !</h3>";
echo "<p><strong>Comptes disponibles :</strong></p>";
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
echo "<br><p>⚠️ <strong>Pensez à supprimer ce fichier init.php après usage !</strong></p>";
echo "<p><a href='index.php'>→ Retour à l'accueil</a> | <a href='connexion.php'>→ Se connecter</a></p>";
