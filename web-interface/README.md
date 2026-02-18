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

# Mode test (par défaut)
php -S localhost:8000

# Mode production (LDAP UL, depuis le réseau IUT)
LDAP_MODE=ul php -S localhost:8000
```

Ouvrir **http://localhost:8000** dans un navigateur.

---

## Mode test / Mode production

Le mode se choisit via la variable d'environnement `LDAP_MODE` au lancement. Par défaut c'est `test`.

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

```bash
LDAP_MODE=ul php -S localhost:8000
```

- Login = identifiant UL (ex : `fuchs54u`)
- Mot de passe = mot de passe UL
- Nécessite d'être sur le réseau de l'IUT (port 636 filtré ailleurs)
