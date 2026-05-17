<?php
require('getapikey.php');

$vendeur = 'MI-1_C';
$api_key = getAPIKey($vendeur);

echo "Vendeur : " . $vendeur . "<br>";
echo "API Key : " . $api_key . "<br>";

if(preg_match("/^[0-9a-zA-Z]{15}$/", $api_key)) {
    echo " Clé valide (15 caractères alphanumériques)";
} else {
    echo " Clé invalide — vérifier le code vendeur";
}
?> 