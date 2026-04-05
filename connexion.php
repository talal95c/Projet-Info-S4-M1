<?php
/*
 * connexion.php
 * ---------------------------------------------------------------
 * Page de connexion du site L'Île au Fruit.
 *
 * Traite le formulaire POST (email + mot de passe).
 * Vérifie que l'email existe dans utilisateurs.json, que le compte
 * est actif et que le mot de passe correspond au hash bcrypt stocké.
 * En cas de succès, crée la session PHP et redirige l'utilisateur
 * vers la page correspondant à son rôle :
 *   - admin       → admin.php
 *   - restaurateur → commandes.php
 *   - livreur     → livraison.php
 *   - client      → profil.php
 * Affiche un message de succès si l'utilisateur vient de s'inscrire.
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
$succes = $_GET['succes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $mdp   = $_POST['mot_de_passe'];

    $utilisateur = trouver_utilisateur_par_login($login);

    if (!$utilisateur) {
        $erreur = 'Adresse e-mail introuvable.';
    } elseif (!$utilisateur['actif']) {
        $erreur = 'Votre compte est désactivé.';
    } elseif (!password_verify($mdp, $utilisateur['mot_de_passe'])) {
        $erreur = 'Mot de passe incorrect.';
    } else {
        creer_session($utilisateur);

        $role = $utilisateur['role'];
        if ($role === 'admin')             header('Location: admin.php');
        elseif ($role === 'restaurateur')  header('Location: commandes.php');
        elseif ($role === 'livreur')       header('Location: livraison.php');
        else                               header('Location: profil.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Île au Fruit</title>
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
                <button class="tab-btn active">Se connecter</button>
                <a href="inscription.php"><button class="tab-btn">S'inscrire</button></a>
            </nav>

            <?php if ($erreur): ?>
                <div class="message erreur">❌ <?= $erreur ?></div>
            <?php endif; ?>

            <?php if ($succes === 'inscription'): ?>
                <div class="message succes">✅ Compte créé ! Vous pouvez vous connecter.</div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="connexion.php">
                <h2>Bienvenue</h2>

                <div class="input-group">
                    <input type="email" name="login" placeholder="Adresse e-mail" required>
                </div>

                <div class="input-group">
                    <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
                </div>

                <button type="submit" class="submit-btn">Se connecter</button>
                <a href="inscription.php" class="switch-link">Vous n'avez pas de compte ? S'inscrire ici</a>
            </form>
        </section>
    </main>
</body>
</html>
