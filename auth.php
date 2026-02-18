<?php
// On récupère les infos du formulaire
$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($login) || empty($password)) {
    die("Veuillez remplir tous les champs.");
}

// Configuration UL
$serveurs = ["montet-dc1.ad.univ-lorraine.fr", "montet-dc2.ad.univ-lorraine.fr"];
$base_dn_etudiants = "OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr";
$base_dn_personnels = "OU=Personnels,OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr";

$ldap_conn = null;

// 1. Connexion et Bind
foreach ($serveurs as $srv) {
    // Utilisation de ldaps:// pour forcer le port 636
    $ds = ldap_connect("ldaps://$srv");
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

    // Tentative de bind (@etu pour les étudiants)
    $user_dn = "$login@etu.univ-lorraine.fr"; 
    if (@ldap_bind($ds, $user_dn, $password)) {
        $ldap_conn = $ds;
        break;
    }
}

if (!$ldap_conn) {
	echo '<form action="index.php">
        <button type="submit">Retour</button>
      </form>';
    die("Échec de l'authentification (Vérifiez votre login/mdp ou votre connexion IUT).");
}

// 2. Recherche
$found = false;
$filter = "(sAMAccountName=$login)";

foreach ([$base_dn_etudiants, $base_dn_personnels] as $base) {
    $sr = ldap_search($ldap_conn, $base, $filter);
    $info = ldap_get_entries($ldap_conn, $sr);

    if ($info["count"] > 0) {
        $found = true;
        $user = $info[0];

        echo "<h2>Bienvenue, " . $user["cn"][0] . " !</h2>";
        echo "<b>Login :</b> " . $user["samaccountname"][0] . "<br>";
        echo "<b>Email :</b> " . ($user["mail"][0] ?? "Non renseigné") . "<br>";
        echo "<b>DN :</b> " . $user["dn"] . "<br>";

        // 3. Analyse des groupes (Rattachement)
        if (isset($user["memberof"])) {
            foreach ($user["memberof"] as $group) {
                if (strpos($group, "GGA_STP_FHBAB") !== false) {
                    echo "<b>Rattachement :</b> Département Informatique<br>";
                } elseif (strpos($group, "GGA_STP_FHB") !== false) {
                    echo "<b>Rattachement :</b> IUTNC<br>";
                }
            }
        }
        break;
    }
}

if (!$found) {
    echo "Utilisateur connecté mais introuvable dans les branches spécifiées.";
}

echo '<form action="index.php">
        <button type="submit"> Retour </button>
      </form>';

ldap_close($ldap_conn);
?>
