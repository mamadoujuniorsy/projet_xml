<?php
/**
 * Fonctions utilitaires pour la plateforme de messagerie XML
 */

function loadXML() {
    $xmlFile = __DIR__ . '/../data/plateforme.xml';
    if (!file_exists($xmlFile)) {
        createInitialXML();
    }
    return simplexml_load_file($xmlFile);
}

function saveXML($xml) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    $dom->save(__DIR__ . '/../data/plateforme.xml');
}

function createInitialXML() {
    $initialXML = '<?xml version="1.0" encoding="UTF-8"?>
<platforme xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:noNamespaceSchemaLocation="schema.xsd">
  <utilisateurs>
    <utilisateur id="u1">
      <nom>Admin</nom>
      <telephone>+33 6 00 00 00 00</telephone>
      <password>$2y$10$example.hash.here</password>
      <status>En ligne</status>
      <avatar></avatar>
      <date_creation>2025-07-28T10:00:00+00:00</date_creation>
      <contacts/>
    </utilisateur>
  </utilisateurs>
  <groupes/>
  <messages/>
</platforme>';
    
    file_put_contents(__DIR__ . '/../data/plateforme.xml', $initialXML);
}

function validateXML() {
    $xmlFile = __DIR__ . '/../data/plateforme.xml';
    $xsdFile = __DIR__ . '/../data/schema.xsd';
    
    $dom = new DOMDocument();
    $dom->load($xmlFile);
    
    return $dom->schemaValidate($xsdFile);
}

function getUserById($xml, $userId) {
    foreach ($xml->utilisateurs->utilisateur as $user) {
        if ((string)$user['id'] === $userId) {
            return $user;
        }
    }
    return null;
}

function getMessagesByUser($xml, $userId) {
    $messages = [];
    foreach ($xml->messages->message as $msg) {
        if ((string)$msg->expediteur === $userId || (string)$msg->destinataire === $userId) {
            $messages[] = $msg;
        }
    }
    return $messages;
}

function createGroup($xml, $groupName, $members = []) {
    $groupId = 'g' . time();
    $group = $xml->groupes->addChild('groupe');
    $group->addAttribute('id', $groupId);
    $group->addChild('nom', $groupName);
    
    foreach ($members as $memberId) {
        $membre = $group->addChild('membre');
        $membre->addAttribute('id', $memberId);
    }
    
    return $groupId;
}

function addMemberToGroup($xml, $groupId, $userId) {
    foreach ($xml->groupes->groupe as $group) {
        if ((string)$group['id'] === $groupId) {
            $membre = $group->addChild('membre');
            $membre->addAttribute('id', $userId);
            return true;
        }
    }
    return false;
}

function getConversation($xml, $user1Id, $user2Id) {
    $conversation = [];
    foreach ($xml->messages->message as $msg) {
        $expediteur = (string)$msg->expediteur;
        $destinataire = (string)$msg->destinataire;
        
        if (($expediteur === $user1Id && $destinataire === $user2Id) ||
            ($expediteur === $user2Id && $destinataire === $user1Id)) {
            $conversation[] = $msg;
        }
    }
    
    // Trier par date
    usort($conversation, function($a, $b) {
        return strtotime($a->date) - strtotime($b->date);
    });
    
    return $conversation;
}

function formatDate($dateString) {
    return date('d/m/Y à H:i', strtotime($dateString));
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Fonction pour quitter un groupe
function leaveGroup($xml, $groupId, $userId) {
    foreach ($xml->groupes->groupe as $group) {
        if ((string)$group['id'] === $groupId) {
            // Supprimer l'utilisateur des membres du groupe
            for ($i = 0; $i < count($group->membre); $i++) {
                if ((string)$group->membre[$i]['id'] === $userId) {
                    unset($group->membre[$i]);
                    return true;
                }
            }
        }
    }
    return false;
}

// Fonction pour vérifier si un utilisateur est membre d'un groupe
function isGroupMember($xml, $groupId, $userId) {
    foreach ($xml->groupes->groupe as $group) {
        if ((string)$group['id'] === $groupId) {
            foreach ($group->membre as $membre) {
                if ((string)$membre['id'] === $userId) {
                    return true;
                }
            }
        }
    }
    return false;
}

// Fonction pour obtenir les non-membres d'un groupe (parmi les contacts de l'utilisateur)
function getNonGroupMembers($xml, $groupId, $currentUserId) {
    $nonMembers = [];
    $currentUser = getUserById($xml, $currentUserId);
    
    if ($currentUser && $currentUser->contacts) {
        foreach ($currentUser->contacts->contact as $contact) {
            $contactUser = getUserById($xml, (string)$contact['id']);
            if ($contactUser && !isGroupMember($xml, $groupId, (string)$contact['id'])) {
                $nonMembers[] = $contactUser;
            }
        }
    }
    
    return $nonMembers;
}

// Fonction pour vérifier si un utilisateur est bloqué
function isUserBlocked($xml, $currentUserId, $userId) {
    $currentUser = getUserById($xml, $currentUserId);
    if ($currentUser && isset($currentUser->blocked)) {
        foreach ($currentUser->blocked->blocked_user as $blocked) {
            if ((string)$blocked['id'] === $userId) {
                return true;
            }
        }
    }
    return false;
}

// Fonction pour bloquer un utilisateur
function blockUser($xml, $currentUserId, $userIdToBlock) {
    $currentUser = getUserById($xml, $currentUserId);
    if ($currentUser) {
        // Créer la section blocked si elle n'existe pas
        if (!isset($currentUser->blocked)) {
            $currentUser->addChild('blocked');
        }
        
        // Vérifier si déjà bloqué
        if (!isUserBlocked($xml, $currentUserId, $userIdToBlock)) {
            $blockedUser = $currentUser->blocked->addChild('blocked_user');
            $blockedUser->addAttribute('id', $userIdToBlock);
            $blockedUser->addAttribute('date_blocked', date('c'));
            return true;
        }
    }
    return false;
}

// Fonction pour débloquer un utilisateur
function unblockUser($xml, $currentUserId, $userIdToUnblock) {
    $currentUser = getUserById($xml, $currentUserId);
    if ($currentUser && isset($currentUser->blocked)) {
        for ($i = 0; $i < count($currentUser->blocked->blocked_user); $i++) {
            if ((string)$currentUser->blocked->blocked_user[$i]['id'] === $userIdToUnblock) {
                unset($currentUser->blocked->blocked_user[$i]);
                return true;
            }
        }
    }
    return false;
}

// Fonction pour vérifier si un utilisateur est dans les contacts
function isContact($xml, $currentUserId, $userId) {
    $currentUser = getUserById($xml, $currentUserId);
    if ($currentUser && $currentUser->contacts) {
        foreach ($currentUser->contacts->contact as $contact) {
            if ((string)$contact['id'] === $userId) {
                return true;
            }
        }
    }
    return false;
}
?>