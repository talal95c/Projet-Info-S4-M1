<?php
// deconnexion.php — Destruction de la session et redirection

require_once 'includes/session.php';

detruire_session();

header('Location: connexion.php');
exit;
