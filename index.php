<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion LDAP - UL</title>
</head>
<body>
    <h1>Connexion LDAP</h1>
    <form action="auth.php" method="POST">
        <label>Login :</label>
        <input type="text" name="login" required>

        <label>Mot de passe :</label>
        <input type="password" name="password" required>

        <button type="submit">Se connecter</button>
    </form>
</body>
</html>
