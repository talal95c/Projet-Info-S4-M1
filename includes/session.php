<?php
// includes/session.php - Version très simple

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // On démarre la session (obligatoire pour utiliser $_SESSION)
}

// Fonction pour vérifier si quelqu'un est connecté
function est_connecte() {
    return isset($_SESSION['user_id']);
}

// Fonction qui retourne le code HTML du menu en haut de la page
function nav_html() {
    // Si PAS connecté
    if (!est_connecte()) {
        return '<nav>
            <a href="index.php">Accueil</a>
            <a href="presentation.php">Menu</a>
            <a href="connexion.php">Connexion</a>
        </nav>';
    }

    // Si connecté, on récupère son rôle
    $role = $_SESSION['role'];
    $menu = '<nav><a href="index.php">Accueil</a> ';

    if ($role === 'client') {
        $menu .= '<a href="presentation.php">Menu</a> <a href="profil.php">Mon Profil</a>';
    } 
    elseif ($role === 'admin') {
        $menu .= '<a href="admin.php">Admin</a> <a href="commandes.php">Commandes</a>';
    }
    elseif ($role === 'restaurateur') {
        $menu .= '<a href="commandes.php">Gestion Commandes</a>';
    }
    elseif ($role === 'livreur') {
        $menu .= '<a href="livraison.php">Ma Livraison</a>';
    }

    $menu .= ' | <a href="deconnexion.php">Déconnexion</a></nav>';
    return $menu;
}
