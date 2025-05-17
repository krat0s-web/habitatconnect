<?php
session_start();
include 'db_connect.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Vérifier si le token existe et n'a pas expiré
        $stmt = $pdo->prepare("SELECT user_id, expires_at FROM email_verifications WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($verification) {
            $user_id = $verification['user_id'];

            // Activer l'utilisateur (mettre active à 1)
            $stmt = $pdo->prepare("UPDATE users SET active = 1 WHERE id = ?");
            $stmt->execute([$user_id]);

            // Supprimer le token après utilisation
            $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Récupérer le rôle de l'utilisateur pour la session
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Connecter l'utilisateur automatiquement
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $user['role'];
            header("Location: profil.php");
            exit;
        } else {
            echo "Le lien de confirmation est invalide ou a expiré.";
        }
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
} else {
    header("Location: connexion.php");
    exit;
}
?>