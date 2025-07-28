<?php
require_once 'utils.php';

function displayGroupManagement() {
    $xml = loadXML();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestion des Groupes - Plateforme Messagerie</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 900px;
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
            input, select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-sizing: border-box;
            }
            button {
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin-right: 10px;
            }
            button:hover {
                background: #218838;
            }
            .back-btn {
                background: #6c757d;
            }
            .back-btn:hover {
                background: #545b62;
            }
            .group-item {
                background: white;
                margin: 10px 0;
                padding: 15px;
                border-radius: 5px;
                border-left: 4px solid #007bff;
            }
            .members-list {
                margin-top: 10px;
                font-style: italic;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>👥 Gestion des Groupes</h1>
            
            <?php
            // Traitement des actions
            if ($_POST['action'] ?? '') {
                switch ($_POST['action']) {
                    case 'create_group':
                        $groupName = sanitizeInput($_POST['group_name'] ?? '');
                        $selectedMembers = $_POST['members'] ?? [];
                        
                        if ($groupName) {
                            $groupId = createGroup($xml, $groupName, $selectedMembers);
                            saveXML($xml);
                            echo "<div style='color: green; margin: 10px 0;'>✅ Groupe '$groupName' créé avec succès (ID: $groupId)</div>";
                            $xml = loadXML(); // Recharger les données
                        }
                        break;
                        
                    case 'add_member':
                        $groupId = $_POST['group_id'] ?? '';
                        $userId = $_POST['user_id'] ?? '';
                        
                        if ($groupId && $userId) {
                            if (addMemberToGroup($xml, $groupId, $userId)) {
                                saveXML($xml);
                                echo "<div style='color: green; margin: 10px 0;'>✅ Membre ajouté au groupe avec succès</div>";
                                $xml = loadXML(); // Recharger les données
                            } else {
                                echo "<div style='color: red; margin: 10px 0;'>❌ Erreur lors de l'ajout du membre</div>";
                            }
                        }
                        break;
                }
            }
            ?>
            
            <!-- Formulaire de création de groupe -->
            <div class="form-section">
                <h2>➕ Créer un Nouveau Groupe</h2>
                <form method="post">
                    <input type="hidden" name="action" value="create_group">
                    <div class="form-group">
                        <label>Nom du groupe :</label>
                        <input type="text" name="group_name" required>
                    </div>
                    <div class="form-group">
                        <label>Membres initiaux (optionnel) :</label>
                        <select name="members[]" multiple size="5">
                            <?php foreach ($xml->utilisateurs->utilisateur as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= $user->nom ?> (<?= $user['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small>Maintenez Ctrl pour sélectionner plusieurs membres</small>
                    </div>
                    <button type="submit">Créer le groupe</button>
                </form>
            </div>
            
            <!-- Formulaire d'ajout de membre -->
            <div class="form-section">
                <h2>👤 Ajouter un Membre à un Groupe</h2>
                <form method="post">
                    <input type="hidden" name="action" value="add_member">
                    <div class="form-group">
                        <label>Groupe :</label>
                        <select name="group_id" required>
                            <option value="">Choisir un groupe</option>
                            <?php foreach ($xml->groupes->groupe as $group): ?>
                                <option value="<?= $group['id'] ?>"><?= $group->nom ?> (<?= $group['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Utilisateur :</label>
                        <select name="user_id" required>
                            <option value="">Choisir un utilisateur</option>
                            <?php foreach ($xml->utilisateurs->utilisateur as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= $user->nom ?> (<?= $user['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Ajouter au groupe</button>
                </form>
            </div>
            
            <!-- Liste des groupes existants -->
            <div class="form-section">
                <h2>📋 Groupes Existants</h2>
                <?php if (count($xml->groupes->groupe) > 0): ?>
                    <?php foreach ($xml->groupes->groupe as $group): ?>
                        <div class="group-item">
                            <h3><?= $group->nom ?> (ID: <?= $group['id'] ?>)</h3>
                            <div class="members-list">
                                <strong>Membres :</strong>
                                <?php if (count($group->membre) > 0): ?>
                                    <?php
                                    $memberNames = [];
                                    foreach ($group->membre as $membre) {
                                        $user = getUserById($xml, (string)$membre['id']);
                                        if ($user) {
                                            $memberNames[] = $user->nom;
                                        }
                                    }
                                    echo implode(', ', $memberNames);
                                    ?>
                                <?php else: ?>
                                    Aucun membre
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucun groupe créé pour le moment.</p>
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
if (basename($_SERVER['PHP_SELF']) === 'manage_groups.php') {
    displayGroupManagement();
}
?>
