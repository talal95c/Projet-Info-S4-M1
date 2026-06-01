# 🍍 L'Île au Fruit - Plateforme de Commande & Restauration Fraîche

Bienvenue sur le projet **L'Île au Fruit**, une application web complète de commande en ligne et de gestion de restaurant de fruits frais. Ce projet met en œuvre des concepts avancés de développement web en PHP (architecture modulaire sans framework), gestion des sessions, sécurité renforcée et intégration de services externes.

---

## 🚀 Fonctionnalités Clés

* **Gestion Multi-Rôles :** Le site s'adapte dynamiquement selon 4 rôles utilisateurs distincts :
  * **Client :** Consultation de la carte, gestion du panier, système de favoris asynchrones, suivi de commande en temps réel, écriture d'avis et notation.
  * **Restaurateur :** Suivi général, gestion et transition des commandes en cuisine ("à préparer" ──► "en préparation" ──► "prête").
  * **Livreur :** Prise en charge des commandes prêtes, géolocalisation théorique de livraison et validation finale de la livraison.
  * **Administrateur :** Gestion complète des utilisateurs, attribution de remises personnalisées, blocage/déblocage instantané de comptes avec déconnexion forcée en temps réel.
* **Sécurité & Sessions :** 
  * Hachage robuste des mots de passe en base de données avec l'algorithme de chiffrement asymétrique **Bcrypt** (`password_hash`).
  * Protection contre les attaques par force brute et par canal auxiliaire de type temporel (`hash_equals`).
  * Système de gestion des droits interdisant l'accès aux pages non autorisées (`verifier_connexion`).
* **Favoris & Filtres Asynchrones (AJAX) :** Les favoris (plats et menus) et le moteur de recherche de la carte fonctionnent en arrière-plan (Fetch API) avec des micro-animations et notifications temporaires (Toasts) sans rafraîchir la page.
* **Intégration Bancaire CY Bank :** Système complet de checkout sécurisé par signature cryptographique (MD5 combiné avec une API Key privée) pour valider l'authenticité de chaque paiement reçu.

---

## 🛠️ Stack Technique

* **Frontend :** HTML5, Vanilla CSS3 (avec support natif du **Mode Sombre** via variables globales et stockage `localStorage`), Vanilla JS (ES6+, Fetch API).
* **Backend :** PHP (sans framework tiers pour une maîtrise totale du cycle de vie des requêtes).
* **Base de données :** Fichiers plats structurés au format **JSON** dans le répertoire `data/`. Cette architecture offre un stockage léger, portable et exempt de configuration SQL.

---

## 📂 Organisation de l'Architecture

Le projet respecte une architecture modulaire inspirée du modèle MVC :

* **Racine du projet :** Contient les pages vues/contrôleurs (`index.php`, `presentation.php`, `panier.php`, `connexion.php`, etc.).
* **`includes/` :** Le noyau applicatif.
  * [session.php](file:///c:/Users/talal/Videos/Projet-Info-S4-M1/includes/session.php) : Gestion des sessions et contrôles d'accès par rôles.
  * [data.php](file:///c:/Users/talal/Videos/Projet-Info-S4-M1/includes/data.php) : Data Abstraction Layer (DAL) assurant l'interface avec les fichiers JSON.
  * [cybank.php](file:///c:/Users/talal/Videos/Projet-Info-S4-M1/includes/cybank.php) : SDK interne pour la signature et le traitement des retours CY Bank.
* **`api/` :** Points d'entrée (Endpoints API) appelés de manière asynchrone par JavaScript (AJAX) pour mettre à jour les favoris, bloquer des utilisateurs ou modifier le statut des commandes.
* **`data/` :** Base de données physique (`utilisateurs.json`, `plats.json`, `commandes.json`, `menus.json`).
* **`js/` :** Scripts JavaScript côté client.
* **`image/` :** Galerie d'images et illustrations du site.

---

## ⚙️ Installation & Lancement Local

Grâce à l'architecture sans base de données SQL (fichiers JSON), l'installation est extrêmement simplifiée et ne nécessite aucun import de schéma de base de données.

1. **Clonage / Copie du projet :**
   Déposez le dossier du projet dans le répertoire de votre serveur local (ex: `htdocs` pour XAMPP, `www` pour Wampserver).

2. **Lancement du serveur :**
   * *Option A (Recommandée) :* Utilisez le serveur de développement intégré de PHP. Ouvrez votre terminal dans le dossier du projet et lancez :
     ```bash
     php -S localhost:8000
     ```
   * *Option B :* Démarrez les modules Apache via l'interface XAMPP / WampServer.

3. **Accès au site :**
   Ouvrez votre navigateur et naviguez vers : `http://localhost:8000` (ou le sous-dossier correspondant à votre configuration Apache).

---

## 🔑 Comptes de Démonstration (Test)

Tous les comptes de démonstration ainsi que leurs identifiants en clair sont consignés dans le fichier [log.txt](file:///c:/Users/talal/Videos/Projet-Info-S4-M1/log.txt) à la racine du projet.

Voici un résumé des accès rapides :
* **Administrateur :** `admin3@ileaufruit.fr` / `Admin123!`
* **Restaurateur :** `restaurateur2@ileaufruit.fr` / `Resto123!`
* **Livreur :** `livreur1@ileaufruit.fr` / `Livreur123!`
* **Client :** `client1@ileaufruit.fr` / `Client123!`
