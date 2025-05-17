<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php"); // Rediriger si non connecté
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accueil</title>
</head>
<body>
    <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?> !</h1>
    <p>Votre rôle : <?php echo $_SESSION['role']; ?></p>
    <?php if ($_SESSION['role'] == 'proprietaire'): ?>
        <p><a href="creer_annonce.php">Créer une annonce</a></p>
    <?php endif; ?>
    <p><a href="liste_annonces.php">Voir les annonces</a></p>
    <p><a href="deconnexion.php">Se déconnecter</a></p>
</body>
</html>