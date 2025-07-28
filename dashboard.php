<?php
session_start();
require_once 'src/utils.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$xml = loadXML();
$currentUser = getUserById($xml, $_SESSION['user_id']);

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Traitement des actions
if ($_POST) {
    switch ($_POST['action'] ?? '') {
        case 'add_contact':
            $telephone = trim($_POST['telephone'] ?? '');
            if ($telephone) {
                $contactUser = null;
                foreach ($xml->utilisateurs->utilisateur as $user) {
                    if ((string)$user->telephone === $telephone && (string)$user['id'] !== $_SESSION['user_id']) {
                        $contactUser = $user;
                        break;
                    }
                }
                
                if ($contactUser) {
                    // Vérifier si le contact existe déjà
                    $contactExists = false;
                    if ($currentUser->contacts) {
                        foreach ($currentUser->contacts->contact as $contact) {
                            if ((string)$contact['id'] === (string)$contactUser['id']) {
                                $contactExists = true;
                                break;
                            }
                        }
                    } else {
                        $currentUser->addChild('contacts');
                    }
                    
                    if (!$contactExists) {
                        $newContact = $currentUser->contacts->addChild('contact');
                        $newContact->addAttribute('id', (string)$contactUser['id']);
                        $newContact->addAttribute('date_ajout', date('c'));
                        saveXML($xml);
                        $success = "Contact ajouté : " . $contactUser->nom;
                    } else {
                        $error = "Ce contact est déjà dans votre liste !";
                    }
                } else {
                    $error = "Aucun utilisateur trouvé avec ce numéro !";
                }
            }
            break;
            
        case 'send_message':
            $destinataire = $_POST['destinataire'] ?? '';
            $contenu = trim($_POST['contenu'] ?? '');
            
            if ($destinataire && $contenu) {
                $messageId = 'm' . time();
                $message = $xml->messages->addChild('message');
                $message->addAttribute('id', $messageId);
                $message->addChild('expediteur', $_SESSION['user_id']);
                $message->addChild('destinataire', $destinataire);
                $message->addChild('contenu', htmlspecialchars($contenu));
                $message->addChild('date', date('c'));
                $message->addChild('lu', 'false');
                saveXML($xml);
            }
            break;
    }
}

// Récupérer les contacts de l'utilisateur
$contacts = [];
if ($currentUser->contacts) {
    foreach ($currentUser->contacts->contact as $contact) {
        $contactUser = getUserById($xml, (string)$contact['id']);
        if ($contactUser) {
            $contacts[] = [
                'user' => $contactUser,
                'last_message' => getLastMessageBetween($xml, $_SESSION['user_id'], (string)$contact['id']),
                'unread_count' => getUnreadMessagesCount($xml, $_SESSION['user_id'], (string)$contact['id'])
            ];
        }
    }
}

// Fonction pour récupérer le dernier message entre deux utilisateurs
function getLastMessageBetween($xml, $user1, $user2) {
    $lastMessage = null;
    $lastTime = 0;
    
    foreach ($xml->messages->message as $msg) {
        $expediteur = (string)$msg->expediteur;
        $destinataire = (string)$msg->destinataire;
        $timestamp = strtotime($msg->date);
        
        if (($expediteur === $user1 && $destinataire === $user2) || 
            ($expediteur === $user2 && $destinataire === $user1)) {
            if ($timestamp > $lastTime) {
                $lastMessage = $msg;
                $lastTime = $timestamp;
            }
        }
    }
    
    return $lastMessage;
}

// Fonction pour compter les messages non lus
function getUnreadMessagesCount($xml, $currentUserId, $contactId) {
    $count = 0;
    foreach ($xml->messages->message as $msg) {
        if ((string)$msg->expediteur === $contactId && 
            (string)$msg->destinataire === $currentUserId && 
            (string)$msg->lu === 'false') {
            $count++;
        }
    }
    return $count;
}

$selectedContact = null;
$conversation = [];
if (isset($_GET['contact'])) {
    $selectedContact = getUserById($xml, $_GET['contact']);
    if ($selectedContact) {
        $conversation = getConversation($xml, $_SESSION['user_id'], $_GET['contact']);
        // Marquer les messages comme lus
        foreach ($xml->messages->message as $msg) {
            if ((string)$msg->expediteur === $_GET['contact'] && 
                (string)$msg->destinataire === $_SESSION['user_id']) {
                $msg->lu = 'true';
            }
        }
        saveXML($xml);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP Chat - Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
            background: #f0f0f0;
        }
        
        .app-container {
            display: flex;
            height: 100vh;
            background: white;
        }
        
        /* Sidebar */
        .sidebar {
            width: 350px;
            border-right: 1px solid #e1e1e1;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .sidebar-header {
            background: #075e54;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #25D366;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .search-bar {
            padding: 0.75rem 1rem;
            background: #f6f6f6;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .search-input {
            width: 100%;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            background: white;
            font-size: 0.9rem;
        }
        
        .contacts-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .contact-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f1f1;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.2s;
        }
        
        .contact-item:hover {
            background: #f5f5f5;
        }
        
        .contact-item.active {
            background: #e8f5e8;
        }
        
        .contact-info {
            flex: 1;
        }
        
        .contact-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .last-message {
            color: #666;
            font-size: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .contact-meta {
            text-align: right;
            font-size: 0.75rem;
            color: #666;
        }
        
        .unread-badge {
            background: #25D366;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-top: 0.25rem;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: #075e54;
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .messages-container {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><circle cx="30" cy="30" r="2" fill="%23f0f0f0"/></svg>');
        }
        
        .message {
            margin-bottom: 1rem;
            display: flex;
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            position: relative;
        }
        
        .message.sent .message-bubble {
            background: #dcf8c6;
        }
        
        .message.received .message-bubble {
            background: white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #666;
            margin-top: 0.25rem;
            text-align: right;
        }
        
        .message-input-area {
            padding: 1rem;
            background: #f0f0f0;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .message-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 20px;
            font-size: 1rem;
        }
        
        .send-button {
            background: #25D366;
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #666;
            text-align: center;
        }
        
        .add-contact-form {
            padding: 1rem;
            background: #f9f9f9;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .add-contact-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }
        
        .add-contact-btn {
            background: #25D366;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;">
                ✅ <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0;">
                ❌ <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <div class="avatar">
                        <?= strtoupper(substr($currentUser->nom, 0, 2)) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($currentUser->nom) ?></div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">En ligne</div>
                    </div>
                </div>
                <form method="post" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Déconnexion</button>
                </form>
            </div>
            
            <!-- Formulaire d'ajout de contact -->
            <div class="add-contact-form">
                <form method="post">
                    <input type="hidden" name="action" value="add_contact">
                    <input type="tel" name="telephone" placeholder="Numéro de téléphone du contact" class="add-contact-input" required>
                    <button type="submit" class="add-contact-btn">➕ Ajouter contact</button>
                </form>
            </div>
            
            <div class="search-bar">
                <input type="text" class="search-input" placeholder="Rechercher ou commencer une nouvelle discussion">
            </div>
            
            <div class="contacts-list">
                <?php if (empty($contacts)): ?>
                    <div style="padding: 2rem; text-align: center; color: #666;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                        <p>Aucun contact pour le moment</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Ajoutez des contacts en utilisant leur numéro de téléphone</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($contacts as $contact): ?>
                        <div class="contact-item <?= (isset($_GET['contact']) && $_GET['contact'] === (string)$contact['user']['id']) ? 'active' : '' ?>" 
                             onclick="window.location.href='?contact=<?= $contact['user']['id'] ?>'">
                            <div class="avatar">
                                <?= strtoupper(substr($contact['user']->nom, 0, 2)) ?>
                            </div>
                            <div class="contact-info">
                                <div class="contact-name"><?= htmlspecialchars($contact['user']->nom) ?></div>
                                <div class="last-message">
                                    <?php if ($contact['last_message']): ?>
                                        <?php if ((string)$contact['last_message']->expediteur === $_SESSION['user_id']): ?>
                                            Vous: <?= htmlspecialchars(substr($contact['last_message']->contenu, 0, 30)) ?>...
                                        <?php else: ?>
                                            <?= htmlspecialchars(substr($contact['last_message']->contenu, 0, 30)) ?>...
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Aucun message
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="contact-meta">
                                <?php if ($contact['last_message']): ?>
                                    <div><?= date('H:i', strtotime($contact['last_message']->date)) ?></div>
                                <?php endif; ?>
                                <?php if ($contact['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $contact['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($selectedContact): ?>
                <div class="chat-header">
                    <div class="avatar">
                        <?= strtoupper(substr($selectedContact->nom, 0, 2)) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($selectedContact->nom) ?></div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">En ligne</div>
                    </div>
                </div>
                
                <div class="messages-container" id="messagesContainer">
                    <?php foreach ($conversation as $msg): ?>
                        <div class="message <?= (string)$msg->expediteur === $_SESSION['user_id'] ? 'sent' : 'received' ?>">
                            <div class="message-bubble">
                                <?= nl2br(htmlspecialchars($msg->contenu)) ?>
                                <div class="message-time">
                                    <?= date('H:i', strtotime($msg->date)) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="message-input-area">
                    <form method="post" style="display: flex; gap: 1rem; align-items: center; width: 100%;" onsubmit="sendMessage(event)">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="destinataire" value="<?= $selectedContact['id'] ?>">
                        <input type="text" name="contenu" class="message-input" placeholder="Tapez votre message..." required id="messageInput">
                        <button type="submit" class="send-button">
                            ➤
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-chat">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">💬</div>
                    <h2>ESP Chat</h2>
                    <p>Sélectionnez une conversation pour commencer à chatter</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-scroll vers le bas des messages
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Fonction pour envoyer un message sans recharger la page
        function sendMessage(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Recharger seulement la zone des messages
                location.reload();
                // Vider le champ de saisie
                document.getElementById('messageInput').value = '';
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
        }
        
        // Auto-refresh toutes les 5 secondes si on est dans une conversation
        <?php if ($selectedContact): ?>
        setInterval(() => {
            location.reload();
        }, 5000);
        <?php endif; ?>
        
        // Permettre l'envoi avec la touche Entrée
        document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>
