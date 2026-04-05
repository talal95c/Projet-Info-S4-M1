<?php
/*
 * includes/data.php
 * ---------------------------------------------------------------
 * Bibliothèque d'accès aux données JSON du projet.
 * Inclus dans toutes les pages qui lisent ou écrivent des données.
 * Tous les fichiers JSON sont stockés dans le dossier data/.
 *
 * Fonctions disponibles :
 *   lire_json($fichier)                      → lit un fichier JSON et retourne un tableau PHP
 *   ecrire_json($fichier, $data)             → encode un tableau PHP en JSON et l'écrit sur disque
 *   trouver_utilisateur_par_login($login)    → recherche un utilisateur par son email
 *   trouver_utilisateur_par_id($id)          → recherche un utilisateur par son identifiant
 *   ajouter_utilisateur($nouvel_user)        → ajoute un utilisateur (id auto = max + 1)
 *   mettre_a_jour_utilisateur($id, $champs)  → met à jour certains champs d'un utilisateur
 *   commandes_du_client($client_id)          → retourne toutes les commandes d'un client
 *   mettre_a_jour_commande($id, $champs)     → met à jour certains champs d'une commande
 *   ajouter_commande($nouvelle_commande)     → ajoute une commande (id auto >= 1001) et retourne son id
 *   trouver_plat_par_id($id)                 → recherche un plat par son identifiant dans plats.json
 *   noms_articles($articles)                 → convertit un tableau d'articles en texte lisible
 *                                              ex : "Smoothie x1, Jus x2"
 */

function lire_json($fichier) {
    $chemin = __DIR__ . '/../data/' . $fichier;
    $contenu = file_get_contents($chemin);
    return json_decode($contenu, true);
}

function ecrire_json($fichier, $data) {
    $chemin = __DIR__ . '/../data/' . $fichier;
    file_put_contents($chemin, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function trouver_utilisateur_par_login($login) {
    $utilisateurs = lire_json('utilisateurs.json');
    foreach ($utilisateurs as $u) {
        if ($u['login'] === $login) return $u;
    }
    return false;
}

function trouver_utilisateur_par_id($id) {
    $utilisateurs = lire_json('utilisateurs.json');
    foreach ($utilisateurs as $u) {
        if ($u['id'] == $id) return $u;
    }
    return false;
}

function ajouter_utilisateur($nouvel_user) {
    $utilisateurs = lire_json('utilisateurs.json');
    $max_id = 0;
    foreach ($utilisateurs as $u) {
        if ($u['id'] > $max_id) $max_id = $u['id'];
    }
    $nouvel_user['id'] = $max_id + 1;
    $utilisateurs[] = $nouvel_user;
    ecrire_json('utilisateurs.json', $utilisateurs);
}

function mettre_a_jour_utilisateur($id, $nouvelles_valeurs) {
    $utilisateurs = lire_json('utilisateurs.json');
    foreach ($utilisateurs as &$u) {
        if ($u['id'] == $id) {
            foreach ($nouvelles_valeurs as $cle => $val) {
                $u[$cle] = $val;
            }
            break;
        }
    }
    ecrire_json('utilisateurs.json', $utilisateurs);
}

function commandes_du_client($client_id) {
    $commandes = lire_json('commandes.json');
    $resultat = [];
    foreach ($commandes as $c) {
        if ($c['client_id'] == $client_id) $resultat[] = $c;
    }
    return $resultat;
}

function mettre_a_jour_commande($id, $nouvelles_valeurs) {
    $commandes = lire_json('commandes.json');
    foreach ($commandes as &$c) {
        if ($c['id'] == $id) {
            foreach ($nouvelles_valeurs as $cle => $val) {
                $c[$cle] = $val;
            }
            break;
        }
    }
    ecrire_json('commandes.json', $commandes);
}

function ajouter_commande($nouvelle_commande) {
    $commandes = lire_json('commandes.json');
    $max_id = 1000;
    foreach ($commandes as $c) {
        if ($c['id'] > $max_id) $max_id = $c['id'];
    }
    $nouvelle_commande['id'] = $max_id + 1;
    $commandes[] = $nouvelle_commande;
    ecrire_json('commandes.json', $commandes);
    return $nouvelle_commande['id'];
}

function trouver_plat_par_id($id) {
    $plats = lire_json('plats.json');
    foreach ($plats as $p) {
        if ($p['id'] == $id) return $p;
    }
    return false;
}

function noms_articles($articles) {
    $noms = [];
    foreach ($articles as $article) {
        $plat = trouver_plat_par_id($article['plat_id']);
        if ($plat) $noms[] = $plat['nom'] . ' x' . $article['quantite'];
    }
    return implode(', ', $noms);
}
