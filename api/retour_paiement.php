<?php
/*
 * api/retour_paiement.php
 * ---------------------------------------------------------------
 * Fichier de retour de la plateforme CY Bank.
 */

// On restaure la session du client grâce au paramètre passé dans l'URL de retour
if (isset($_GET['session']) && session_status() === PHP_SESSION_NONE) {
    session_id($_GET['session']);
    session_start();
}

require('getapikey.php');

// --- 1. Récupération des paramètres de retour ---
// La consigne mentionne "status" dans l'URL mais "statut" dans le texte. 
// On gère les deux pour être certain que ça fonctionne.
$transaction   = $_GET['transaction'] ?? '';
$montant       = $_GET['montant']     ?? '';
$vendeur       = $_GET['vendeur']     ?? '';
$statut        = $_GET['status']      ?? $_GET['statut'] ?? '';
$control_recu  = $_GET['control']     ?? '';

// --- 2. Vérification que tous les paramètres sont là ---
if ($transaction === '' || $montant === '' || $vendeur === '' || $statut === '' || $control_recu === '') {
    die("Erreur : données de retour incomplètes de la part de CY Bank.");
}

// --- 3. Recalcul du hash pour vérifier l'intégrité ---
// Règle : md5( $api_key . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $statut . "#" );
$api_key          = getAPIKey($vendeur);
$control_attendu  = md5($api_key . '#' . $transaction . '#' . $montant . '#' . $vendeur . '#' . $statut . '#');

// --- 4. VÉRIFICATION DE SÉCURITÉ ---
if (!hash_equals($control_attendu, $control_recu)) {
    die("❌ Erreur de sécurité : données de retour falsifiées (hash invalide).");
}

// --- 5. Si le paiement est accepté, on valide la commande ---
if ($statut === 'accepted') {
    $data_php = __DIR__ . '/../includes/data.php';
    if (file_exists($data_php) && (!empty($_SESSION['panier']) || !empty($_SESSION['panier_menus']))) {
        require_once $data_php;
        
        $articles = [];
        foreach ($_SESSION['panier'] ?? [] as $l) {
            $articles[] = ['plat_id' => $l['plat_id'], 'quantite' => $l['quantite']];
        }
        foreach ($_SESSION['panier_menus'] ?? [] as $l) {
            $articles[] = ['menu_id' => $l['menu_id'], 'quantite' => $l['quantite']];
        }

        $user = !empty($_SESSION['user_id']) ? trouver_utilisateur_par_id($_SESSION['user_id']) : [];
        $checkout = $_SESSION['checkout'] ?? [];

        $type_livraison = $checkout['type_livraison'] ?? 'maintenant';
        $date_souhaitee = $checkout['date_souhaitee'] ?? '';

        if ($type_livraison === 'plus_tard' && $date_souhaitee !== '') {
            $statut_commande = 'en_attente';
        } else {
            $statut_commande = 'a_preparer';
            $date_souhaitee  = null;
        }

        $nouvelle_commande = [
            'client_id'         => $_SESSION['user_id'] ?? null,
            'livreur_id'        => null,
            'articles'          => $articles,
            'adresse_livraison' => $checkout['adresse_livraison'] ?? ($user['adresse'] ?? ''),
            'code_interphone'   => $checkout['code_interphone'] ?? ($user['code_interphone'] ?? ''),
            'etage'             => $checkout['etage'] ?? ($user['etage'] ?? ''),
            'telephone'         => $user['telephone'] ?? '',
            'commentaire'       => $checkout['commentaire'] ?? '',
            'statut'            => $statut_commande,
            'date_souhaitee'    => $date_souhaitee,
            'date'              => date('Y-m-d\TH:i:s'),
            'total'             => round((float)$montant, 2),
            'paiement_effectue' => true,
            'transaction_id'    => $transaction,
            'avis'              => null,
        ];

        ajouter_commande($nouvelle_commande);

        // Ajout des points de fidélité (1 point par euro)
        if (!empty($_SESSION['user_id'])) {
            mettre_a_jour_utilisateur($_SESSION['user_id'], [
                'points_fidelite' => ($user['points_fidelite'] ?? 0) + intval($montant),
            ]);
        }

        // Vider le panier et la session checkout
        $_SESSION['panier'] = [];
        $_SESSION['panier_menus'] = [];
        unset($_SESSION['checkout']);
    }
}

// --- 6. Affichage ---
$t = htmlspecialchars($transaction);
$m = htmlspecialchars($montant);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Retour paiement - L'Île au Fruit</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; padding: 50px; text-align: center; }
        .box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .success { color: #28a745; }
        .danger { color: #dc3545; }
        .btn { background: #0056b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 20px;}
        .btn:hover { background: #004494; }
    </style>
</head>
<body>

<div class="box">
    <?php if ($statut === 'accepted'): ?>
        <h1 class="success">✅ Paiement accepté !</h1>
        <p>Merci pour votre commande.</p>
        <p><strong>Référence :</strong> <?= $t ?></p>
        <p><strong>Montant payé :</strong> <?= $m ?> €</p>
    <?php else: ?>
        <h1 class="danger">❌ Paiement refusé</h1>
        <p>Votre transaction a été déclinée par la banque.</p>
        <p><strong>Référence :</strong> <?= $t ?></p>
        <p><strong>Montant :</strong> <?= $m ?> €</p>
        <a href="paiement.php" class="btn">Réessayer</a>
    <?php endif; ?>

    <br><br>
    <a href="../index.php" class="btn" style="background:#555;">Retour à la boutique</a>
</div>

</body>
</html>
