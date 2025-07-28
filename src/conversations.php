<?php
require_once 'utils.php';

function displayConversations() {
    $xml = loadXML();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Conversations - Plateforme Messagerie</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 1000px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .form-section {
                margin: 20px 0;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
                background: #f9f9f9;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-sizing: border-box;
            }
            button {
                background: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin-right: 10px;
            }
            button:hover {
                background: #0056b3;
            }
            .back-btn {
                background: #6c757d;
            }
            .back-btn:hover {
                background: #545b62;
            }
            .conversation {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                max-height: 500px;
                overflow-y: auto;
            }
            .message {
                margin: 15px 0;
                padding: 12px;
                border-radius: 8px;
                position: relative;
            }
            .message.sent {
                background: #007bff;
                color: white;
                margin-left: 20%;
                text-align: right;
            }
            .message.received {
                background: #e9ecef;
                color: #333;
                margin-right: 20%;
            }
            .message-time {
                font-size: 0.8em;
                opacity: 0.8;
                margin-top: 5px;
            }
            .conversation-header {
                text-align: center;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                margin-bottom: 20px;
                font-weight: bold;
                color: #495057;
            }
            .no-messages {
                text-align: center;
                color: #6c757d;
                font-style: italic;
                padding: 40px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>💬 Conversations</h1>
            
            <!-- Sélection de conversation -->
            <div class="form-section">
                <h2>🔍 Voir une Conversation</h2>
                <form method="post">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; align-items: end;">
                        <div class="form-group">
                            <label>Premier utilisateur :</label>
                            <select name="user1" required>
                                <option value="">Choisir un utilisateur</option>
                                <?php foreach ($xml->utilisateurs->utilisateur as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= ($_POST['user1'] ?? '') === (string)$user['id'] ? 'selected' : '' ?>>
                                        <?= $user->nom ?> (<?= $user['id'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Deuxième utilisateur :</label>
                            <select name="user2" required>
                                <option value="">Choisir un utilisateur</option>
                                <?php foreach ($xml->utilisateurs->utilisateur as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= ($_POST['user2'] ?? '') === (string)$user['id'] ? 'selected' : '' ?>>
                                        <?= $user->nom ?> (<?= $user['id'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit">Afficher la conversation</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php
            // Affichage de la conversation si les utilisateurs sont sélectionnés
            if (isset($_POST['user1']) && isset($_POST['user2']) && $_POST['user1'] && $_POST['user2']) {
                $user1Id = $_POST['user1'];
                $user2Id = $_POST['user2'];
                
                $user1 = getUserById($xml, $user1Id);
                $user2 = getUserById($xml, $user2Id);
                
                if ($user1 && $user2) {
                    $conversation = getConversation($xml, $user1Id, $user2Id);
                    ?>
                    <div class="form-section">
                        <div class="conversation-header">
                            Conversation entre <strong><?= $user1->nom ?></strong> et <strong><?= $user2->nom ?></strong>
                        </div>
                        
                        <div class="conversation">
                            <?php if (count($conversation) > 0): ?>
                                <?php foreach ($conversation as $msg): ?>
                                    <?php
                                    $isSentByUser1 = (string)$msg->expediteur === $user1Id;
                                    $senderName = $isSentByUser1 ? $user1->nom : $user2->nom;
                                    ?>
                                    <div class="message <?= $isSentByUser1 ? 'sent' : 'received' ?>">
                                        <div><?= nl2br(htmlspecialchars($msg->contenu)) ?></div>
                                        <div class="message-time">
                                            <?= formatDate($msg->date) ?> - <?= $senderName ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-messages">
                                    Aucun message échangé entre ces utilisateurs.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
            
            <!-- Section statistiques -->
            <div class="form-section">
                <h2>📊 Statistiques</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                        <h3 style="color: #007bff; margin: 0;"><?= count($xml->utilisateurs->utilisateur) ?></h3>
                        <p style="margin: 5px 0;">Utilisateurs</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                        <h3 style="color: #28a745; margin: 0;"><?= count($xml->messages->message) ?></h3>
                        <p style="margin: 5px 0;">Messages</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                        <h3 style="color: #ffc107; margin: 0;"><?= count($xml->groupes->groupe) ?></h3>
                        <p style="margin: 5px 0;">Groupes</p>
                    </div>
                </div>
            </div>
            
            <!-- Liste des derniers messages -->
            <div class="form-section">
                <h2>📨 Derniers Messages</h2>
                <?php
                $allMessages = [];
                foreach ($xml->messages->message as $msg) {
                    $allMessages[] = $msg;
                }
                
                // Trier par date décroissante
                usort($allMessages, function($a, $b) {
                    return strtotime($b->date) - strtotime($a->date);
                });
                
                $recentMessages = array_slice($allMessages, 0, 5);
                ?>
                
                <?php if (count($recentMessages) > 0): ?>
                    <?php foreach ($recentMessages as $msg): ?>
                        <?php
                        $expediteur = getUserById($xml, (string)$msg->expediteur);
                        $destinataire = getUserById($xml, (string)$msg->destinataire);
                        ?>
                        <div style="background: white; margin: 10px 0; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;">
                            <strong><?= $expediteur ? $expediteur->nom : $msg->expediteur ?></strong> 
                            → 
                            <strong><?= $destinataire ? $destinataire->nom : $msg->destinataire ?></strong>
                            <br>
                            <em><?= formatDate($msg->date) ?></em>
                            <br>
                            <?= nl2br(htmlspecialchars(substr($msg->contenu, 0, 100))) ?><?= strlen($msg->contenu) > 100 ? '...' : '' ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucun message pour le moment.</p>
                <?php endif; ?>
            </div>
            
            <button onclick="window.location.href='../index.php'" class="back-btn">
                ← Retour à l'accueil
            </button>
        </div>
    </body>
    </html>
    <?php
}

// Afficher la page si appelée directement
if (basename($_SERVER['PHP_SELF']) === 'conversations.php') {
    displayConversations();
}
?>
