<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultat - LDAP UL</title>
</head>
<body>

<?php
// on récupère les infos du formulaire
$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($login) || empty($password)) {
    echo "<p>Veuillez remplir tous les champs.</p>";
    echo '<a href="index.php">Retour</a>';
    exit;
}

// config serveurs UL
$serveurs = ["montet-dc1.ad.univ-lorraine.fr", "montet-dc2.ad.univ-lorraine.fr"];
$base_dn_etudiants = "OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr";
$base_dn_personnels = "OU=Personnels,OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr";

$ldap_conn = null;

// connexion et bind sur un des serveurs
foreach ($serveurs as $srv) {
    $ds = ldap_connect("ldaps://$srv");
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

    // bind avec le compte étudiant
    $user_dn = "$login@etu.univ-lorraine.fr";
    if (@ldap_bind($ds, $user_dn, $password)) {
        $ldap_conn = $ds;
        break;
    }
}

if (!$ldap_conn) {
    echo "<p>Échec de l'authentification (vérifiez votre login/mdp ou votre connexion IUT).</p>";
    echo '<a href="index.php">Retour</a>';
    exit;
}

// recherche de l'utilisateur dans l'annuaire
// on échappe le login pour éviter une injection LDAP
$login_safe = ldap_escape($login, "", LDAP_ESCAPE_FILTER);
$filtre = "(sAMAccountName=$login_safe)";
$found = false;

foreach ([$base_dn_etudiants, $base_dn_personnels] as $base) {
    $sr = ldap_search($ldap_conn, $base, $filtre);
    $info = ldap_get_entries($ldap_conn, $sr);

    if ($info["count"] > 0) {
        $found = true;
        $user = $info[0];

        echo "<h2>Bienvenue, " . htmlspecialchars($user["cn"][0]) . " !</h2>";
        echo "<p><b>Login :</b> " . htmlspecialchars($user["samaccountname"][0]) . "</p>";
        echo "<p><b>Email :</b> " . htmlspecialchars($user["mail"][0] ?? "Non renseigné") . "</p>";
        echo "<p><b>DN :</b> " . htmlspecialchars($user["dn"]) . "</p>";

        // on regarde les groupes pour le rattachement
        if (isset($user["memberof"])) {
            foreach ($user["memberof"] as $group) {
                if (is_string($group)) {
                    if (strpos($group, "GGA_STP_FHBAB") !== false) {
                        echo "<p><b>Rattachement :</b> Département Informatique</p>";
                    } elseif (strpos($group, "GGA_STP_FHB") !== false) {
                        echo "<p><b>Rattachement :</b> IUTNC</p>";
                    }
                }
            }
        }
        break;
    }
}

if (!$found) {
    echo "<p>Connecté mais introuvable dans l'annuaire.</p>";
}

ldap_close($ldap_conn);
?>

<br>
<a href="index.php">Retour</a>

</body>
</html>
