<?php
session_start();
require_once 'src/utils.php';

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $nom = trim($_POST['nom'] ?? '');
                $telephone = trim($_POST['telephone'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if ($nom && $telephone && $password) {
                    $xml = loadXML();
                    
                    // Vérifier si le téléphone existe déjà
                    $phoneExists = false;
                    foreach ($xml->utilisateurs->utilisateur as $user) {
                        if ((string)$user->telephone === $telephone) {
                            $phoneExists = true;
                            break;
                        }
                    }
                    
                    if ($phoneExists) {
                        $error = 'Ce numéro de téléphone est déjà utilisé !';
                    } else {
                        $userId = 'u' . time();
                        $newUser = $xml->utilisateurs->addChild('utilisateur');
                        $newUser->addAttribute('id', $userId);
                        $newUser->addChild('nom', htmlspecialchars($nom));
                        $newUser->addChild('telephone', htmlspecialchars($telephone));
                        $newUser->addChild('password', password_hash($password, PASSWORD_DEFAULT));
                        $newUser->addChild('status', 'En ligne');
                        $newUser->addChild('avatar', 'default.png');
                        $newUser->addChild('date_creation', date('c'));
                        
                        // Créer la section contacts pour ce nouvel utilisateur
                        $contacts = $newUser->addChild('contacts');
                        
                        saveXML($xml);
                        $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
                    }
                } else {
                    $error = 'Tous les champs sont requis !';
                }
                break;
                
            case 'login':
                $telephone = trim($_POST['telephone'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if ($telephone && $password) {
                    $xml = loadXML();
                    $userFound = false;
                    
                    foreach ($xml->utilisateurs->utilisateur as $user) {
                        if ((string)$user->telephone === $telephone) {
                            if (password_verify($password, (string)$user->password)) {
                                $_SESSION['user_id'] = (string)$user['id'];
                                $_SESSION['user_name'] = (string)$user->nom;
                                $_SESSION['user_phone'] = (string)$user->telephone;
                                header('Location: dashboard.php');
                                exit;
                            } else {
                                $error = 'Mot de passe incorrect !';
                            }
                            $userFound = true;
                            break;
                        }
                    }
                    
                    if (!$userFound) {
                        $error = 'Aucun compte trouvé avec ce numéro !';
                    }
                } else {
                    $error = 'Numéro de téléphone et mot de passe requis !';
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP Chat - Connexion</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: #25D366;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .logo p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 2rem;
            border-radius: 10px;
            background: #f5f5f5;
            padding: 4px;
        }
        
        .tab {
            flex: 1;
            padding: 0.75rem;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab.active {
            background: #25D366;
            color: white;
        }
        
        .tab:not(.active) {
            color: #666;
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #25D366;
        }
        
        button {
            width: 100%;
            background: #25D366;
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #128C7E;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .alert.error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert.success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .emoji {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="emoji">💬</div>
            <h1>ESP Chat</h1>
            <p>Connectez-vous avec vos proches</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert error">❌ <?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success">✅ <?= $success ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('login')">Se connecter</div>
            <div class="tab" onclick="showTab('register')">S'inscrire</div>
        </div>
        
        <!-- Formulaire de connexion -->
        <div class="form-section active" id="login-form">
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>� Numéro de téléphone</label>
                    <input type="tel" name="telephone" required placeholder="+33 6 12 34 56 78">
                </div>
                <div class="form-group">
                    <label>🔒 Mot de passe</label>
                    <input type="password" name="password" required placeholder="Votre mot de passe">
                </div>
                <button type="submit">Se connecter</button>
            </form>
        </div>
        
        <!-- Formulaire d'inscription -->
        <div class="form-section" id="register-form">
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>👤 Nom complet</label>
                    <input type="text" name="nom" required placeholder="Votre nom complet">
                </div>
                <div class="form-group">
                    <label>� Numéro de téléphone</label>
                    <input type="tel" name="telephone" required placeholder="+33 6 12 34 56 78">
                </div>
                <div class="form-group">
                    <label>🔒 Mot de passe</label>
                    <input type="password" name="password" required placeholder="Choisissez un mot de passe">
                </div>
                <button type="submit">Créer mon compte</button>
            </form>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Masquer toutes les sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Désactiver tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Activer la section et l'onglet sélectionnés
            document.getElementById(tabName + '-form').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
