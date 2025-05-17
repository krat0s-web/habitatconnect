<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: connexion.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$reservations = [];
$messages = [];

try {
    // Récupérer les réservations
    $stmt_reservations = $pdo->prepare("
        SELECT r.*, a.title AS annonce_title, u.username AS proprietaire_name
        FROM reservations r
        JOIN annonces a ON r.annonce_id = a.id
        JOIN users u ON a.proprietaire_id = u.id
        WHERE r.client_id = ? AND r.status = 'confirmed'
        ORDER BY r.start_date DESC
    ");
    $stmt_reservations->execute([$user_id]);
    $reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les messages (questions et réponses)
    $stmt_messages = $pdo->prepare("
        SELECT q.annonce_id, q.question, q.date_question AS question_date, q.reponse, q.date_reponse, a.title AS annonce_title, u.username AS proprietaire_name
        FROM questions q
        JOIN annonces a ON q.annonce_id = a.id
        JOIN users u ON a.proprietaire_id = u.id
        WHERE q.client_id = ?
        ORDER BY q.date_question DESC
    ");
    $stmt_messages->execute([$user_id]);
    $messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes messages</title>
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
    <style>
        body {
            background-color: rgb(255, 255, 255);
            color: #fff;
            margin: 0; /* Supprime les marges par défaut du body */
        }
        .navbar {
            position: fixed; /* Fixe la navbar en haut */
            top: 0;
            width: 100%; /* Prend toute la largeur */
            height: 60px; /* Hauteur de la navbar */
            background-color: #1a3c5e; /* Couleur de fond (adaptée à votre capture d'écran) */
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000; /* Assure que la navbar reste au-dessus des autres éléments */
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            font-size: 16px;
        }
        .container {
            background-color: #fff;
            color: #222;
            border-radius: 8px;
            padding: 20px;
            max-width: 800px;
            margin: 80px auto 20px auto; /* Ajoute une marge en haut pour compenser la hauteur de la navbar (60px + 20px d'espace) */
        }
        h1, h2 {
            color: #222;
        }
        .reservation-item, .message-item {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .message-response {
            margin-left: 20px;
            color: #555;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="liste_annonces.php">Accueil</a>
        <a href="messages_client.php">Mes réservations</a>
        <a href="profil.php">Mon profil</a>
        <a href="deconnexion.php">Se déconnecter</a>
    </div>

    <div class="container">
        <h1>Mes réservations</h1>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($reservations)): ?>
            <p>Aucune réservation confirmée.</p>
        <?php else: ?>
            <?php foreach ($reservations as $reservation): ?>
                <div class="reservation-item">
                    <p><strong>Annonce :</strong> <?php echo htmlspecialchars($reservation['annonce_title']); ?></p>
                    <p><strong>Propriétaire :</strong> <?php echo htmlspecialchars($reservation['proprietaire_name']); ?></p>
                    <p><strong>Date de début :</strong> <?php echo $reservation['start_date']; ?></p>
                    <p><strong>Date de fin :</strong> <?php echo $reservation['end_date']; ?></p>
                    <p><strong>Statut :</strong> <?php echo htmlspecialchars($reservation['status']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>Mes messages</h2>

        <?php if (empty($messages)): ?>
            <p>Aucun message.</p>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="message-item">
                    <p><strong>Annonce :</strong> <?php echo htmlspecialchars($message['annonce_title']); ?></p>
                    <p><strong>Propriétaire :</strong> <?php echo htmlspecialchars($message['proprietaire_name']); ?></p>
                    <p><strong>Votre message :</strong> <?php echo htmlspecialchars($message['question']); ?></p>
                    <p><strong>Date :</strong> <?php echo $message['question_date']; ?></p>
                    <?php if ($message['reponse']): ?>
                        <p class="message-response"><strong>Réponse :</strong> <?php echo htmlspecialchars($message['reponse']); ?></p>
                        <p class="message-response"><strong>Date de réponse :</strong> <?php echo $message['date_reponse']; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p><a href="liste_annonces.php">Retour aux annonces</a></p>
    </div>
</body>
</html>