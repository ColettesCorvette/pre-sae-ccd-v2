# Interface Web — Annuaire LDAP

Interface PHP pour rechercher des utilisateurs dans l'annuaire LDAP.

---

## Prérequis

### PHP + extension LDAP

**Arch / Manjaro :**
```bash
sudo pacman -S php
```
L'extension LDAP est incluse dans le paquet `php` mais désactivée par défaut. Pour l'activer, décommenter la ligne suivante dans `/etc/php/php.ini` :
```ini
extension=ldap
```

**Debian / Ubuntu :**
```bash
sudo apt install php php-ldap
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

Le mode se configure dans **`config.php`** :

```php
define('LDAP_MODE', 'test');  // ou 'ul'
```

| Mode | Valeur | Serveur | Réseau requis |
|------|--------|---------|---------------|
| **Test** | `'test'` | `ldap.forumsys.com` (public) | Aucun |
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

Modifier `config.php` :

```diff
- define('LDAP_MODE', 'test');
+ define('LDAP_MODE', 'ul');
```

- Login = identifiant UL (ex : `fuchs54u`)
- Mot de passe = mot de passe UL
- Nécessite d'être sur le réseau de l'IUT (port 636 filtré ailleurs)
