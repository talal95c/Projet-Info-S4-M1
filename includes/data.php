<?php
// includes/data.php - Version très simple

// Fonction pour LIRE un fichier JSON
function lire_json($nom_fichier) {
    $chemin = __DIR__ . '/../data/' . $nom_fichier;
    $texte = file_get_contents($chemin); // On lit le texte
    return json_decode($texte, true);    // On transforme en tableau PHP
}

// Fonction pour ÉCRIRE dans un fichier JSON
function ecrire_json($nom_fichier, $donnees) {
    $chemin = __DIR__ . '/../data/' . $nom_fichier;
    $texte = json_encode($donnees, JSON_PRETTY_PRINT); // On transforme le tableau en texte
    file_put_contents($chemin, $texte);                // On enregistre
}
