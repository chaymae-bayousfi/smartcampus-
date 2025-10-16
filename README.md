# 🎓 SmartCampus+

> 🌐 **Gestion intelligente de la vie étudiante**

---

## 🧭 Sommaire

- [🎯 Présentation](#-présentation)
- [🚀 Fonctionnalités principales](#-fonctionnalités-principales)
- [🏗️ Architecture du projet](#️-architecture-du-projet)
- [⚙️ Installation et démarrage](#️-installation-et-démarrage)
- [📂 Structure des dossiers](#-structure-des-dossiers)
- [🛠️ Technologies utilisées](#️-technologies-utilisées)


---

## 🎯 Présentation

**SmartCampus+** est une plateforme web moderne 🌟 destinée aux étudiants 🎓 pour **centraliser la gestion de leur vie universitaire** :
🗓️ emploi du temps, 💸 gestion budgétaire, 👥 groupes d’étude, 💬 forum, 🎉 événements, 🔔 alertes intelligentes, etc.

➡️ Une expérience **fluide, intuitive et personnalisée**, adaptée à la vie étudiante connectée.

---

## 🚀 Fonctionnalités principales

- 🧩 **Tableau de bord** : Vue globale sur vos stats (devoirs, dépenses, groupes d’étude…)
- 📅 **Emploi du temps** : Ajout, affichage et gestion des cours
- 📚 **Devoirs & examens** : Suivi des deadlines et notifications
- 💰 **Gestion budgétaire** : Dépenses, budget mensuel, conseils automatiques
- 👩‍🏫 **Groupes d’étude** : Matching intelligent, gestion des membres
- 💬 **Forum étudiant** : Discussions, entraide, bons plans
- 🎟️ **Événements** : Création, inscription, gestion des participants
- 🔐 **Authentification** : Inscription, connexion, sécurité renforcée

---

## 🏗️ Architecture du projet

📦 Organisation en plusieurs couches :

- 🎨 **Front-end** → HTML, CSS (Bootstrap + styles personnalisés), JS (interactions, AJAX…)
- 🧠 **Back-end** → PHP (API REST, gestion de session, MySQL)
- 🗃️ **Base de données** → MySQL *(voir `supabase/migrations/...sql`)*

### 📁 Structure principale

```bash
smartcampus-/
├── api/                   # Endpoints PHP (stats, notifications, etc.)
├── assets/
│   ├── css/               # Styles personnalisés Bootstrap
│   └── js/                # Scripts JS (interactions, notifications…)
├── config/
│   └── database.php       # Connexion MySQL
├── includes/
│   ├── header.php         # Barre de navigation
│   └── footer.php         # Pied de page
├── supabase/
│   └── migrations/        # Schéma de la base de données
├── index.php / index.html # Page d’accueil
├── dashboard.php          # Tableau de bord
├── budget.php             # Gestion budgétaire
├── schedule.php           # Emploi du temps
├── study-groups.php       # Groupes d’étude
├── login.php / register.php / logout.php  # Authentification
└── style.css              # Styles additionnels
```
⚙️ Installation et démarrage
🧩 Prérequis
PHP ≥ 7.4

MySQL 

Serveur Web (Apache)


🔽 1. Cloner le dépôt
bash
```
Copy code
git clone https://github.com/chaymae-bayousfi/smartcampus-.git
cd smartcampus-
```
🗃️ 2. Configurer la base de données

Créez une base smartcampus dans MySQL

🚀 3. Lancer le serveur web
Placez le projet dans le répertoire web (htdocs ou www), puis accédez à :

Copy code
bash
```
http://localhost/smartcampus-/index.php
```
