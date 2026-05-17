<?php
// Renvoie les plats filtrés en JSON pour le filtrage AJAX de la page produits.

require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');

$plats = lire_json('plats.json');
$plats = array_filter($plats, fn($p) => $p['disponible']);

$recherche = trim($_GET['recherche'] ?? '');
$categorie = trim($_GET['categorie'] ?? '');
$regime    = trim($_GET['regime']    ?? '');
$prix      = trim($_GET['prix']      ?? '');

if ($categorie !== '') {
    $plats = array_filter($plats, fn($p) => $p['categorie'] === $categorie);
}
if ($regime !== '') {
    $plats = array_filter($plats, fn($p) => in_array($regime, $p['tags']));
}
if ($prix !== '') {
    if ($prix === '0-5')        $plats = array_filter($plats, fn($p) => $p['prix'] < 5);
    elseif ($prix === '5-10')   $plats = array_filter($plats, fn($p) => $p['prix'] >= 5 && $p['prix'] <= 10);
    elseif ($prix === '10-15')  $plats = array_filter($plats, fn($p) => $p['prix'] > 10 && $p['prix'] <= 15);
    elseif ($prix === '15+')    $plats = array_filter($plats, fn($p) => $p['prix'] > 15);
}
if ($recherche !== '') {
    $plats = array_filter($plats, fn($p) =>
        stripos($p['nom'], $recherche) !== false ||
        stripos($p['description'], $recherche) !== false
    );
}

$plats = array_values($plats);

echo json_encode([
    'succes' => true,
    'total'  => count($plats),
    'plats'  => $plats,
], JSON_UNESCAPED_UNICODE);
