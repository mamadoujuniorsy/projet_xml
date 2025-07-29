<?php
// Script pour nettoyer les avatars "default.png" dans le fichier XML existant
require_once 'src/utils.php';

echo "Nettoyage des avatars 'default.png' en cours...\n";

$xml = loadXML();

// Parcourir tous les utilisateurs et remplacer "default.png" par une chaîne vide
foreach ($xml->utilisateurs->utilisateur as $user) {
    if ((string)$user->avatar === 'default.png') {
        $user->avatar = '';
        echo "Avatar nettoyé pour l'utilisateur: " . $user->nom . "\n";
    }
}

// Sauvegarder les modifications
saveXML($xml);
echo "Nettoyage terminé avec succès !\n";
?>
