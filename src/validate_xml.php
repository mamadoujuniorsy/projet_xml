<?php
require_once 'utils.php';

function displayValidation() {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Validation XML - Plateforme Messagerie</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
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
            .validation-result {
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                font-weight: bold;
            }
            .valid {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .invalid {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .info-section {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
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
            pre {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                overflow-x: auto;
                border: 1px solid #e9ecef;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔍 Validation XML</h1>
            
            <?php
            if (isset($_POST['validate'])) {
                echo "<h2>Résultat de la Validation</h2>";
                
                try {
                    $isValid = validateXML();
                    if ($isValid) {
                        echo '<div class="validation-result valid">✅ Le fichier XML est valide selon le schéma XSD !</div>';
                    } else {
                        echo '<div class="validation-result invalid">❌ Le fichier XML n\'est pas valide selon le schéma XSD.</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="validation-result invalid">❌ Erreur lors de la validation : ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            ?>
            
            <div class="info-section">
                <h2>📋 Informations sur la Validation</h2>
                <p>Cette page permet de vérifier si le fichier XML de données respecte le schéma XSD défini.</p>
                <p><strong>Fichiers concernés :</strong></p>
                <ul>
                    <li><code>data/plateforme.xml</code> - Fichier de données</li>
                    <li><code>data/schema.xsd</code> - Schéma de validation</li>
                </ul>
            </div>
            
            <form method="post">
                <button type="submit" name="validate">🔍 Valider le XML</button>
            </form>
            
            <div class="info-section">
                <h2>📄 Contenu Actuel du XML</h2>
                <?php
                $xmlContent = file_get_contents(__DIR__ . '/../data/plateforme.xml');
                echo '<pre>' . htmlspecialchars($xmlContent) . '</pre>';
                ?>
            </div>
            
            <div class="info-section">
                <h2>📝 Structure du Schéma XSD</h2>
                <p>Le schéma définit la structure suivante :</p>
                <ul>
                    <li><strong>platforme</strong> (élément racine)
                        <ul>
                            <li><strong>utilisateurs</strong> - Liste des utilisateurs
                                <ul>
                                    <li><strong>utilisateur</strong> (id, nom, email)</li>
                                </ul>
                            </li>
                            <li><strong>groupes</strong> - Liste des groupes
                                <ul>
                                    <li><strong>groupe</strong> (id, nom, membres)</li>
                                </ul>
                            </li>
                            <li><strong>messages</strong> - Liste des messages
                                <ul>
                                    <li><strong>message</strong> (id, expéditeur, destinataire, contenu, date)</li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
            
            <?php
            // Afficher les statistiques du fichier
            $xml = loadXML();
            ?>
            <div class="info-section">
                <h2>📊 Statistiques du Fichier</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <div style="text-align: center; padding: 15px; background: white; border-radius: 5px;">
                        <h3 style="color: #007bff; margin: 0;"><?= count($xml->utilisateurs->utilisateur) ?></h3>
                        <p style="margin: 5px 0;">Utilisateurs</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: white; border-radius: 5px;">
                        <h3 style="color: #28a745; margin: 0;"><?= count($xml->messages->message) ?></h3>
                        <p style="margin: 5px 0;">Messages</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: white; border-radius: 5px;">
                        <h3 style="color: #ffc107; margin: 0;"><?= count($xml->groupes->groupe) ?></h3>
                        <p style="margin: 5px 0;">Groupes</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: white; border-radius: 5px;">
                        <h3 style="color: #dc3545; margin: 0;"><?= number_format(filesize(__DIR__ . '/../data/plateforme.xml')) ?></h3>
                        <p style="margin: 5px 0;">Octets</p>
                    </div>
                </div>
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
if (basename($_SERVER['PHP_SELF']) === 'validate_xml.php') {
    displayValidation();
}
?>
