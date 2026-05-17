<?php
/*
 * includes/session.php
 * ---------------------------------------------------------------
 * Bibliothèque de gestion des sessions et de la navigation.
 * Inclus en premier dans toutes les pages du site.
 *
 * Fonctions disponibles :
 *   est_connecte()              → retourne true si un utilisateur est connecté
 *                                  ET que son compte est encore actif
 *   get_role()                  → retourne le rôle de l'utilisateur connecté (string)
 *   creer_session($utilisateur) → initialise la session après une connexion réussie
 *   detruire_session()          → vide et détruit la session (déconnexion)
 *   verifier_connexion($roles)  → redirige si l'utilisateur n'est pas connecté ou
 *                                  n'a pas l'un des rôles autorisés
 *   nav_html($page_active)      → génère le HTML de la barre de navigation selon
 *                                  le rôle : visiteur, client, admin, restaurateur,
 *                                  livreur. Le paramètre $page_active permet de
 *                                  marquer le lien courant comme actif.
 *
 * Phase 3 — Déconnexion immédiate d'un utilisateur bloqué :
 *   À chaque chargement de page, on vérifie que le compte de
 *   l'utilisateur connecté est toujours actif. S'il a été bloqué
 *   par un admin depuis la dernière requête, sa session est
 *   immédiatement détruite (cf. verifier_session_active).
 */

require_once __DIR__ . '/data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie que l'utilisateur connecté n'a pas été bloqué par un
 * admin entre temps. Si c'est le cas, la session est détruite
 * sur-le-champ (cf. consigne phase 3).
 *
 * Appelée automatiquement à l'inclusion de session.php pour
 * couvrir TOUTES les pages du site. Aucun effet pour les visiteurs
 * non connectés.
 */
function verifier_session_active() {
    if (!isset($_SESSION['user_id'])) return;

    $user = trouver_utilisateur_par_id($_SESSION['user_id']);
    if (!$user || empty($user['actif'])) {
        // Compte introuvable OU bloqué → on coupe immédiatement
        detruire_session();
    }
}

// Vérification automatique pour chaque page incluant session.php
verifier_session_active();

function est_connecte() {
    return isset($_SESSION['user_id']);
}

function get_role() {
    return $_SESSION['role'] ?? '';
}

function creer_session($utilisateur) {
    $_SESSION['user_id'] = $utilisateur['id'];
    $_SESSION['role']    = $utilisateur['role'];
    $_SESSION['nom']     = $utilisateur['nom'];
    $_SESSION['prenom']  = $utilisateur['prenom'];
    $_SESSION['login']   = $utilisateur['login'];
}

function detruire_session() {
    $_SESSION = [];
    session_destroy();
}

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

function nav_html($page_active = '') {
    $logo = '<div class="logo">
                <a href="index.php">
                    <img src="image/lgoo.png" alt="Retour à l\'accueil">
                </a>
             </div>';

    // Bouton de bascule de thème (phase 3) — visible sur toutes les pages.
    // L'icône et le titre sont mis à jour côté JS (js/theme.js) selon le mode actif.
    $btn_theme = '<button type="button" id="btn-theme" class="btn-theme"
                          aria-label="Basculer le thème clair/sombre"
                          title="Basculer le thème">🌙</button>';

    if (!est_connecte()) {
        return '
        <nav>
            ' . $logo . '
            <ul>
                <li><a href="presentation.php">Menu</a></li>
                <li><a href="panier.php">🛒 Panier</a></li>
                <li><a href="profil.php">Mon Profil</a></li>
                <li><a href="avis.php">Avis</a></li>
            </ul>
            <div class="nav-actions">
                ' . $btn_theme . '
                <a href="connexion.php" class="btn-connexion">Se connecter</a>
            </div>
        </nav>';
    }

    $role   = get_role();
    $prenom = htmlspecialchars($_SESSION['prenom']);
    $liens  = '';

    if ($role === 'client') {
        $liens = '
            <li><a href="presentation.php">Menu</a></li>
            <li><a href="panier.php">🛒 Panier</a></li>
            <li><a href="profil.php">Mon Profil</a></li>
            <li><a href="avis.php">Avis</a></li>';
    } elseif ($role === 'admin') {
        $liens = '
            <li><a href="admin.php">Admin</a></li>
            <li><a href="commandes.php">Commandes</a></li>';
    } elseif ($role === 'restaurateur') {
        $liens = '<li><a href="commandes.php">Gestion Commandes</a></li>';
    } elseif ($role === 'livreur') {
        $liens = '<li><a href="livraison.php">Ma Livraison</a></li>';
    }

    return '
        <nav>
            ' . $logo . '
            <ul>' . $liens . '</ul>
            <div class="nav-actions">
                ' . $btn_theme . '
                <span>Bonjour, ' . $prenom . ' | </span>
                <a href="deconnexion.php" class="btn-connexion">Déconnexion</a>
            </div>
        </nav>';
}
