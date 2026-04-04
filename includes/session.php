<?php
// includes/session.php — Gestion des sessions PHP

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─────────────────────────────────────────────────
// FONCTIONS DE BASE
// ─────────────────────────────────────────────────

/**
 * Vérifie si un utilisateur est connecté.
 */
function est_connecte() {
    return isset($_SESSION['user_id']);
}

/**
 * Retourne le rôle de l'utilisateur connecté (ou '' si déconnecté).
 */
function get_role() {
    return $_SESSION['role'] ?? '';
}

/**
 * Crée la session après une connexion réussie.
 */
function creer_session($utilisateur) {
    $_SESSION['user_id']  = $utilisateur['id'];
    $_SESSION['role']     = $utilisateur['role'];
    $_SESSION['nom']      = $utilisateur['nom'];
    $_SESSION['prenom']   = $utilisateur['prenom'];
    $_SESSION['login']    = $utilisateur['login'];
}

/**
 * Détruit la session (déconnexion).
 */
function detruire_session() {
    $_SESSION = [];
    session_destroy();
}

/**
 * Vérifie que l'utilisateur est connecté et a le bon rôle.
 * Si pas connecté → redirige vers connexion.php
 * Si mauvais rôle → redirige vers index.php
 *
 * @param array $roles  Liste des rôles autorisés (vide = tous les rôles acceptés)
 */
function verifier_connexion($roles = []) {
    if (!est_connecte()) {
        header('Location: connexion.php');
        exit;
    }
    if (!empty($roles) && !in_array(get_role(), $roles)) {
        header('Location: index.php');
        exit;
    }
}

// ─────────────────────────────────────────────────
// GÉNÉRATION DE LA NAVIGATION
// ─────────────────────────────────────────────────

/**
 * Génère le HTML du menu de navigation selon l'état de connexion et le rôle.
 * 
 * @param string $page_active  Slug de la page active (ex: 'accueil', 'menu', 'profil')
 */
function nav_html($page_active = '') {
    $logo = '<div class="logo"><a href="index.php"><img src="image/lgoo.png" alt="L\'Île au Fruit"></a></div>';

    // Utilisateur NON connecté
    if (!est_connecte()) {
        return '
        <nav>
            ' . $logo . '
            <ul>
                <li><a href="presentation.php"' . ($page_active === 'menu' ? ' class="active"' : '') . '>Menu</a></li>
                <li><a href="avis.php"' . ($page_active === 'avis' ? ' class="active"' : '') . '>Avis</a></li>
            </ul>
            <a href="connexion.php" class="btn-connexion">Se connecter</a>
        </nav>';
    }

    // Utilisateur connecté
    $role   = get_role();
    $prenom = htmlspecialchars($_SESSION['prenom'] ?? '');
    $liens  = '';

    if ($role === 'client') {
        $liens = '
                <li><a href="presentation.php"' . ($page_active === 'menu' ? ' class="active"' : '') . '>Menu</a></li>
                <li><a href="panier.php"' . ($page_active === 'panier' ? ' class="active"' : '') . '>🛒 Panier</a></li>
                <li><a href="profil.php"' . ($page_active === 'profil' ? ' class="active"' : '') . '>Mon Profil</a></li>
                <li><a href="avis.php"' . ($page_active === 'avis' ? ' class="active"' : '') . '>Avis</a></li>';
    } elseif ($role === 'admin') {
        $liens = '
                <li><a href="admin.php"' . ($page_active === 'admin' ? ' class="active"' : '') . '>Admin</a></li>
                <li><a href="commandes.php"' . ($page_active === 'commandes' ? ' class="active"' : '') . '>Commandes</a></li>';
    } elseif ($role === 'restaurateur') {
        $liens = '
                <li><a href="commandes.php"' . ($page_active === 'commandes' ? ' class="active"' : '') . '>Gestion Commandes</a></li>';
    } elseif ($role === 'livreur') {
        $liens = '
                <li><a href="livraison.php"' . ($page_active === 'livraison' ? ' class="active"' : '') . '>Ma Livraison</a></li>';
    }

    return '
        <nav>
            ' . $logo . '
            <ul>
                ' . $liens . '
            </ul>
            <div class="nav-user">
                <span>Bonjour, ' . $prenom . '</span>
                <a href="deconnexion.php" class="btn-connexion">Déconnexion</a>
            </div>
        </nav>';
}
