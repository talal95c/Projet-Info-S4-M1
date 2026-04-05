<?php
/*
 * inscription.php
 * ---------------------------------------------------------------
 * Page d'inscription du site L'Île au Fruit.
 *
 * Traite le formulaire POST de création de compte client.
 * Vérifie que tous les champs obligatoires sont remplis, que les
 * deux mots de passe correspondent, que le mot de passe fait au
 * moins 6 caractères et que l'email n'est pas déjà utilisé.
 * En cas de succès, crée le compte via ajouter_utilisateur() avec
 * le rôle 'client', le statut 'bronze' et 0 points de fidélité,
 * puis redirige vers connexion.php.
 *
 * Dépendances : includes/session.php, includes/data.php
 */

require_once 'includes/session.php';
require_once 'includes/data.php';

if (est_connecte()) {
    header('Location: index.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nom']);
    $prenom     = trim($_POST['prenom']);
    $login      = trim($_POST['login']);
    $telephone  = trim($_POST['telephone']);
    $adresse    = trim($_POST['adresse']);
    $interphone = trim($_POST['code_interphone']);
    $etage      = trim($_POST['etage']);
    $mdp        = $_POST['mot_de_passe'];
    $mdp2       = $_POST['mot_de_passe2'];

    if (empty($nom) || empty($prenom) || empty($login) || empty($telephone) || empty($adresse) || empty($mdp)) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } elseif ($mdp !== $mdp2) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($mdp) < 6) {
        $erreur = 'Le mot de passe doit faire au moins 6 caractères.';
    } elseif (trouver_utilisateur_par_login($login)) {
        $erreur = 'Cet email est déjà utilisé.';
    } else {
        $nouvel_user = [
            'login'           => $login,
            'mot_de_passe'    => password_hash($mdp, PASSWORD_DEFAULT),
            'role'            => 'client',
            'nom'             => $nom,
            'prenom'          => $prenom,
            'telephone'       => $telephone,
            'adresse'         => $adresse,
            'code_interphone' => $interphone,
            'etage'           => $etage,
            'points_fidelite' => 0,
            'statut'          => 'bronze',
            'date_inscription' => date('Y-m-d'),
            'actif'           => true
        ];

        ajouter_utilisateur($nouvel_user);
        header('Location: connexion.php?succes=inscription');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Île au Fruit</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="login-container">
        <header class="logo-section">
            <a href="index.php">
                <img src="image/lgoo.png" alt="Logo L'Île au Fruit" class="logo">
            </a>
        </header>

        <section class="auth-card">
            <nav class="auth-tabs">
                <a href="connexion.php"><button class="tab-btn">Se connecter</button></a>
                <button class="tab-btn active">S'inscrire</button>
            </nav>

            <?php if ($erreur): ?>
                <div class="message erreur">❌ <?= $erreur ?></div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="inscription.php">
                <h2>Créer un compte</h2>

                <div class="input-group">
                    <input type="text" name="nom" placeholder="Nom *" required>
                </div>
                <div class="input-group">
                    <input type="text" name="prenom" placeholder="Prénom *" required>
                </div>
                <div class="input-group">
                    <input type="email" name="login" placeholder="Adresse e-mail *" required>
                </div>
                <div class="input-group">
                    <input type="tel" name="telephone" placeholder="Numéro de téléphone *" required>
                </div>
                <div class="input-group">
                    <input type="text" name="adresse" placeholder="Adresse de livraison *" required>
                </div>
                <div class="input-group">
                    <input type="text" name="code_interphone" placeholder="Code interphone (ex: A1234)">
                </div>
                <div class="input-group">
                    <input type="text" name="etage" placeholder="Étage / Informations complémentaires">
                </div>
                <div class="input-group">
                    <input type="password" name="mot_de_passe" placeholder="Mot de passe * (min. 6 caractères)" required>
                </div>
                <div class="input-group">
                    <input type="password" name="mot_de_passe2" placeholder="Confirmer le mot de passe *" required>
                </div>

                <button type="submit" class="submit-btn">S'inscrire</button>
                <a href="connexion.php" class="switch-link">Vous avez déjà un compte ? Se connecter ici</a>
            </form>
        </section>
    </main>
</body>
</html>
