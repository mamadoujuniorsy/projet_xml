<?php
require 'utils.php';

$xml = loadXML();

foreach ($xml->messages->message as $msg) {
    echo "[{$msg['id']}] {$msg->expediteur} -> {$msg->destinataire} : {$msg->contenu} ({$msg->date})\n";
}
?>