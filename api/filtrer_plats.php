<?php
require_once '../includes/data.php';

header('Content-Type: application/json');

$tous_plats = lire_json('plats.json');
$plats = [];
foreach ($tous_plats as $p) {
    if ($p['disponible']) {
        $plats[] = $p;
    }
}

$categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
$regime    = isset($_GET['regime'])    ? trim($_GET['regime'])    : '';
$prix      = isset($_GET['prix'])      ? trim($_GET['prix'])      : '';
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

if ($categorie !== '') {
    $nouveaux_plats = [];
    foreach ($plats as $p) {
        if ($p['categorie'] === $categorie) $nouveaux_plats[] = $p;
    }
    $plats = $nouveaux_plats;
}
if ($regime !== '') {
    $nouveaux_plats = [];
    foreach ($plats as $p) {
        if (in_array($regime, $p['tags'])) $nouveaux_plats[] = $p;
    }
    $plats = $nouveaux_plats;
}
if ($prix !== '') {
    $nouveaux_plats = [];
    foreach ($plats as $p) {
        if ($prix === '0-5' && $p['prix'] < 5) $nouveaux_plats[] = $p;
        elseif ($prix === '5-10' && $p['prix'] >= 5 && $p['prix'] <= 10) $nouveaux_plats[] = $p;
        elseif ($prix === '10-15' && $p['prix'] > 10 && $p['prix'] <= 15) $nouveaux_plats[] = $p;
        elseif ($prix === '15+' && $p['prix'] > 15) $nouveaux_plats[] = $p;
    }
    $plats = $nouveaux_plats;
}
if ($recherche !== '') {
    $nouveaux_plats = [];
    foreach ($plats as $p) {
        if (stripos($p['nom'], $recherche) !== false || stripos($p['description'], $recherche) !== false) {
            $nouveaux_plats[] = $p;
        }
    }
    $plats = $nouveaux_plats;
}

// Convert indices to 0-based array values so json_encode doesn't make an object
$plats = array_values($plats);

echo json_encode([
    'succes' => true,
    'plats' => $plats
]);
