# � WhatsApp Clone - Plateforme de Messagerie XML

Une application de messagerie moderne inspirée de WhatsApp, utilisant PHP et XML pour la gestion des données.

## 🎯 Fonctionnalités Principales

### � **Authentification**
- **Inscription** : Création de compte avec nom, email, téléphone et mot de passe
- **Connexion** : Système de login sécurisé avec sessions PHP
- **Sécurité** : Mots de passe hashés avec `password_hash()`

### 👥 **Gestion des Contacts**
- **Ajout de contacts** : Recherche et ajout par email
- **Liste des contacts** : Interface claire avec avatars
- **Statuts** : Affichage des statuts en ligne

### 💬 **Messagerie en Temps Réel**
- **Interface WhatsApp** : Design identique à WhatsApp
- **Messages en temps réel** : Auto-refresh toutes les 3 secondes
- **Conversations privées** : Chat 1-to-1 avec historique
- **Messages non lus** : Compteur de messages non lus
- **Aperçu des conversations** : Dernier message visible dans la liste

## 🚀 **Comment Utiliser**

### 1. **Démarrer le Serveur**
```bash
cd projet-xml-whatsapp
php -S localhost:8000
```

### 2. **Accéder à l'Application**
Ouvrez `http://localhost:8000` dans votre navigateur

### 3. **Créer un Compte**
1. Cliquez sur l'onglet "S'inscrire"
2. Remplissez vos informations (nom, email, téléphone, mot de passe)
3. Cliquez sur "Créer mon compte"

### 4. **Se Connecter**
1. Utilisez votre email et mot de passe
2. Cliquez sur "Se connecter"

### 5. **Ajouter des Contacts**
1. Dans la sidebar, utilisez le champ "Email du contact à ajouter"
2. Entrez l'email d'un utilisateur existant
3. Cliquez sur "➕ Ajouter contact"

### 6. **Commencer à Chatter**
1. Cliquez sur un contact dans la liste
2. Tapez votre message dans le champ en bas
3. Appuyez sur "Entrée" ou cliquez sur "➤"

## 🏗️ **Structure du Projet**

```
projet-xml-whatsapp/
├── index.php              # Redirection vers login.php
├── login.php              # Page d'authentification (connexion/inscription)
├── dashboard.php          # Interface principale (comme WhatsApp)
├── logout.php             # Script de déconnexion
├── README.md              # Documentation
├── data/
│   ├── plateforme.xml     # Base de données XML
│   └── schema.xsd         # Schéma de validation XSD
└── src/
    ├── utils.php          # Fonctions utilitaires
    ├── conversations.php  # Interface de conversation (legacy)
    ├── manage_groups.php  # Gestion des groupes (legacy)
    └── validate_xml.php   # Validation XML (legacy)
```

## � **Interface Utilisateur**

### **Page de Connexion**
- Design moderne avec dégradé vert WhatsApp
- Onglets pour basculer entre connexion et inscription
- Validation en temps réel des formulaires
- Messages d'erreur et de succès

### **Dashboard Principal**
- **Sidebar gauche** : Liste des contacts avec aperçu des conversations
- **Zone de chat** : Interface de conversation en temps réel
- **Header** : Informations de l'utilisateur connecté
- **Responsive** : S'adapte aux différentes tailles d'écran

### **Fonctionnalités Visuelles**
- **Avatars** : Initiales colorées pour chaque utilisateur
- **Messages** : Bulles distinctes pour envoyés/reçus
- **Timestamps** : Heure d'envoi de chaque message
- **Badges** : Compteur de messages non lus
- **Statuts** : Indicateurs "En ligne"

## 🗃️ **Structure des Données XML Mise à Jour**

### **Utilisateurs avec Authentification**
```xml
<utilisateur id="u1732800123">
    <nom>John Doe</nom>
    <email>john@example.com</email>
    <telephone>+33 6 12 34 56 78</telephone>
    <password>$2y$10$hashed_password</password>
    <status>En ligne</status>
    <avatar>default.png</avatar>
    <date_creation>2025-07-28T10:30:00+00:00</date_creation>
    <contacts>
        <contact id="u1732800456" date_ajout="2025-07-28T11:00:00+00:00"/>
    </contacts>
</utilisateur>
```

### **Messages avec Statut de Lecture**
```xml
<message id="m1732800789">
    <expediteur>u1732800123</expediteur>
    <destinataire>u1732800456</destinataire>
    <contenu>Salut ! Comment ça va ?</contenu>
    <date>2025-07-28T12:00:00+00:00</date>
    <lu>false</lu>
</message>
```

## � **Fonctionnalités Techniques**

### **Sessions PHP**
- Gestion sécurisée des sessions utilisateur
- Protection contre l'accès non autorisé
- Déconnexion automatique

### **Sécurité**
- **Hachage des mots de passe** : `password_hash()` et `password_verify()`
- **Protection XSS** : `htmlspecialchars()` sur toutes les entrées
- **Validation des données** : Vérification côté serveur
- **Sessions sécurisées** : Gestion appropriée des sessions PHP

### **Base de Données XML**
- **Structure hiérarchique** : Organisation logique des données
- **Validation XSD** : Respect du schéma défini
- **Sauvegarde formatée** : XML indenté et lisible
- **Gestion des relations** : Contacts et conversations liés

## 🎨 **Personnalisation**

### **Couleurs WhatsApp**
- **Vert principal** : `#25D366`
- **Vert foncé** : `#075e54`
- **Vert secondaire** : `#128C7E`
- **Bulles messages** : `#dcf8c6` (envoyés), blanc (reçus)

### **Ajout de Fonctionnalités**
1. **Messages de groupe** : Étendre la structure XML
2. **Upload d'images** : Ajouter support des fichiers
3. **Notifications** : Intégrer des notifications push
4. **Thèmes** : Permettre la personnalisation des couleurs

## 🔄 **Workflow d'Utilisation**

1. **Premier utilisateur** :
   - S'inscrit via le formulaire
   - Se connecte automatiquement

2. **Deuxième utilisateur** :
   - S'inscrit avec un email différent
   - Se connecte à son compte

3. **Ajout mutuel** :
   - Chaque utilisateur ajoute l'autre par email
   - Les contacts apparaissent dans la sidebar

4. **Conversation** :
   - Clic sur un contact pour ouvrir le chat
   - Échange de messages en temps réel
   - Messages marqués comme lus automatiquement

## 🐛 **Résolution de Problèmes**

### **Erreurs Communes**
- **"Session not found"** : Effacez les cookies et reconnectez-vous
- **"Contact not found"** : Vérifiez que l'email existe et est correct
- **"XML validation failed"** : Le fichier XML est corrompu, restaurez la sauvegarde

### **Réinitialisation**
Pour recommencer à zéro :
1. Supprimez `data/plateforme.xml`
2. Le fichier sera recréé automatiquement au prochain accès

## 🚀 **Améliorations Futures**

- [ ] **Notifications push** en temps réel
- [ ] **Messages vocaux** et images
- [ ] **Statuts** temporaires (stories)
- [ ] **Groupes de discussion**
- [ ] **Chiffrement end-to-end**
- [ ] **Application mobile** (React Native/Flutter)
- [ ] **API REST** pour intégrations
- [ ] **Base de données** MySQL/PostgreSQL

---

**🎉 Votre clone WhatsApp est prêt ! Créez votre compte et commencez à chatter !**
