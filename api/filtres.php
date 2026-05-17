<?php
require_once '../includes/session.php';
require_once '../includes/data.php';

header('Content-Type: application/json');

$plats = lire_json('plats.json');
$plats = array_filter($plats, fn($p) => $p['disponible']);

$categorie = trim($_GET['categorie'] ?? '');
$regime    = trim($_GET['regime'] ?? '');
$prix      = trim($_GET['prix'] ?? '');
$recherche = trim($_GET['recherche'] ?? '');
$tri       = trim($_GET['tri'] ?? '');

if ($categorie !== '') {
    $plats = array_filter($plats, fn($p) => $p['categorie'] === $categorie);
}
if ($regime !== '') {
    $plats = array_filter($plats, fn($p) => in_array($regime, $p['tags']));
}
if ($prix !== '') {
    if ($prix === '0-5')  $plats = array_filter($plats, fn($p) => $p['prix'] < 5);
    elseif ($prix === '5-10')  $plats = array_filter($plats, fn($p) => $p['prix'] >= 5 && $p['prix'] <= 10);
    elseif ($prix === '10-15') $plats = array_filter($plats, fn($p) => $p['prix'] > 10 && $p['prix'] <= 15);
    elseif ($prix === '15+')   $plats = array_filter($plats, fn($p) => $p['prix'] > 15);
}
if ($recherche !== '') {
    $plats = array_filter($plats, fn($p) =>
        stripos($p['nom'], $recherche) !== false ||
        stripos($p['description'], $recherche) !== false
    );
}

// Tris
if ($tri === 'prix_asc') {
    usort($plats, fn($a, $b) => $a['prix'] <=> $b['prix']);
} elseif ($tri === 'prix_desc') {
    usort($plats, fn($a, $b) => $b['prix'] <=> $a['prix']);
} elseif ($tri === 'nom_asc') {
    usort($plats, fn($a, $b) => strcasecmp($a['nom'], $b['nom']));
}

echo json_encode(array_values($plats));
