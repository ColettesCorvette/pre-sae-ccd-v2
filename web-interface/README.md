# Interface Web — Annuaire LDAP

Interface PHP pour rechercher des utilisateurs dans l'annuaire LDAP.

---

## Prérequis

### PHP + extension LDAP

**macOS (Homebrew) :**
```bash
brew install php
```
> L'extension `ldap` est incluse automatiquement.

**Debian / Ubuntu :**
```bash
sudo apt install php php-ldap
```

**Arch / Manjaro :**
```bash
sudo pacman -S php php-ldap
```

**Fedora :**
```bash
sudo dnf install php php-ldap
```

### Vérifier l'installation

```bash
php -v              # Doit afficher PHP >= 8.0
php -m | grep ldap  # Doit afficher "ldap"
```

---

## Lancement

```bash
cd web-interface/
php -S localhost:8000
```

Ouvrir **http://localhost:8000** dans un navigateur.

---

## Mode test / Mode production

Le mode se configure dans **`config.php`**, à la ligne :

```php
define('LDAP_MODE', 'test');
```

| Mode | Valeur | Serveur | Réseau requis |
|------|--------|---------|---------------|
| **Test** | `'test'` | `ldap.forumsys.com` (public) | Aucun — marche partout |
| **Production** | `'ul'` | LDAPS Université de Lorraine | Réseau IUT obligatoire |

### Mode test (par défaut)

Utilise le serveur public forumsys. Comptes disponibles :

| Login | Mot de passe |
|-------|-------------|
| `einstein` | `password` |
| `tesla` | `password` |
| `newton` | `password` |
| `curie` | `password` |

### Mode production

Pour basculer en production, modifier `config.php` :

```diff
- define('LDAP_MODE', 'test');
+ define('LDAP_MODE', 'ul');
```

- Login = votre identifiant UL (ex: `prenom.nom`)
- Mot de passe = votre mot de passe UL
- **Nécessite d'être sur le réseau de l'IUT** (le port 636 est filtré ailleurs)
