<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'proprietaire') {
    header("Location: connexion.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_annonce_id'])) {
    $annonce_id = $_POST['delete_annonce_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM annonces WHERE id = ? AND proprietaire_id = ?");
        $stmt->execute([$annonce_id, $_SESSION['user_id']]);
        echo "<p>Annonce supprimée avec succès !</p>";
        header("Location: mes_annonces.php"); // Recharger la page après suppression
        exit;
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, title, description, price, localisation, created_at FROM annonces WHERE proprietaire_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes annonces</title>
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
</head>
<body>
    <div class="navbar">
        <a href="creer_annonce.php">Créer une annonce</a>
        <a href="annonces_proprietaire.php">Retour à la gestion</a>
        <a href="liste_annonces.php">Retour à la liste</a>
    </div>

    <div class="section">
        <h1>Mes annonces</h1>
        <?php if (empty($annonces)): ?>
            <p>Vous n’avez pas encore créé d’annonces.</p>
        <?php else: ?>
            <?php foreach ($annonces as $annonce): ?>
                <div class="annonce-item">
                    <p><strong>Titre :</strong> <?php echo htmlspecialchars($annonce['title']); ?></p>
                    <p><strong>Description :</strong> <?php echo htmlspecialchars($annonce['description']); ?></p>
                    <p><strong>Prix :</strong> <?php echo $annonce['price'] ? number_format($annonce['price'], 2) . ' €' : 'Non précisé'; ?></p>
                    <p><strong>Localisation :</strong> <?php echo htmlspecialchars($annonce['localisation'] ?? 'Non précisé'); ?></p>
                    <p><strong>Date de création :</strong> <?php echo $annonce['created_at']; ?></p>
                    <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?');">
                        <input type="hidden" name="delete_annonce_id" value="<?php echo $annonce['id']; ?>">
                        <input type="submit" value="Supprimer">
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>