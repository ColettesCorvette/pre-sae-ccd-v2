<?php
require_once 'config.php';

// ── Vérification de l'extension LDAP ──────────────────────
$ldap_available = extension_loaded('ldap');

// ── Variables ─────────────────────────────────────────────
$results = [];
$error = '';
$connected_server = '';
$searched = false;
$config = get_config();
$mode = LDAP_MODE;

// ── Traitement du formulaire ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ldap_available) {
    $searched = true;
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $search_q = trim($_POST['search'] ?? '');

    if (empty($login) || empty($password)) {
        $error = 'Veuillez remplir le login et le mot de passe.';
    } else {
        // Si aucun login à chercher, on cherche soi-même
        if (empty($search_q)) {
            $search_q = $login;
        }

        $bind_dn  = sprintf($config['bind_format'], $login);
        $ldap_conn = null;

        // Désactiver la vérification des certificats TLS (pour les CA internes)
        if ($config['use_tls']) {
            putenv('LDAPTLS_REQCERT=never');
        }

        // ── Failover : on essaie chaque serveur ──────────
        foreach ($config['servers'] as $server) {
            $proto = $config['use_tls'] ? 'ldaps' : 'ldap';
            $uri   = "{$proto}://{$server}:{$config['port']}";

            $ldap_conn = @ldap_connect($uri);
            if (!$ldap_conn) continue;

            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 5);

            if (@ldap_bind($ldap_conn, $bind_dn, $password)) {
                $connected_server = $server;
                break;
            } else {
                @ldap_close($ldap_conn);
                $ldap_conn = null;
            }
        }

        if (!$ldap_conn) {
            $error = 'Connexion impossible. Vérifiez vos identifiants.';
            if ($mode === 'ul') {
                $error .= ' Assurez-vous aussi d\'être sur le réseau de l\'IUT.';
            }
        } else {
            // ── Recherche dans les différentes branches ──
            $safe_q = ldap_escape($search_q, '', LDAP_ESCAPE_FILTER);
            $filter = "({$config['search_attribute']}={$safe_q})";

            foreach ($config['base_dns'] as $base_dn) {
                $sr = @ldap_search($ldap_conn, $base_dn, $filter, $config['display_attributes']);
                if (!$sr) continue;

                $entries = ldap_get_entries($ldap_conn, $sr);
                if ($entries['count'] === 0) continue;

                for ($i = 0; $i < $entries['count']; $i++) {
                    $e = $entries[$i];
                    $r = [];

                    // Nom
                    if (isset($e['cn'][0]))  $r['nom'] = $e['cn'][0];

                    // Login
                    $la = strtolower($config['search_attribute']);
                    if (isset($e[$la][0]))   $r['login'] = $e[$la][0];

                    // Mail
                    if (isset($e['mail'][0])) $r['mail'] = $e['mail'][0];

                    // DN
                    if (isset($e['distinguishedname'][0])) {
                        $r['dn'] = $e['distinguishedname'][0];
                    } elseif (isset($e['dn'])) {
                        $r['dn'] = $e['dn'];
                    }

                    // Téléphone (test mode)
                    if (isset($e['telephonenumber'][0])) {
                        $r['telephone'] = $e['telephonenumber'][0];
                    }

                    // Rattachement (UL mode — via memberOf)
                    $r['rattachements'] = [];
                    if (isset($e['memberof']) && !empty($config['rattachement_groups'])) {
                        for ($j = 0; $j < $e['memberof']['count']; $j++) {
                            foreach ($config['rattachement_groups'] as $pattern => $label) {
                                if (str_contains($e['memberof'][$j], $pattern)) {
                                    $r['rattachements'][] = $label;
                                }
                            }
                        }
                    }

                    $results[] = $r;
                }
                break; // trouvé, on arrête
            }

            @ldap_close($ldap_conn);

            if (empty($results)) {
                $error = 'Aucun résultat pour « ' . htmlspecialchars($search_q) . ' ».';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche LDAP — SAÉ Projet Tuteuré</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <!-- ── En-tête ──────────────────────────────────────── -->
    <div class="header">
        <span class="mode-badge <?= $mode ?>">
            <?= $mode === 'test' ? '⚙ Mode test' : '🔒 Production UL' ?>
        </span>
        <h1>Annuaire LDAP</h1>
    </div>

    <!-- ── Extension manquante ──────────────────────────── -->
    <?php if (!$ldap_available): ?>
        <div class="card extension-error">
            <h2>Extension PHP LDAP manquante</h2>
            <p>L'extension <code>ldap</code> n'est pas installée. Installez-la selon votre système :</p>
            <pre># macOS (Homebrew)
brew install php
# L'extension ldap est incluse par défaut

# Debian / Ubuntu
sudo apt install php-ldap

# Arch / Manjaro
sudo pacman -S php-ldap

# Fedora
sudo dnf install php-ldap</pre>
            <p>Puis relancez le serveur PHP.</p>
        </div>
    <?php else: ?>

    <!-- ── Formulaire ───────────────────────────────────── -->
    <form method="POST" class="card" autocomplete="off">
        <div class="form-group">
            <label for="login">
                <?= $mode === 'test' ? 'Identifiant' : 'Login UL' ?>
            </label>
            <input
                type="text"
                id="login"
                name="login"
                value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </div>

        <div class="form-group">
            <label for="search">Rechercher un utilisateur</label>
            <input
                type="text"
                id="search"
                name="search"
                value="<?= htmlspecialchars($_POST['search'] ?? '') ?>"
            >
        </div>

        <button type="submit" class="btn">Rechercher</button>
    </form>

    <!-- ── Erreur ───────────────────────────────────────── -->
    <?php if ($searched && $error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── Résultats ────────────────────────────────────── -->
    <?php if (!empty($results)): ?>
        <div class="message success">
            Connecté à <strong><?= htmlspecialchars($connected_server) ?></strong>
            — <?= count($results) ?> résultat(s)
        </div>

        <?php foreach ($results as $r): ?>
            <div class="result-card">
                <?php if (!empty($r['nom'])): ?>
                <div class="result-row">
                    <span class="result-label">Nom</span>
                    <span class="result-value"><?= htmlspecialchars($r['nom']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($r['login'])): ?>
                <div class="result-row">
                    <span class="result-label">Login</span>
                    <span class="result-value"><?= htmlspecialchars($r['login']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($r['mail'])): ?>
                <div class="result-row">
                    <span class="result-label">Mail</span>
                    <span class="result-value"><?= htmlspecialchars($r['mail']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($r['telephone'])): ?>
                <div class="result-row">
                    <span class="result-label">Téléphone</span>
                    <span class="result-value"><?= htmlspecialchars($r['telephone']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($r['dn'])): ?>
                <div class="result-row">
                    <span class="result-label">DN</span>
                    <span class="result-value"><?= htmlspecialchars($r['dn']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($r['rattachements'])): ?>
                <div class="result-row">
                    <span class="result-label">Rattachement</span>
                    <span class="result-value">
                        <?php foreach ($r['rattachements'] as $rat): ?>
                            <span class="rattachement-tag"><?= htmlspecialchars($rat) ?></span>
                        <?php endforeach; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; /* ldap_available */ ?>

    <!-- ── Aide ─────────────────────────────────────────── -->
    <div class="help-text">
        <?php if ($mode === 'test'): ?>
            Mode test — serveur public <code>ldap.forumsys.com</code><br>
            Pour passer en production, modifier <code>LDAP_MODE</code> dans <code>config.php</code>
        <?php else: ?>
            Connexion LDAPS vers l'annuaire de l'Université de Lorraine<br>
            Nécessite d'être connecté au réseau de l'IUT
        <?php endif; ?>
    </div>

</div>

</body>
</html>
