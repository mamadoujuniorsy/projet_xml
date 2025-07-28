<?php
require 'utils.php';

$xml = loadXML();

$id = 'u' . rand(1000, 9999);
$nom = 'Utilisateur ' . rand(1, 100);
$email = strtolower(str_replace(' ', '', $nom)) . '@exemple.com';

$newUser = $xml->utilisateurs->addChild('utilisateur');
$newUser->addAttribute('id', $id);
$newUser->addChild('nom', $nom);
$newUser->addChild('email', $email);

saveXML($xml);

echo "Utilisateur ajouté avec succès : $nom ($id)\n";
?>