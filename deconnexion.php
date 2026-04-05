<?php
/*
 * deconnexion.php
 * ---------------------------------------------------------------
 * Déconnecte l'utilisateur du site L'Île au Fruit.
 *
 * Appelé via le lien "Déconnexion" généré dans nav_html().
 * Détruit la session PHP via detruire_session(), puis redirige
 * vers connexion.php. Ne contient aucune interface graphique.
 *
 * Dépendances : includes/session.php
 */

require_once 'includes/session.php';

detruire_session();
header('Location: connexion.php');
exit;
