<?php
session_start();
require_once 'src/utils.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Gestion des requêtes AJAX pour le rafraîchissement des messages
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Recharger les données XML
    $xml = loadXML();
    $currentUser = getUserById($xml, $_SESSION['user_id']);
    
    if (!$currentUser) {
        exit;
    }
    
    // Récupérer la conversation en cours
    $selectedContact = null;
    $selectedGroup = null;
    $conversation = [];
    $isGroupChat = false;
    
    if (isset($_GET['contact'])) {
        $selectedContact = getUserById($xml, $_GET['contact']);
        if ($selectedContact) {
            $conversation = getConversationAjax($xml, $_SESSION['user_id'], $_GET['contact']);
        }
    } elseif (isset($_GET['group'])) {
        foreach ($xml->groupes->groupe as $group) {
            if ((string)$group['id'] === $_GET['group']) {
                $selectedGroup = $group;
                break;
            }
        }
        if ($selectedGroup) {
            $isGroupChat = true;
            $conversation = getGroupConversationAjax($xml, $_GET['group']);
        }
    }
    
    // Renvoyer seulement le HTML de la zone des messages
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body>
        <div class="messages-container" id="messagesContainer">
            <?php foreach ($conversation as $msg): ?>
                <?php if (is_object($msg) && isset($msg->expediteur) && isset($msg->contenu) && isset($msg->date)): ?>
                <div class="message <?= (string)$msg->expediteur === $_SESSION['user_id'] ? 'sent' : 'received' ?>">
                    <div class="message-bubble">
                        <?php if ($isGroupChat && (string)$msg->expediteur !== $_SESSION['user_id']): ?>
                            <?php 
                            $senderUser = getUserById($xml, (string)$msg->expediteur);
                            $senderName = $senderUser ? $senderUser->nom : 'Utilisateur inconnu';
                            ?>
                            <div style="font-size: 0.8rem; color: #25D366; font-weight: bold; margin-bottom: 0.25rem;">
                                <?= htmlspecialchars($senderName) ?>
                            </div>
                        <?php endif; ?>
                        <?= nl2br(htmlspecialchars((string)$msg->contenu)) ?>
                        <div class="message-time">
                            <?= date('H:i', strtotime((string)$msg->date)) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// API de vérification rapide des nouveaux messages
if (isset($_GET['check']) && $_GET['check'] == '1') {
    header('Content-Type: application/json');
    
    $xml = loadXML();
    $conversation = [];
    
    if (isset($_GET['contact'])) {
        $conversation = getConversationAjax($xml, $_SESSION['user_id'], $_GET['contact']);
    } elseif (isset($_GET['group'])) {
        $conversation = getGroupConversationAjax($xml, $_GET['group']);
    }
    
    $response = [
        'messageCount' => count($conversation),
        'lastMessageId' => !empty($conversation) ? (string)$conversation[count($conversation)-1]['id'] : '',
        'timestamp' => time()
    ];
    
    echo json_encode($response);
    exit;
}

// Fonctions pour AJAX (duplicatas temporaires)
function getConversationAjax($xml, $user1Id, $user2Id) {
    $conversation = [];
    foreach ($xml->messages->message as $msg) {
        $expediteur = (string)$msg->expediteur;
        $destinataire = (string)$msg->destinataire;
        
        if (($expediteur === $user1Id && $destinataire === $user2Id) ||
            ($expediteur === $user2Id && $destinataire === $user1Id)) {
            $conversation[] = $msg;
        }
    }
    
    // Trier par date en utilisant uasort au lieu de usort pour préserver les clés
    uasort($conversation, function($a, $b) {
        if (!isset($a->date) || !isset($b->date)) {
            return 0;
        }
        return strtotime($a->date) - strtotime($b->date);
    });
    
    return array_values($conversation); // Réindexer le tableau
}

function getGroupConversationAjax($xml, $groupId) {
    $conversation = [];
    foreach ($xml->messages->message as $msg) {
        if ((string)$msg->destinataire === $groupId && 
            isset($msg->type) && (string)$msg->type === 'group') {
            $conversation[] = $msg;
        }
    }
    
    // Trier par date en utilisant uasort au lieu de usort pour préserver les clés
    uasort($conversation, function($a, $b) {
        if (!isset($a->date) || !isset($b->date)) {
            return 0;
        }
        return strtotime($a->date) - strtotime($b->date);
    });
    
    return array_values($conversation); // Réindexer le tableau
}

// Fonction pour récupérer les messages d'un groupe (version principale)
function getGroupConversation($xml, $groupId) {
    $conversation = [];
    foreach ($xml->messages->message as $msg) {
        if ((string)$msg->destinataire === $groupId && 
            isset($msg->type) && (string)$msg->type === 'group') {
            $conversation[] = $msg;
        }
    }
    
    // Trier par date en utilisant uasort au lieu de usort pour préserver les clés
    uasort($conversation, function($a, $b) {
        if (!isset($a->date) || !isset($b->date)) {
            return 0;
        }
        return strtotime($a->date) - strtotime($b->date);
    });
    
    return array_values($conversation); // Réindexer le tableau
}

$xml = loadXML();
$currentUser = getUserById($xml, $_SESSION['user_id']);

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
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
            $isGroup = $_POST['is_group'] ?? 'false';
            
            if ($destinataire && $contenu) {
                // Vérifier si l'utilisateur est bloqué (sauf pour les groupes)
                if ($isGroup === 'false' && isUserBlocked($xml, $destinataire, $_SESSION['user_id'])) {
                    $error = "Vous ne pouvez pas envoyer de message à cet utilisateur.";
                } else {
                    $messageId = 'm' . time();
                    $message = $xml->messages->addChild('message');
                    $message->addAttribute('id', $messageId);
                    $message->addChild('expediteur', $_SESSION['user_id']);
                    $message->addChild('destinataire', $destinataire);
                    $message->addChild('contenu', htmlspecialchars($contenu));
                    $message->addChild('date', date('c'));
                    $message->addChild('lu', 'false');
                    
                    // Ajouter un flag pour indiquer si c'est un message de groupe
                    if ($isGroup === 'true') {
                        $message->addChild('type', 'group');
                    } else {
                        $message->addChild('type', 'private');
                    }
                    
                    saveXML($xml);
                }
            }
            break;
            
        case 'create_group':
            $groupName = trim($_POST['group_name'] ?? '');
            $selectedMembers = $_POST['members'] ?? [];
            
            if ($groupName) {
                // Ajouter l'utilisateur courant comme membre du groupe
                if (!in_array($_SESSION['user_id'], $selectedMembers)) {
                    $selectedMembers[] = $_SESSION['user_id'];
                }
                
                $groupId = createGroup($xml, $groupName, $selectedMembers);
                saveXML($xml);
                $success = "Groupe '$groupName' créé avec succès !";
            } else {
                $error = "Le nom du groupe est obligatoire !";
            }
            break;
            
        case 'add_group_member':
            $groupId = $_POST['group_id'] ?? '';
            $userId = $_POST['user_id'] ?? '';
            
            if ($groupId && $userId) {
                // Vérifier que l'utilisateur courant est membre du groupe
                if (isGroupMember($xml, $groupId, $_SESSION['user_id'])) {
                    if (addMemberToGroup($xml, $groupId, $userId)) {
                        saveXML($xml);
                        $success = "Membre ajouté au groupe avec succès !";
                    } else {
                        $error = "Erreur lors de l'ajout du membre au groupe !";
                    }
                } else {
                    $error = "Vous n'êtes pas membre de ce groupe !";
                }
            }
            break;
            
        case 'leave_group':
            $groupId = $_POST['group_id'] ?? '';
            
            if ($groupId) {
                if (leaveGroup($xml, $groupId, $_SESSION['user_id'])) {
                    saveXML($xml);
                    $success = "Vous avez quitté le groupe avec succès !";
                } else {
                    $error = "Erreur lors de la sortie du groupe !";
                }
            }
            break;
            
        case 'update_avatar':
            $avatar = $_POST['avatar'] ?? '';
            
            if ($avatar) {
                $currentUser->avatar = htmlspecialchars($avatar);
                saveXML($xml);
                $success = "Photo de profil mise à jour !";
            } else {
                $error = "Veuillez sélectionner une photo de profil !";
            }
            break;
            
        case 'block_user':
            $userIdToBlock = $_POST['user_id'] ?? '';
            
            if ($userIdToBlock) {
                if (blockUser($xml, $_SESSION['user_id'], $userIdToBlock)) {
                    saveXML($xml);
                    $success = "Utilisateur bloqué avec succès !";
                } else {
                    $error = "Erreur lors du blocage de l'utilisateur !";
                }
            }
            break;
            
        case 'add_contact_from_chat':
            $userIdToAdd = $_POST['user_id'] ?? '';
            
            if ($userIdToAdd) {
                $userToAdd = getUserById($xml, $userIdToAdd);
                if ($userToAdd) {
                    // Vérifier si le contact existe déjà
                    if (!isContact($xml, $_SESSION['user_id'], $userIdToAdd)) {
                        if (!$currentUser->contacts) {
                            $currentUser->addChild('contacts');
                        }
                        
                        $newContact = $currentUser->contacts->addChild('contact');
                        $newContact->addAttribute('id', $userIdToAdd);
                        $newContact->addAttribute('date_ajout', date('c'));
                        saveXML($xml);
                        $success = "Contact ajouté : " . $userToAdd->nom;
                    } else {
                        $error = "Ce contact est déjà dans votre liste !";
                    }
                } else {
                    $error = "Utilisateur introuvable !";
                }
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
                'unread_count' => getUnreadMessagesCount($xml, $_SESSION['user_id'], (string)$contact['id']),
                'is_contact' => true
            ];
        }
    }
}

// Récupérer les conversations avec des non-contacts
$nonContactConversations = [];
foreach ($xml->messages->message as $msg) {
    $otherUserId = null;
    if ((string)$msg->expediteur === $_SESSION['user_id']) {
        $otherUserId = (string)$msg->destinataire;
    } elseif ((string)$msg->destinataire === $_SESSION['user_id']) {
        $otherUserId = (string)$msg->expediteur;
    }
    
    if ($otherUserId && 
        !isContact($xml, $_SESSION['user_id'], $otherUserId) && 
        !isUserBlocked($xml, $_SESSION['user_id'], $otherUserId) &&
        !isset($nonContactConversations[$otherUserId])) {
        
        $otherUser = getUserById($xml, $otherUserId);
        if ($otherUser) {
            $nonContactConversations[$otherUserId] = [
                'user' => $otherUser,
                'last_message' => getLastMessageBetween($xml, $_SESSION['user_id'], $otherUserId),
                'unread_count' => getUnreadMessagesCount($xml, $_SESSION['user_id'], $otherUserId),
                'is_contact' => false
            ];
        }
    }
}

// Définir les variables pour l'affichage du chat
$selectedContact = null;
$selectedGroup = null;
$conversation = [];
$isGroupChat = false;
$userGroups = [];

// Récupérer les groupes de l'utilisateur
if ($xml->groupes) {
    foreach ($xml->groupes->groupe as $group) {
        foreach ($group->membre as $membre) {
            if ((string)$membre['id'] === $_SESSION['user_id']) {
                $lastMessage = null;
                $unreadCount = 0;
                
                // Récupérer le dernier message du groupe
                foreach ($xml->messages->message as $msg) {
                    if ((string)$msg->destinataire === (string)$group['id'] && 
                        isset($msg->type) && (string)$msg->type === 'group') {
                        if (!$lastMessage || strtotime($msg->date) > strtotime($lastMessage->date)) {
                            $lastMessage = $msg;
                        }
                        // Compter les messages non lus
                        if ((string)$msg->expediteur !== $_SESSION['user_id'] && (string)$msg->lu === 'false') {
                            $unreadCount++;
                        }
                    }
                }
                
                $userGroups[] = [
                    'group' => $group,
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount
                ];
                break;
            }
        }
    }
}

// Traitement de la sélection d'un contact ou groupe
if (isset($_GET['contact'])) {
    $selectedContact = getUserById($xml, $_GET['contact']);
    if ($selectedContact) {
        $conversation = [];
        foreach ($xml->messages->message as $msg) {
            $expediteur = (string)$msg->expediteur;
            $destinataire = (string)$msg->destinataire;
            
            if (($expediteur === $_SESSION['user_id'] && $destinataire === $_GET['contact']) ||
                ($expediteur === $_GET['contact'] && $destinataire === $_SESSION['user_id'])) {
                $conversation[] = $msg;
            }
        }
        
        // Trier par date
        uasort($conversation, function($a, $b) {
            if (!isset($a->date) || !isset($b->date)) {
                return 0;
            }
            return strtotime($a->date) - strtotime($b->date);
        });
        
        $conversation = array_values($conversation);
    }
} elseif (isset($_GET['group'])) {
    foreach ($xml->groupes->groupe as $group) {
        if ((string)$group['id'] === $_GET['group']) {
            $selectedGroup = $group;
            $isGroupChat = true;
            break;
        }
    }
    if ($selectedGroup) {
        $conversation = getGroupConversation($xml, $_GET['group']);
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
            position: relative;
            overflow: hidden;
        }
        
        .avatar.default-avatar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }
        
        .avatar.default-avatar::before {
            content: '👤';
            font-size: 20px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .avatar.default-avatar.initials::before {
            content: attr(data-initials);
            font-size: 14px;
            font-weight: bold;
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
        
        .avatar-selector {
            transition: all 0.2s;
        }
        
        .avatar-selector:hover {
            background: #e8f5e8;
            transform: scale(1.1);
        }
        
        .avatar-selector.selected {
            background: #25D366;
            color: white;
        }
    </style>
</head>
<body>
    <?php
    // Initialiser les variables pour éviter les warnings
    if (!isset($selectedContact)) $selectedContact = null;
    if (!isset($selectedGroup)) $selectedGroup = null;
    ?>
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
                    <div class="avatar <?= (!isset($currentUser->avatar) || empty($currentUser->avatar)) ? 'default-avatar initials' : '' ?>" 
                         <?= (!isset($currentUser->avatar) || empty($currentUser->avatar)) ? 'data-initials="' . strtoupper(substr($currentUser->nom, 0, 2)) . '"' : '' ?>>
                        <?php if (isset($currentUser->avatar) && !empty($currentUser->avatar)): ?>
                            <?= $currentUser->avatar ?>
                        <?php endif; ?>
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
            
            <!-- Bouton de création de groupe -->
            <div class="add-contact-form">
                <button type="button" class="add-contact-btn" onclick="toggleGroupForm()" style="background: #128C7E;">
                    👥 Créer un groupe
                </button>
            </div>
            
            <!-- Formulaire de création de groupe (caché par défaut) -->
            <div id="groupForm" class="add-contact-form" style="display: none;">
                <form method="post">
                    <input type="hidden" name="action" value="create_group">
                    <input type="text" name="group_name" placeholder="Nom du groupe" class="add-contact-input" required>
                    <div style="margin: 10px 0; font-size: 0.9rem; color: #666;">
                        Sélectionnez les membres :
                    </div>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">
                        <?php foreach ($contacts as $contact): ?>
                            <label style="display: block; padding: 5px; cursor: pointer;">
                                <input type="checkbox" name="members[]" value="<?= $contact['user']['id'] ?>" style="margin-right: 8px;">
                                <?= htmlspecialchars($contact['user']->nom) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display: flex; gap: 5px; margin-top: 10px;">
                        <button type="submit" class="add-contact-btn" style="flex: 1;">✅ Créer</button>
                        <button type="button" class="add-contact-btn" onclick="toggleGroupForm()" style="background: #ccc; color: #333;">❌ Annuler</button>
                    </div>
                </form>
            </div>
            
            <!-- Bouton de gestion du profil -->
            <div class="add-contact-form">
                <button type="button" class="add-contact-btn" onclick="toggleProfileForm()" style="background: #6f42c1;">
                    👤 Gérer mon profil
                </button>
            </div>
            
            <!-- Formulaire de gestion du profil (caché par défaut) -->
            <div id="profileForm" class="add-contact-form" style="display: none;">
                <form method="post">
                    <input type="hidden" name="action" value="update_avatar">
                    <div style="margin: 10px 0; font-size: 0.9rem; color: #666;">
                        Choisir une photo de profil :
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin: 10px 0;">
                        <?php 
                        $avatars = ['👤', '😀', '😎', '🤓', '😊', '🙂', '😉', '🤗', '🧑‍💻', '👨‍💼', '👩‍💼', '🧑‍🎓', '👨‍🎓', '👩‍🎓', '🧑‍🏫', '👨‍🏫'];
                        foreach ($avatars as $avatar): 
                        ?>
                            <label style="text-align: center; cursor: pointer; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" 
                                   class="avatar-selector" onclick="selectAvatar(this)">
                                <input type="radio" name="avatar" value="<?= $avatar ?>" style="display: none;" 
                                       <?= $currentUser->avatar === $avatar ? 'checked' : '' ?>>
                                <span style="font-size: 20px;"><?= $avatar ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display: flex; gap: 5px; margin-top: 10px;">
                        <button type="submit" class="add-contact-btn" style="flex: 1;">✅ Mettre à jour</button>
                        <button type="button" class="add-contact-btn" onclick="toggleProfileForm()" style="background: #ccc; color: #333;">❌ Annuler</button>
                    </div>
                </form>
            </div>
            
            <div class="search-bar">
                <input type="text" class="search-input" placeholder="Rechercher ou commencer une nouvelle discussion">
            </div>
            
            <div class="contacts-list">
                <!-- Section des groupes -->
                <?php if (!empty($userGroups)): ?>
                    <div style="padding: 0.5rem 1rem; background: #f0f0f0; font-weight: bold; font-size: 0.9rem; color: #666;">
                        GROUPES
                    </div>
                    <?php foreach ($userGroups as $groupData): ?>
                        <div class="contact-item <?= (isset($_GET['group']) && $_GET['group'] === (string)$groupData['group']['id']) ? 'active' : '' ?>" 
                             onclick="window.location.href='?group=<?= $groupData['group']['id'] ?>'">
                            <div class="avatar" style="background: #128C7E;">
                                👥
                            </div>
                            <div class="contact-info">
                                <div class="contact-name"><?= htmlspecialchars($groupData['group']->nom) ?></div>
                                <div class="last-message">
                                    <?php if ($groupData['last_message']): ?>
                                        <?php 
                                        $senderUser = getUserById($xml, (string)$groupData['last_message']->expediteur);
                                        $senderName = $senderUser ? $senderUser->nom : 'Quelqu\'un';
                                        ?>
                                        <?php if ((string)$groupData['last_message']->expediteur === $_SESSION['user_id']): ?>
                                            Vous: <?= htmlspecialchars(substr($groupData['last_message']->contenu, 0, 25)) ?>...
                                        <?php else: ?>
                                            <?= htmlspecialchars($senderName) ?>: <?= htmlspecialchars(substr($groupData['last_message']->contenu, 0, 20)) ?>...
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php 
                                        $memberCount = count($groupData['group']->membre);
                                        echo "$memberCount membre" . ($memberCount > 1 ? 's' : '');
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="contact-meta">
                                <?php if ($groupData['last_message']): ?>
                                    <div><?= date('H:i', strtotime($groupData['last_message']->date)) ?></div>
                                <?php endif; ?>
                                <?php if ($groupData['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $groupData['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Section des contacts -->
                <?php if (!empty($contacts)): ?>
                    <?php if (!empty($userGroups)): ?>
                        <div style="padding: 0.5rem 1rem; background: #f0f0f0; font-weight: bold; font-size: 0.9rem; color: #666; margin-top: 10px;">
                            CONTACTS
                        </div>
                    <?php endif; ?>
                    <?php foreach ($contacts as $contact): ?>
                        <div class="contact-item <?= (isset($_GET['contact']) && $_GET['contact'] === (string)$contact['user']['id']) ? 'active' : '' ?>" 
                             onclick="window.location.href='?contact=<?= $contact['user']['id'] ?>'">
                            <div class="avatar <?= (!isset($contact['user']->avatar) || empty($contact['user']->avatar)) ? 'default-avatar initials' : '' ?>"
                                 <?= (!isset($contact['user']->avatar) || empty($contact['user']->avatar)) ? 'data-initials="' . strtoupper(substr($contact['user']->nom, 0, 2)) . '"' : '' ?>>
                                <?php if (isset($contact['user']->avatar) && !empty($contact['user']->avatar)): ?>
                                    <?= $contact['user']->avatar ?>
                                <?php endif; ?>
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
                
                <!-- Section des conversations avec non-contacts -->
                <?php if (!empty($nonContactConversations)): ?>
                    <div style="padding: 0.5rem 1rem; background: #fff3cd; font-weight: bold; font-size: 0.9rem; color: #856404; margin-top: 10px;">
                        MESSAGES REÇUS
                    </div>
                    <?php foreach ($nonContactConversations as $conversation): ?>
                        <div class="contact-item <?= (isset($_GET['contact']) && $_GET['contact'] === (string)$conversation['user']['id']) ? 'active' : '' ?>" 
                             onclick="window.location.href='?contact=<?= $conversation['user']['id'] ?>&non_contact=1'">
                            <div class="avatar <?= (!isset($conversation['user']->avatar) || empty($conversation['user']->avatar)) ? 'default-avatar initials' : '' ?>" 
                                 style="border: 2px solid #ffc107;" 
                                 <?= (!isset($conversation['user']->avatar) || empty($conversation['user']->avatar)) ? 'data-initials="' . strtoupper(substr($conversation['user']->nom, 0, 2)) . '"' : '' ?>>
                                <?php if (isset($conversation['user']->avatar) && !empty($conversation['user']->avatar)): ?>
                                    <?= $conversation['user']->avatar ?>
                                <?php endif; ?>
                            </div>
                            <div class="contact-info">
                                <div class="contact-name">
                                    <?= htmlspecialchars($conversation['user']->nom) ?>
                                    <span style="color: #ffc107; font-size: 0.7rem;">●</span>
                                </div>
                                <div class="last-message">
                                    <?php if ($conversation['last_message']): ?>
                                        <?php if ((string)$conversation['last_message']->expediteur === $_SESSION['user_id']): ?>
                                            Vous: <?= htmlspecialchars(substr($conversation['last_message']->contenu, 0, 30)) ?>...
                                        <?php else: ?>
                                            <?= htmlspecialchars(substr($conversation['last_message']->contenu, 0, 30)) ?>...
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="contact-meta">
                                <?php if ($conversation['last_message']): ?>
                                    <div><?= date('H:i', strtotime($conversation['last_message']->date)) ?></div>
                                <?php endif; ?>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <div class="unread-badge" style="background: #ffc107;"><?= $conversation['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Message si aucun contact et aucun groupe -->
                <?php if (empty($contacts) && empty($userGroups) && empty($nonContactConversations)): ?>
                    <div style="padding: 2rem; text-align: center; color: #666;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                        <p>Aucun contact ou groupe pour le moment</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Ajoutez des contacts ou créez des groupes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($selectedContact || $selectedGroup): ?>
                <div class="chat-header">
                    <div class="avatar <?= $selectedGroup ? '' : ((!isset($selectedContact->avatar) || empty($selectedContact->avatar)) ? 'default-avatar initials' : '') ?>" 
                         style="<?= $selectedGroup ? 'background: #128C7E;' : '' ?>"
                         <?= (!$selectedGroup && (!isset($selectedContact->avatar) || empty($selectedContact->avatar))) ? 'data-initials="' . strtoupper(substr($selectedContact->nom, 0, 2)) . '"' : '' ?>>
                        <?php if ($selectedGroup): ?>
                            👥
                        <?php else: ?>
                            <?php if (isset($selectedContact->avatar) && !empty($selectedContact->avatar)): ?>
                                <?= $selectedContact->avatar ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600;">
                            <?php if ($selectedGroup): ?>
                                <?= htmlspecialchars($selectedGroup->nom) ?>
                            <?php else: ?>
                                <?= htmlspecialchars($selectedContact->nom) ?>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">
                            <?php if ($selectedGroup): ?>
                                <?php 
                                $memberCount = count($selectedGroup->membre);
                                echo "$memberCount membre" . ($memberCount > 1 ? 's' : '');
                                ?>
                            <?php else: ?>
                                En ligne
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($selectedGroup): ?>
                        <div style="display: flex; gap: 10px;">
                            <button onclick="toggleAddMemberForm()" class="add-contact-btn" style="padding: 5px 10px; font-size: 0.8rem;">
                                ➕ Ajouter membre
                            </button>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir quitter ce groupe ?')">
                                <input type="hidden" name="action" value="leave_group">
                                <input type="hidden" name="group_id" value="<?= $selectedGroup['id'] ?>">
                                <button type="submit" class="logout-btn" style="padding: 5px 10px; font-size: 0.8rem;">
                                    🚪 Quitter
                                </button>
                            </form>
                        </div>
                    <?php elseif ($selectedContact && isset($_GET['non_contact'])): ?>
                        <!-- Actions pour non-contact -->
                        <div style="display: flex; gap: 5px;">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="add_contact_from_chat">
                                <input type="hidden" name="user_id" value="<?= $selectedContact['id'] ?>">
                                <button type="submit" class="add-contact-btn" style="padding: 3px 8px; font-size: 0.7rem;">
                                    ➕ Ajouter
                                </button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir bloquer cet utilisateur ?')">
                                <input type="hidden" name="action" value="block_user">
                                <input type="hidden" name="user_id" value="<?= $selectedContact['id'] ?>">
                                <button type="submit" class="logout-btn" style="padding: 3px 8px; font-size: 0.7rem;">
                                    🚫 Bloquer
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($selectedGroup): ?>
                    <!-- Formulaire d'ajout de membre (caché par défaut) -->
                    <div id="addMemberForm" class="add-contact-form" style="display: none; border-bottom: 1px solid #e1e1e1;">
                        <form method="post">
                            <input type="hidden" name="action" value="add_group_member">
                            <input type="hidden" name="group_id" value="<?= $selectedGroup['id'] ?>">
                            <div style="margin: 10px 0; font-size: 0.9rem; color: #666;">
                                Ajouter un membre :
                            </div>
                            <select name="user_id" class="add-contact-input" required>
                                <option value="">Choisir un contact</option>
                                <?php 
                                $nonMembers = getNonGroupMembers($xml, (string)$selectedGroup['id'], $_SESSION['user_id']);
                                foreach ($nonMembers as $user): 
                                ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user->nom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="display: flex; gap: 5px; margin-top: 10px;">
                                <button type="submit" class="add-contact-btn" style="flex: 1;">✅ Ajouter</button>
                                <button type="button" class="add-contact-btn" onclick="toggleAddMemberForm()" style="background: #ccc; color: #333;">❌ Annuler</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if ($selectedContact && isset($_GET['non_contact'])): ?>
                    <!-- Notification pour non-contact -->
                    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 0; color: #856404; font-size: 0.9rem; text-align: center;">
                        ⚠️ Cette personne n'est pas dans vos contacts. Vous pouvez répondre, l'ajouter ou la bloquer.
                    </div>
                <?php endif; ?>
                
                <div class="messages-container" id="messagesContainer">
                    <?php foreach ($conversation as $msg): ?>
                        <?php if (is_object($msg) && isset($msg->expediteur) && isset($msg->contenu) && isset($msg->date)): ?>
                        <div class="message <?= (string)$msg->expediteur === $_SESSION['user_id'] ? 'sent' : 'received' ?>">
                            <div class="message-bubble">
                                <?php if ($isGroupChat && (string)$msg->expediteur !== $_SESSION['user_id']): ?>
                                    <?php 
                                    $senderUser = getUserById($xml, (string)$msg->expediteur);
                                    $senderName = $senderUser ? $senderUser->nom : 'Utilisateur inconnu';
                                    ?>
                                    <div style="font-size: 0.8rem; color: #25D366; font-weight: bold; margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($senderName) ?>
                                    </div>
                                <?php endif; ?>
                                <?= nl2br(htmlspecialchars((string)$msg->contenu)) ?>
                                <div class="message-time">
                                    <?= date('H:i', strtotime((string)$msg->date)) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="message-input-area">
                    <form method="post" style="display: flex; gap: 1rem; align-items: center; width: 100%" onsubmit="sendMessage(event)">
                        <input type="hidden" name="action" value="send_message">
                        <?php if ($selectedGroup): ?>
                            <input type="hidden" name="destinataire" value="<?= $selectedGroup['id'] ?>">
                            <input type="hidden" name="is_group" value="true">
                        <?php else: ?>
                            <input type="hidden" name="destinataire" value="<?= $selectedContact['id'] ?>">
                            <input type="hidden" name="is_group" value="false">
                        <?php endif; ?>
                        <input type="text" name="contenu" class="message-input" placeholder="Tapez votre message..." required id="messageInput">
                        <button type="submit" class="send-button">
                            ➤
                        </button>
                    </form>
                </div>
            <?php elseif ($selectedGroup): ?>
                <div class="chat-header">
                    <div class="avatar" style="background: #128C7E;">
                        👥
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($selectedGroup->nom) ?></div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">
                            <?php 
                            $memberCount = count($selectedGroup->membre);
                            echo "$memberCount membre" . ($memberCount > 1 ? 's' : '');
                            ?>
                        </div>
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
                    <form method="post" style="display: flex; gap: 1rem; align-items: center; width: 100%" onsubmit="sendMessage(event)">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="destinataire" value="<?= $selectedGroup['id'] ?>">
                        <input type="hidden" name="is_group" value="true">
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
        let isTyping = false;
        let typingTimer;
        let lastMessageCount = <?= count($conversation) ?>;
        let lastMessageId = '<?= !empty($conversation) ? (string)$conversation[count($conversation)-1]['id'] : '' ?>';
        let checkInterval;
        
        // Auto-scroll vers le bas des messages
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Détecter quand l'utilisateur tape
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                isTyping = true;
                clearTimeout(typingTimer);
                
                // Arrêter de considérer l'utilisateur comme "en train de taper" après 2 secondes d'inactivité
                typingTimer = setTimeout(() => {
                    isTyping = false;
                }, 2000);
            });
            
            messageInput.addEventListener('blur', function() {
                // Ne pas arrêter isTyping immédiatement au blur, garder un délai
                setTimeout(() => {
                    if (!messageInput.value.trim()) {
                        isTyping = false;
                    }
                }, 1000);
            });
            
            messageInput.addEventListener('focus', function() {
                // Rafraîchir immédiatement quand on reprend le focus
                if (!isTyping) {
                    refreshMessages();
                }
            });
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
                // Vider le champ de saisie
                document.getElementById('messageInput').value = '';
                isTyping = false;
                
                // Rafraîchir les messages immédiatement après envoi
                setTimeout(() => {
                    refreshMessages();
                }, 200);
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
        }
        
        // Fonction pour vérifier rapidement s'il y a de nouveaux messages
        function checkForNewMessages() {
            if (isTyping) {
                return; // Ne pas vérifier si l'utilisateur tape
            }
            
            fetch(window.location.href + '&check=1', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                // Si il y a de nouveaux messages, rafraîchir
                if (data.messageCount > lastMessageCount || data.lastMessageId !== lastMessageId) {
                    refreshMessages();
                    lastMessageCount = data.messageCount;
                    lastMessageId = data.lastMessageId;
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification:', error);
            });
        }
        
        // Fonction pour rafraîchir seulement les messages via AJAX
        function refreshMessages() {
            fetch(window.location.href + '&ajax=1', {
                method: 'GET'
            })
            .then(response => response.text())
            .then(html => {
                // Extraire seulement la partie des messages
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMessagesContainer = doc.getElementById('messagesContainer');
                
                if (newMessagesContainer && messagesContainer) {
                    // Sauvegarder la position de scroll
                    const wasAtBottom = messagesContainer.scrollTop >= messagesContainer.scrollHeight - messagesContainer.clientHeight - 100;
                    
                    // Remplacer le contenu
                    messagesContainer.innerHTML = newMessagesContainer.innerHTML;
                    
                    // Auto-scroll seulement si l'utilisateur était déjà en bas ou si c'est un nouveau message
                    if (wasAtBottom || !isTyping) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                    
                    // Mettre à jour le compteur
                    lastMessageCount = messagesContainer.children.length;
                }
            })
            .catch(error => {
                console.error('Erreur lors du rafraîchissement:', error);
            });
        }
        
        // Système de vérification intelligente
        <?php if ($selectedContact || $selectedGroup): ?>
        // Vérification rapide toutes les secondes
        checkInterval = setInterval(() => {
            checkForNewMessages();
        }, 1000);
        
        // Rafraîchissement complet moins fréquent (si pas de nouveaux messages détectés)
        setInterval(() => {
            if (!isTyping) {
                refreshMessages();
            }
        }, 5000);
        <?php endif; ?>
        
        // Permettre l'envoi avec la touche Entrée
        document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').dispatchEvent(new Event('submit'));
            }
        });
        
        // Afficher / cacher le formulaire de groupe
        function toggleGroupForm() {
            const groupForm = document.getElementById('groupForm');
            if (groupForm.style.display === 'none' || groupForm.style.display === '') {
                groupForm.style.display = 'block';
            } else {
                groupForm.style.display = 'none';
            }
        }
        
        // Afficher / cacher le formulaire de profil
        function toggleProfileForm() {
            const profileForm = document.getElementById('profileForm');
            if (profileForm.style.display === 'none' || profileForm.style.display === '') {
                profileForm.style.display = 'block';
            } else {
                profileForm.style.display = 'none';
            }
        }
        
        // Afficher / cacher le formulaire d'ajout de membre
        function toggleAddMemberForm() {
            const addMemberForm = document.getElementById('addMemberForm');
            if (addMemberForm.style.display === 'none' || addMemberForm.style.display === '') {
                addMemberForm.style.display = 'block';
            } else {
                addMemberForm.style.display = 'none';
            }
        }
        
        // Gérer la sélection d'avatar
        function selectAvatar(element) {
            // Retirer la classe selected de tous les éléments
            document.querySelectorAll('.avatar-selector').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Ajouter la classe selected à l'élément cliqué
            element.classList.add('selected');
            
            // Cocher le radio button correspondant
            element.querySelector('input[type="radio"]').checked = true;
        }
        
        // Initialiser la sélection d'avatar au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const checkedAvatar = document.querySelector('input[name="avatar"]:checked');
            if (checkedAvatar) {
                checkedAvatar.closest('.avatar-selector').classList.add('selected');
            }
        });
        
        // Nettoyer les intervalles quand on quitte la page
        window.addEventListener('beforeunload', function() {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
        });
    </script>
</body>
</html>
