<?php
// includes/data.php — Fonctions utilitaires pour lire/écrire les fichiers JSON

// ─────────────────────────────────────────────────
// FONCTIONS DE BASE : lire et écrire un fichier JSON
// ─────────────────────────────────────────────────

/**
 * Lit un fichier JSON dans data/ et retourne un tableau PHP.
 */
function lire_json($nom_fichier) {
    $chemin = __DIR__ . '/../data/' . $nom_fichier;
    if (!file_exists($chemin)) return [];
    $texte = file_get_contents($chemin);
    return json_decode($texte, true) ?? [];
}

/**
 * Écrit un tableau PHP dans un fichier JSON dans data/.
 */
function ecrire_json($nom_fichier, $donnees) {
    $chemin = __DIR__ . '/../data/' . $nom_fichier;
    $texte = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($chemin, $texte);
}

// ─────────────────────────────────────────────────
// FONCTIONS UTILISATEURS
// ─────────────────────────────────────────────────

/**
 * Cherche un utilisateur par son login (email).
 * Retourne le tableau de l'utilisateur ou false si introuvable.
 */
function trouver_utilisateur_par_login($login) {
    $utilisateurs = lire_json('utilisateurs.json');
    foreach ($utilisateurs as $u) {
        if ($u['login'] === $login) {
            return $u;
        }
    }
    return false;
}

/**
 * Cherche un utilisateur par son id.
 * Retourne le tableau de l'utilisateur ou false si introuvable.
 */
function trouver_utilisateur_par_id($id) {
    $utilisateurs = lire_json('utilisateurs.json');
    foreach ($utilisateurs as $u) {
        if ($u['id'] == $id) {
            return $u;
        }
    }
    return false;
}

/**
 * Ajoute un nouvel utilisateur dans utilisateurs.json.
 * Génère automatiquement un id unique.
 */
function ajouter_utilisateur($nouvel_utilisateur) {
    $utilisateurs = lire_json('utilisateurs.json');
    // Trouver le plus grand id existant et ajouter 1
    $max_id = 0;
    foreach ($utilisateurs as $u) {
        if ($u['id'] > $max_id) $max_id = $u['id'];
    }
    $nouvel_utilisateur['id'] = $max_id + 1;
    $utilisateurs[] = $nouvel_utilisateur;
    ecrire_json('utilisateurs.json', $utilisateurs);
    return $nouvel_utilisateur['id'];
}

/**
 * Met à jour un utilisateur existant (par id).
 */
function mettre_a_jour_utilisateur($id, $nouvelles_donnees) {
    $utilisateurs = lire_json('utilisateurs.json');
    foreach ($utilisateurs as &$u) {
        if ($u['id'] == $id) {
            foreach ($nouvelles_donnees as $cle => $valeur) {
                $u[$cle] = $valeur;
            }
            break;
        }
    }
    ecrire_json('utilisateurs.json', $utilisateurs);
}

// ─────────────────────────────────────────────────
// FONCTIONS COMMANDES
// ─────────────────────────────────────────────────

/**
 * Retourne toutes les commandes d'un client donné (par client_id).
 */
function commandes_du_client($client_id) {
    $commandes = lire_json('commandes.json');
    $resultat = [];
    foreach ($commandes as $c) {
        if ($c['client_id'] == $client_id) {
            $resultat[] = $c;
        }
    }
    return $resultat;
}

/**
 * Retourne toutes les commandes ayant un statut donné.
 */
function commandes_par_statut($statut) {
    $commandes = lire_json('commandes.json');
    $resultat = [];
    foreach ($commandes as $c) {
        if ($c['statut'] === $statut) {
            $resultat[] = $c;
        }
    }
    return $resultat;
}

/**
 * Cherche une commande par son id.
 */
function trouver_commande_par_id($id) {
    $commandes = lire_json('commandes.json');
    foreach ($commandes as $c) {
        if ($c['id'] == $id) {
            return $c;
        }
    }
    return false;
}

/**
 * Met à jour le statut d'une commande (par id).
 */
function mettre_a_jour_commande($id, $nouvelles_donnees) {
    $commandes = lire_json('commandes.json');
    foreach ($commandes as &$c) {
        if ($c['id'] == $id) {
            foreach ($nouvelles_donnees as $cle => $valeur) {
                $c[$cle] = $valeur;
            }
            break;
        }
    }
    ecrire_json('commandes.json', $commandes);
}

/**
 * Ajoute une nouvelle commande dans commandes.json.
 */
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

// ─────────────────────────────────────────────────
// FONCTIONS PLATS
// ─────────────────────────────────────────────────

/**
 * Retourne tous les plats disponibles.
 */
function lire_tous_plats() {
    return lire_json('plats.json');
}

/**
 * Cherche un plat par son id.
 */
function trouver_plat_par_id($id) {
    $plats = lire_json('plats.json');
    foreach ($plats as $p) {
        if ($p['id'] == $id) {
            return $p;
        }
    }
    return false;
}

/**
 * À partir d'une liste d'articles [{plat_id, quantite}],
 * retourne les noms des plats formatés pour l'affichage.
 */
function noms_articles($articles) {
    $noms = [];
    foreach ($articles as $article) {
        $plat = trouver_plat_par_id($article['plat_id']);
        if ($plat) {
            $noms[] = $plat['nom'] . ' x' . $article['quantite'];
        }
    }
    return implode(', ', $noms);
}
