# SAE Projet Tuteuré — Déploiement d'une application / LDAP

> BUT Informatique 3A — Parcours DACS — Février 2026

---

## Table des matières

1. [Synthèse sur le protocole LDAP](#1-synthèse-sur-le-protocole-ldap)
2. [Étape 1 — Raccordement au serveur de test](#2-étape-1--raccordement-au-serveur-de-test-forumsys)
3. [Étape 2 — Raccordement au LDAP de l'Université de Lorraine](#3-étape-2--raccordement-au-ldap-de-luniversité-de-lorraine)
4. [Étape 3 — Interface web PHP](#4-étape-3--interface-web-php)
5. [Instructions de déploiement et d'utilisation](#5-instructions-de-déploiement-et-dutilisation)

---

## 1. Synthèse sur le protocole LDAP

### 1.1 LDAP, c'est quoi ?

**LDAP** (*Lightweight Directory Access Protocol*) est un protocole standardisé (RFC 4511) qui sert à interroger et modifier un **annuaire**. Un annuaire, dans ce contexte, c'est une sorte de base de données, mais pensée pour être **lue très souvent et modifiée rarement**. On y stocke typiquement des comptes utilisateurs, des groupes, des coordonnées — bref, tout ce qui relève de l'organisation d'une structure.

Pour bien comprendre la différence avec une BDD classique :

```
┌───────────────────────┬─────────────────────────────────┐
│    Base SQL            │    Annuaire LDAP               │
├───────────────────────┼─────────────────────────────────┤
│ Tables / lignes        │ Arbre d'entrées (DIT)          │
│ Lecture + Écriture     │ Optimisé lecture                │
│ Requêtes SQL           │ Filtres LDAP                   │
│ Clé primaire           │ DN (Distinguished Name)        │
│ Schéma rigide          │ Schéma flexible (objectClass)  │
└───────────────────────┴─────────────────────────────────┘
```

En gros, si on a besoin d'un truc qu'on lit 1000 fois pour 1 écriture, LDAP est fait pour ça.

### 1.2 Les concepts à connaître

Avant de toucher au code, il faut avoir en tête quelques notions :

- **Entrée** (*Entry*) — C'est un élément de l'annuaire : un utilisateur, un groupe, une unité organisationnelle. Chaque entrée a un identifiant unique.

- **Attribut** — Une propriété d'une entrée. Par exemple `cn` (Common Name), `mail`, `uid`, `telephoneNumber`. Un attribut peut avoir plusieurs valeurs.

- **DN** (*Distinguished Name*) — L'adresse unique d'une entrée dans l'arbre. Ça ressemble à ça : `uid=einstein,dc=example,dc=com`. On le lit de gauche (le plus précis) à droite (la racine).

- **RDN** (*Relative DN*) — La partie la plus à gauche du DN. Dans `uid=einstein,dc=example,dc=com`, le RDN c'est `uid=einstein`.

- **Base DN** — Le point de départ quand on fait une recherche. Par exemple `dc=example,dc=com` pour chercher dans tout l'annuaire.

- **Bind** — L'opération d'authentification. On envoie un DN + un mot de passe au serveur pour prouver qui on est.

- **objectClass** — Ça définit le "type" d'une entrée et quels attributs elle peut/doit contenir. Exemples : `inetOrgPerson`, `organizationalUnit`, `groupOfNames`.

### 1.3 La structure en arbre : le DIT

L'annuaire LDAP est organisé en arbre, qu'on appelle le **DIT** (*Directory Information Tree*). Chaque noeud est une entrée identifiée par son DN.

Voici à quoi ressemble le DIT du serveur de test forumsys :

```
                        dc=com
                          │
                     dc=example
                      ┌───┴────────────────────┐
                      │                         │
               ou=scientists               ou=mathematicians
              ┌───┬───┴───┬───┐                 │
              │   │       │   │            uid=euclid
        uid=  uid=  uid=  uid=
       einstein tesla curie newton
```

Pour comprendre comment on lit un DN :

```
uid=einstein , dc=example , dc=com
     │              │           │
     │              │           └── Domaine racine
     │              └────────────── Sous-domaine
     └───────────────────────────── Entrée utilisateur (RDN)
```

C'est comme un chemin dans l'arbre, mais à l'envers : on part du noeud et on remonte jusqu'à la racine, chaque composant séparé par une virgule.

### 1.4 Les opérations LDAP

Le protocole définit un jeu d'opérations bien précis :

```
┌──────────┬───────────────────────────────────────────────────────┐
│ Bind     │ On s'authentifie auprès du serveur (DN + mdp).       │
│          │ C'est toujours la première chose à faire.            │
├──────────┼───────────────────────────────────────────────────────┤
│ Search   │ On cherche des entrées dans l'annuaire.              │
│          │ On précise : Base DN, scope, filtre, attributs.      │
│          │ Ex de filtre : (&(objectClass=person)(uid=tesla))    │
├──────────┼───────────────────────────────────────────────────────┤
│ Compare  │ On vérifie si une entrée a un attribut avec une      │
│          │ valeur donnée. Retourne juste vrai/faux.             │
├──────────┼───────────────────────────────────────────────────────┤
│ Add      │ On ajoute une nouvelle entrée.                       │
├──────────┼───────────────────────────────────────────────────────┤
│ Modify   │ On modifie les attributs d'une entrée existante.     │
│          │ Sous-opérations : add, delete, replace.              │
├──────────┼───────────────────────────────────────────────────────┤
│ Delete   │ On supprime une entrée.                              │
├──────────┼───────────────────────────────────────────────────────┤
│ Unbind   │ On ferme la connexion.                               │
└──────────┴───────────────────────────────────────────────────────┘
```

Quand on fait un Search, on choisit un **scope** qui détermine la profondeur de la recherche :

```
           dc=example,dc=com          <- base (scope: base)
            ┌──────┴──────┐
         ou=people     ou=groups       <- 1 niveau (scope: one)
          ┌──┴──┐
     uid=alice uid=bob                 <- sous-arbre (scope: sub)
```

- **base** — on regarde uniquement l'entrée du Base DN
- **one** — on regarde ses enfants directs (un seul niveau)
- **sub** — on parcourt tout le sous-arbre (c'est le plus courant)

### 1.5 LDAP vs LDAPS

C'est un point important : LDAP en lui-même ne chiffre rien. On a trois modes de communication :

```
┌──────────────────────────────────────────────────────────────┐
│                                                              │
│   CLIENT                          SERVEUR LDAP               │
│                                                              │
│   ── LDAP (port 389) ──────────►                             │
│      Tout passe en clair.                                    │
│      Le mot de passe est lisible sur le réseau.              │
│                                                              │
│   ── LDAPS (port 636) ─────────►                             │
│      Chiffré en TLS dès la connexion.                        │
│      Le certificat du serveur est vérifié.                   │
│                                                              │
│   ── StartTLS (port 389) ──────►                             │
│      On commence en clair, puis on négocie                   │
│      un passage en TLS.                                      │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

| | LDAP | LDAPS | StartTLS |
|---|---|---|---|
| **Port** | 389 | 636 | 389 |
| **Chiffrement** | Aucun | TLS dès la connexion | TLS après négociation |
| **Sécurité** | Faible | Forte | Forte |
| **Usage** | Tests, réseau isolé | **Production** | Alternative à LDAPS |

En production, on utilise **LDAPS (port 636)** — c'est d'ailleurs ce que fait l'Université de Lorraine.

### 1.6 À quoi ça sert concrètement en entreprise ?

Dans une entreprise ou une université, on retrouve en général un annuaire LDAP central (souvent Active Directory chez Microsoft, ou OpenLDAP en open-source) auquel se raccordent tous les services :

```
┌────────────────────────────────────────────────────────────────┐
│                                                                │
│                    ANNUAIRE LDAP CENTRAL                       │
│              (Active Directory, OpenLDAP, etc.)                │
│                                                                │
│   Contient : utilisateurs, groupes, rôles, structures         │
│                                                                │
└────────┬──────────┬──────────┬──────────┬─────────────────────┘
         │          │          │          │
         ▼          ▼          ▼          ▼
    ┌─────────┐┌─────────┐┌─────────┐┌──────────┐
    │  Appli  ││  VPN /  ││  Mail   ││  Intranet│
    │  Web    ││  WiFi   ││ Exchange││  Portail │
    └─────────┘└─────────┘└─────────┘└──────────┘
```

Les cas d'usage les plus courants :

1. **Authentification centralisée** — On a un seul login/mot de passe pour tous les services. Quand une appli web veut vérifier un utilisateur, elle fait un bind LDAP avec ses credentials.

2. **Annuaire d'entreprise** — On peut consulter les coordonnées des collègues (nom, mail, téléphone, bureau, service).

3. **Gestion des droits** — Via l'attribut `memberOf`, on sait à quels groupes appartient quelqu'un. Ça permet de gérer les accès (VPN, applis, rôles admin, etc.).

4. **Provisioning** — Quand on ajoute quelqu'un dans l'annuaire, tous ses accès peuvent se créer automatiquement.

---

## 2. Étape 1 — Raccordement au serveur de test (forumsys)

### 2.1 Test en ligne de commande

Avant d'écrire du code, on commence par vérifier que la connexion fonctionne avec `ldapsearch`, l'outil en ligne de commande fourni par OpenLDAP.

**Infos du serveur de test :**

| Paramètre | Valeur |
|---|---|
| Serveur | `ldap.forumsys.com` |
| Port | `389` (LDAP, pas chiffré) |
| Bind DN | `cn=read-only-admin,dc=example,dc=com` |
| Mot de passe | `password` |
| Base DN | `dc=example,dc=com` |

On peut aussi s'authentifier avec des comptes utilisateurs comme `uid=einstein,dc=example,dc=com` ou `uid=tesla,dc=example,dc=com`. Tous les mots de passe sont `password`.

**Lister toutes les entrées de l'annuaire :**

```bash
ldapsearch -x -H ldap://ldap.forumsys.com:389 \
  -D "cn=read-only-admin,dc=example,dc=com" \
  -w password \
  -b "dc=example,dc=com" \
  "(objectClass=*)"
```

Pour comprendre les options :

| Option | Ce que ça fait |
|---|---|
| `-x` | Authentification simple (pas SASL) |
| `-H ldap://...` | URL du serveur |
| `-D "..."` | Le DN avec lequel on se bind |
| `-w password` | Le mot de passe pour le bind |
| `-b "..."` | Le Base DN (point de départ de la recherche) |
| `"(objectClass=*)"` | Le filtre — ici on prend tout |

**Chercher uniquement les utilisateurs :**

```bash
ldapsearch -x -H ldap://ldap.forumsys.com:389 \
  -D "cn=read-only-admin,dc=example,dc=com" \
  -w password \
  -b "dc=example,dc=com" \
  "(objectClass=person)" uid cn mail
```

**Tester un bind avec un compte utilisateur :**

```bash
ldapsearch -x -H ldap://ldap.forumsys.com:389 \
  -D "uid=einstein,dc=example,dc=com" \
  -w password \
  -b "dc=example,dc=com" \
  "(uid=einstein)"
```

> Le serveur forumsys est public et gratuit, il arrive qu'il soit temporairement down. Si on obtient `Can't contact LDAP server`, il suffit de réessayer plus tard.

### 2.2 Script Ruby

Le fichier `ldap_forumsys.rb` fait trois choses :

1. Un **bind** avec le compte admin en lecture seule
2. Une **recherche** de toutes les entrées, puis un filtrage sur les utilisateurs (`objectClass=person`)
3. Un **test d'authentification** avec un compte utilisateur (Einstein)

Voici le flux du script :

```
┌─────────────┐     1. Bind (admin)      ┌──────────────────┐
│             │ ──────────────────────►   │                  │
│   Script    │     2. Search (users)     │   ldap.forumsys  │
│   Ruby      │ ──────────────────────►   │   .com:389       │
│             │     3. Bind (einstein)    │                  │
│             │ ──────────────────────►   │                  │
└─────────────┘  ◄────────────────────    └──────────────────┘
                    Résultats / OK
```

Pour lancer :

```bash
ruby ldap_forumsys.rb
```

### 2.3 La gem net-ldap

| | |
|---|---|
| **Gem** | [net-ldap](https://rubygems.org/gems/net-ldap) |
| **Version** | 0.20.0 |
| **Licence** | MIT |
| **Dépôt** | https://github.com/ruby-ldap/ruby-net-ldap |

On a choisi `net-ldap` parce que c'est une gem Ruby pure — pas besoin de compiler quoi que ce soit ni d'installer des libs système (contrairement à `ruby-ldap` qui dépend de libldap en C). Elle gère LDAP, LDAPS et StartTLS, et son API est simple à prendre en main.

Exemple minimal pour se connecter et chercher un utilisateur :

```ruby
require "net/ldap"

ldap = Net::LDAP.new(
  host: "ldap.forumsys.com",
  port: 389,
  auth: { method: :simple, username: "cn=read-only-admin,dc=example,dc=com", password: "password" }
)

ldap.bind  # => true si le bind a marché

ldap.search(base: "dc=example,dc=com", filter: "(uid=tesla)") do |entry|
  puts entry.dn
  puts entry[:mail]
end
```

---

## 3. Étape 2 — Raccordement au LDAP de l'Université de Lorraine

### 3.1 Ce qui change par rapport au serveur de test

Quand on passe du serveur de test au vrai LDAP de l'UL, pas mal de choses changent. Voici un récap :

```
┌───────────────────────┬──────────────────────┬───────────────────────────┐
│                       │  Serveur de test     │  LDAP Univ. de Lorraine   │
│                       │  (forumsys)          │                           │
├───────────────────────┼──────────────────────┼───────────────────────────┤
│ Protocole             │ LDAP (clair)         │ LDAPS (chiffré TLS)       │
│ Port                  │ 389                  │ 636                       │
│ Serveurs              │ 1 seul               │ 2 (haute dispo)           │
│ Type d'annuaire       │ OpenLDAP             │ Active Directory          │
│ Format du Bind DN     │ DN complet           │ login@univ-lorraine.fr    │
│                       │ cn=...,dc=...,dc=... │ (format UPN)              │
│ Base DN               │ dc=example,dc=com    │ OU=Personnels,OU=_Utili.. │
│ Identifiant user      │ uid                  │ sAMAccountName            │
│ Groupes               │ Attribut simple      │ memberOf (multi-valué)    │
│ Accès                 │ Public, lecture seule │ Authentifié, réseau UL    │
│ Mot de passe          │ "password" (public)  │ Credentials personnels    │
└───────────────────────┴──────────────────────┴───────────────────────────┘
```

Quelques points qu'on a relevés :

- **Active Directory vs OpenLDAP** — L'UL tourne sur Active Directory (Microsoft), pas OpenLDAP. Du coup, les noms d'attributs ne sont pas les mêmes : on a `sAMAccountName` au lieu de `uid`, et `memberOf` pour les groupes.

- **Format UPN pour le bind** — On ne se bind pas avec un DN complet comme sur forumsys. Ici on utilise le format `login@domaine` (c'est ce qu'on appelle le User Principal Name). C'est une spécificité d'Active Directory. Attention : pour les étudiants c'est `login@etu.univ-lorraine.fr`, pas `login@univ-lorraine.fr` (qui est réservé aux personnels). On a perdu du temps là-dessus avant de s'en rendre compte.

- **LDAPS obligatoire** — Pas de LDAP en clair ici, tout passe en TLS sur le port 636. Côté Ruby, ça se traduit par `encryption: { method: :simple_tls }`.

- **Deux serveurs (failover)** — L'UL fournit deux serveurs (`montet-dc1` et `montet-dc2`). On a implémenté un failover dans le script : si le premier ne répond pas, on essaie le second.

- **Rattachement structurel** — Pour savoir à quelle structure quelqu'un est rattaché, on regarde l'attribut `memberOf` et on cherche certains motifs :
  - `GGA_STP_FHB–` → IUTNC
  - `GGA_STP_FHBAB` → Département Informatique

- **Base DN étudiants vs personnels** — Le sujet donne le Base DN des personnels (`OU=Personnels,OU=_Utilisateurs,...`), mais les comptes étudiants n'y sont pas. On a dû élargir la recherche à `OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr` pour trouver nos comptes. Le script essaie d'abord cette branche large, puis la branche personnels si rien n'est trouvé.

### 3.2 Difficultés rencontrées

En pratique, on a galéré sur plusieurs points avant d'arriver à un script fonctionnel :

1. **Port 636 bloqué par le réseau** — Le LDAPS n'est accessible que depuis certains VLANs de l'IUT. En WiFi (eduroam) ou en partage de connexion, le port est filtré. Il a fallu demander l'ouverture du port pour le VLAN de notre salle de TP. On peut vérifier l'accès avec `nc -zv montet-dc1.ad.univ-lorraine.fr 636 -w 5`.

2. **DNS interne** — Les noms `montet-dc1.ad.univ-lorraine.fr` et `montet-dc2.ad.univ-lorraine.fr` ne sont résolvables que par le DNS de l'UL. Depuis un réseau externe (4G, VPN type Tailscale), le nslookup échoue. Il faut être sur le réseau de l'UL avec le bon serveur DNS.

3. **Domaine de bind étudiant** — Le sujet indique `login@univ-lorraine.fr` pour le bind, mais ça retourne `invalid credentials` pour un compte étudiant. Le bon format est `login@etu.univ-lorraine.fr`.

4. **Base DN des étudiants** — La branche `OU=Personnels` ne contient pas les comptes étudiants. On a dû chercher plus haut dans l'arbre (`OU=_Utilisateurs`) pour trouver nos entrées.

### 3.3 Le script PoC

Le fichier `ldap_ul.rb` est un proof of concept de connexion au LDAP de l'UL. Voici comment il fonctionne :

```
                                        ┌──────────────────────┐
                          ┌── essai 1 ──► montet-dc1 (LDAPS)   │
┌──────────┐              │             └──────────────────────┘
│          │   Bind TLS   │                      │
│  Script  │──────────────┤           succès ?   │
│  Ruby    │              │           oui ───► Search personnels
│          │              │           non ──┐    │
└──────────┘              │                 │    ▼
                          │                 │  Affiche : nom, mail,
                          └── essai 2 ──┐   │  rattachement
                                        ▼   │
                          ┌──────────────────────┐
                          │ montet-dc2 (LDAPS)   │
                          └──────────────────────┘
```

Concrètement, le script :

1. Récupère le login et le mot de passe depuis les variables d'environnement
2. Tente un bind LDAPS sur `montet-dc1`, et bascule sur `montet-dc2` si ça échoue
3. Recherche l'utilisateur par `sAMAccountName` (d'abord dans la branche étudiants, puis personnels)
4. Affiche son DN, son nom, son mail, et son rattachement

### 3.4 Gestion du mot de passe

Le mot de passe UL est personnel — on ne doit **jamais** le retrouver dans le code source ou dans l'historique Git.

On a choisi d'utiliser des **variables d'environnement** :

```bash
# Soit on exporte les variables
export LDAP_LOGIN="monlogin"
export LDAP_PASSWORD="monmotdepasse"
ruby ldap_ul.rb

# Soit on les passe en une ligne
# (avec un espace devant pour éviter que ça rentre dans l'historique du shell)
 LDAP_LOGIN=monlogin LDAP_PASSWORD=secret ruby ldap_ul.rb
```

Ce qu'on a mis en place côté sécurité :
- Le `.gitignore` exclut `.env` et `config/local.yml`
- Le script refuse de tourner si les variables ne sont pas définies
- Aucun mot de passe n'est en dur dans le code

---

## 4. Étape 3 — Interface web PHP

### 4.1 Présentation

Pour l'étape 3, on a fait une interface web en PHP pour interroger l'annuaire LDAP via le navigateur. On a d'abord fait un premier prototype rapide (`index.php` + `auth.php` à la racine) pour valider que le raccordement LDAP fonctionnait bien en PHP. Ensuite on a fait une version plus propre dans `web-interface/` avec une config séparée, un mode test/production et du CSS.

La version à la racine est juste le premier jet — la version finale c'est `web-interface/`.

### 4.2 Le premier prototype (racine)

Les fichiers `index.php` et `auth.php` à la racine, c'est la toute première version qu'on a faite. C'est du PHP brut, pas de CSS, la config est en dur. Ça servait surtout à vérifier que `ldap_bind()` et `ldap_search()` fonctionnaient bien avec le serveur de l'UL avant de se lancer dans quelque chose de plus structuré.

### 4.3 La version finale (web-interface/)

C'est la version qu'on a gardée. La grosse différence c'est qu'on a séparé la config dans un fichier à part (`config.php`) et ajouté un **mode test/production**. On peut basculer entre le serveur de test forumsys et le vrai LDAP de l'UL en changeant une seule ligne :

```php
define('LDAP_MODE', 'test');  // ou 'ul' pour la prod
```

Le tout tient en 3 fichiers :
- `config.php` — la config des deux modes (serveurs, ports, Base DN, attributs...)
- `index.php` — tout en un : formulaire + traitement + affichage
- `style.css` — la mise en forme

```
┌──────────────┐    POST login/mdp    ┌──────────────┐    Bind LDAP(S)   ┌─────────────┐
│              │ ──────────────────►   │              │ ──────────────►   │  Serveur    │
│  Formulaire  │                      │  index.php   │                   │  LDAP       │
│  (login/mdp) │                      │  (traitement)│  ◄──────────────   │             │
│              │                      │              │    Résultats       └─────────────┘
└──────────────┘                      └──────────────┘
                                            │
                                            ▼
                                      Affiche : nom, login,
                                      mail, DN, rattachement
```

En mode test, on peut se connecter avec `einstein` / `password` (ou tesla, newton, curie...). En mode `ul`, on utilise son login UL depuis le réseau de l'IUT.

Pour lancer :
```bash
cd web-interface/
php -S localhost:8000
```

### 4.4 Sécurité

Quelques trucs qu'on a gérés :
- Le login est échappé avec `ldap_escape()` avant d'être mis dans le filtre LDAP (pour éviter une injection LDAP)
- Les valeurs affichées passent par `htmlspecialchars()` contre le XSS
- Le mot de passe sert uniquement au bind, il n'est jamais stocké ni affiché

---

## 5. Instructions de déploiement et d'utilisation

### Prérequis

- Linux (obligatoire pour ce projet)
- Ruby >= 3.0
- PHP >= 8.0 avec l'extension `ldap`
- OpenLDAP (`openldap` sur Arch, `ldap-utils` sur Debian/Ubuntu) pour `ldapsearch`
- Un accès réseau au serveur ciblé

### Installation

```bash
# Cloner le dépôt
git clone git@github.com:ColettesCorvette/pre-sae-ccd-v2.git
cd pre-sae-ccd-v2
```

Installer les paquets système selon la distro :

```bash
# Arch / Manjaro
sudo pacman -S openldap ruby php
# Sur Arch, l'extension LDAP est dans le paquet php mais désactivée.
# Décommenter "extension=ldap" dans /etc/php/php.ini

# Debian / Ubuntu
sudo apt install ldap-utils ruby php php-ldap

# Fedora
sudo dnf install openldap-clients ruby php php-ldap
```

Puis installer la gem LDAP :

```bash
gem install net-ldap
```

### Utilisation

**Étape 1 — Serveur de test forumsys :**

```bash
# Test en ligne de commande
ldapsearch -x -H ldap://ldap.forumsys.com:389 \
  -D "cn=read-only-admin,dc=example,dc=com" \
  -w password \
  -b "dc=example,dc=com" \
  "(objectClass=person)"

# Script Ruby
ruby ldap_forumsys.rb
```

**Étape 2 — LDAP Université de Lorraine (depuis le réseau UL ou VPN) :**

```bash
# Configurer ses credentials (ne JAMAIS les commiter)
export LDAP_LOGIN="prenom.nom"
export LDAP_PASSWORD="votre_mot_de_passe"

# Lancer le script
ruby ldap_ul.rb

# Rechercher un autre utilisateur
ruby ldap_ul.rb autre.login
```

**Étape 3 — Interface web PHP :**

```bash
cd web-interface/

# Mode test (par défaut, marche partout)
php -S localhost:8000
# -> login : einstein, mdp : password

# Mode UL (modifier LDAP_MODE dans config.php, depuis le réseau IUT)
php -S localhost:8000
```

### Structure du dépôt

```
pre-sae-4/
├── README.md            # Cette documentation
├── Gemfile              # Dépendances Ruby
├── .gitignore           # Fichiers exclus du versioning
├── ldap_forumsys.rb     # Étape 1 : script serveur de test
├── ldap_ul.rb           # Étape 2 : script PoC LDAP UL
├── index.php            # Étape 3 (version simple) : formulaire
├── auth.php             # Étape 3 (version simple) : traitement
└── web-interface/       # Étape 3 (version complète)
    ├── README.md        # Doc spécifique à l'interface
    ├── config.php       # Config test/production
    ├── index.php        # Formulaire + traitement + affichage
    └── style.css        # Mise en forme
```
