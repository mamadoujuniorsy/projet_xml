<?php
require 'utils.php';

$xml = loadXML();

$id = 'm' . rand(1000, 9999);
$message = $xml->messages->addChild('message');
$message->addAttribute('id', $id);
$message->addChild('expediteur', 'u1');
$message->addChild('destinataire', 'u2');
$message->addChild('contenu', 'Message XML automatique');
$message->addChild('date', date('c'));

saveXML($xml);

echo "Message envoyé. ID: $id\n";
?>