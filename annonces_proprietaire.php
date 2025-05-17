<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'proprietaire') {
    header("Location: connexion.php");
    exit;
}

$proprietaire_id = $_SESSION['user_id'];

// Gestion des messages (réponse)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['question_id']) && isset($_POST['reponse'])) {
    $question_id = $_POST['question_id'];
    $reponse = $_POST['reponse'];

    try {
        $stmt = $pdo->prepare("
            UPDATE questions 
            SET reponse = ?, date_reponse = NOW(), lu = 1 
            WHERE id = ? AND annonce_id IN (SELECT id FROM annonces WHERE proprietaire_id = ?)
        ");
        $stmt->execute([$reponse, $question_id, $proprietaire_id]);
        $success[] = "Réponse enregistrée avec succès !";
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de l’envoi de la réponse : " . $e->getMessage();
    }
}

// Gestion des réservations (confirmer ou rejeter)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reservation_id']) && isset($_POST['action'])) {
    $reservation_id = $_POST['reservation_id'];
    $action = $_POST['action'];

    try {
        $new_status = $action === 'confirm' ? 'confirmed' : 'rejected';
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET status = ? 
            WHERE id = ? AND annonce_id IN (SELECT id FROM annonces WHERE proprietaire_id = ?)
        ");
        $stmt->execute([$new_status, $reservation_id, $proprietaire_id]);
        $success[] = "Réservation " . ($action === 'confirm' ? 'confirmée' : 'rejetée') . " avec succès !";
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de la gestion de la réservation : " . $e->getMessage();
    }
}

// Gestion de la suppression d’une annonce
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_annonce_id'])) {
    $annonce_id = $_POST['delete_annonce_id'];

    try {
        // Vérifier que l’annonce appartient au propriétaire
        $stmt = $pdo->prepare("SELECT id FROM annonces WHERE id = ? AND proprietaire_id = ?");
        $stmt->execute([$annonce_id, $proprietaire_id]);
        $annonce_exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($annonce_exists) {
            // Désactiver temporairement les contraintes de clé étrangère
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Supprimer les dépendances dans l’ordre correct
            $stmt = $pdo->prepare("DELETE FROM likes WHERE annonce_id = ?");
            $stmt->execute([$annonce_id]);

            $stmt = $pdo->prepare("DELETE FROM equipements WHERE annonce_id = ?");
            $stmt->execute([$annonce_id]);

            $stmt = $pdo->prepare("DELETE FROM annonce_images WHERE annonce_id = ?");
            $stmt->execute([$annonce_id]);

            $stmt = $pdo->prepare("DELETE FROM commentaires WHERE annonce_id = ?");
            $stmt->execute([$annonce_id]);

            $stmt = $pdo->prepare("DELETE FROM reservations WHERE annonce_id = ?");
            $stmt->execute([$annonce_id]);

            $stmt = $pdo->prepare("DELETE FROM questions WHERE annonce_id = ?");
            $stmt->execute([$annonce_id]);

            // Supprimer l’annonce
            $stmt = $pdo->prepare("DELETE FROM annonces WHERE id = ? AND proprietaire_id = ?");
            $stmt->execute([$annonce_id, $proprietaire_id]);

            // Réactiver les contraintes de clé étrangère
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $success[] = "Annonce supprimée avec succès !";
        } else {
            $errors[] = "Erreur : Vous n’êtes pas autorisé à supprimer cette annonce.";
        }
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de la suppression de l’annonce : " . $e->getMessage();
    }
}

try {
    // Statistiques globales
    // 1. Nombre total de réservations
    $stmt_reservations = $pdo->prepare("
        SELECT COUNT(*) as total_reservations 
        FROM reservations r 
        JOIN annonces a ON r.annonce_id = a.id 
        WHERE a.proprietaire_id = ? AND r.status = 'confirmed'
    ");
    $stmt_reservations->execute([$proprietaire_id]);
    $total_reservations = $stmt_reservations->fetchColumn();

    // 2. Revenus générés
    $stmt_revenus = $pdo->prepare("
        SELECT SUM(DATEDIFF(r.end_date, r.start_date) * a.price) as total_revenus 
        FROM reservations r 
        JOIN annonces a ON r.annonce_id = a.id 
        WHERE a.proprietaire_id = ? AND r.status = 'confirmed'
    ");
    $stmt_revenus->execute([$proprietaire_id]);
    $total_revenus = $stmt_revenus->fetchColumn() ?? 0;

    // 3. Taux d’occupation (jours réservés sur l’année)
    $stmt_occupation = $pdo->prepare("
        SELECT SUM(DATEDIFF(r.end_date, r.start_date)) as jours_reserves 
        FROM reservations r 
        JOIN annonces a ON r.annonce_id = a.id 
        WHERE a.proprietaire_id = ? AND r.status = 'confirmed' AND YEAR(r.start_date) = YEAR(CURDATE())
    ");
    $stmt_occupation->execute([$proprietaire_id]);
    $jours_reserves = $stmt_occupation->fetchColumn() ?? 0;
    $jours_total = 365; // Année non bissextile
    $taux_occupation = ($jours_reserves / $jours_total) * 100;

    // 4. Avis moyen par annonce
    $stmt_annonces = $pdo->prepare("SELECT id, title FROM annonces WHERE proprietaire_id = ?");
    $stmt_annonces->execute([$proprietaire_id]);
    $annonces = $stmt_annonces->fetchAll(PDO::FETCH_ASSOC);

    $avis_data = [];
    foreach ($annonces as $annonce) {
        $stmt_avis = $pdo->prepare("
            SELECT AVG(c.etoiles) as moyenne_avis 
            FROM commentaires c 
            WHERE c.annonce_id = ?
        ");
        $stmt_avis->execute([$annonce['id']]);
        $moyenne_avis = $stmt_avis->fetchColumn() ?? 0;
        $avis_data[] = [
            'title' => $annonce['title'],
            'moyenne_avis' => round($moyenne_avis, 1)
        ];
    }

    // 5. Réservations par mois pour le graphique
    $reservations_par_mois = [];
    for ($mois = 1; $mois <= 12; $mois++) {
        $stmt_mois = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM reservations r 
            JOIN annonces a ON r.annonce_id = a.id 
            WHERE a.proprietaire_id = ? AND MONTH(r.start_date) = ? AND YEAR(r.start_date) = YEAR(CURDATE()) AND r.status = 'confirmed'
        ");
        $stmt_mois->execute([$proprietaire_id, $mois]);
        $reservations_par_mois[] = $stmt_mois->fetchColumn();
    }

    // Récupérer les messages
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;

    $sql = "
        SELECT q.id, q.question, q.reponse, q.date_question, q.date_reponse, u.username AS client_username, a.title
        FROM questions q
        JOIN annonces a ON q.annonce_id = a.id
        JOIN users u ON q.client_id = u.id
        WHERE a.proprietaire_id = ?
        ORDER BY q.date_question DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$proprietaire_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM questions q JOIN annonces a ON q.annonce_id = a.id WHERE a.proprietaire_id = ?");
    $stmt_count->execute([$proprietaire_id]);
    $total_questions = $stmt_count->fetchColumn();
    $total_pages = ceil($total_questions / $limit);

    // Récupérer les réservations en attente
    $stmt_pending_reservations = $pdo->prepare("
        SELECT r.id, r.client_id, r.annonce_id, r.start_date, r.end_date, r.status, u.username AS client_username, a.title
        FROM reservations r
        JOIN annonces a ON r.annonce_id = a.id
        JOIN users u ON r.client_id = u.id
        WHERE a.proprietaire_id = ? AND r.status = 'pending'
        ORDER BY r.start_date ASC
    ");
    $stmt_pending_reservations->execute([$proprietaire_id]);
    $pending_reservations = $stmt_pending_reservations->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les réservations confirmées
    $stmt_confirmed_reservations = $pdo->prepare("
        SELECT r.id, r.client_id, r.annonce_id, r.start_date, r.end_date, r.status, u.username AS client_username, a.title
        FROM reservations r
        JOIN annonces a ON r.annonce_id = a.id
        JOIN users u ON r.client_id = u.id
        WHERE a.proprietaire_id = ? AND r.status = 'confirmed'
        ORDER BY r.start_date DESC
    ");
    $stmt_confirmed_reservations->execute([$proprietaire_id]);
    $confirmed_reservations = $stmt_confirmed_reservations->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les annonces du propriétaire
    $stmt_annonces = $pdo->prepare("
        SELECT a.id, a.title, a.description, a.price, a.localisation
        FROM annonces a
        WHERE a.proprietaire_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt_annonces->execute([$proprietaire_id]);
    $annonces = $stmt_annonces->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gérer mes annonces</title>
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
    <!-- Ajouter Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0; /* Supprime les marges par défaut du body */
        }
        .navbar {
            position: fixed; /* Fixe la navbar en haut */
            top: 0;
            width: 100%; /* Prend toute la largeur */
            height: 60px; /* Hauteur de la navbar */
            background-color: #34495e; /* Couleur de fond (adaptée à votre capture d'écran) */
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
            max-width: 1200px;
            margin: 80px auto 20px auto; /* Ajoute une marge en haut pour compenser la hauteur de la navbar (60px + 20px d'espace) */
        }
        .dashboard {
            margin-bottom: 30px;
        }
        .stats {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.2em;
            color: #34495e;
        }
        .stat-card p {
            margin: 0;
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        .chart-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .avis-list {
            margin-top: 20px;
        }
        .avis-item {
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .avis-item span {
            font-weight: bold;
            color: #e74c3c;
        }
        .annonce, .reservation, .question {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .annonce p, .reservation p, .question p {
            margin: 5px 0;
            color: #555;
        }
        .annonce .content, .reservation .content, .question .content {
            margin-bottom: 15px;
        }
        .annonce .actions, .reservation .actions {
            text-align: right;
        }
        .annonce form, .reservation form {
            display: inline;
            margin-left: 10px;
        }
        .annonce form input[type="submit"], .reservation form input[type="submit"] {
            background-color: #e74c3c;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .annonce form input[type="submit"][value="Modifier"], .annonce a[href*="modifier_annonce.php"] {
            background-color: #007bff;
        }
        .annonce form input[type="submit"][value="Modifier"]:hover, .annonce a[href*="modifier_annonce.php"]:hover {
            background-color: #0056b3;
        }
        .annonce form input[type="submit"]:hover, .reservation form input[type="submit"]:hover {
            background-color: #c0392b;
        }
        .reservation form input[type="submit"][value="Confirmer"] {
            background-color: #28a745;
        }
        .reservation form input[type="submit"][value="Confirmer"]:hover {
            background-color: #218838;
        }
        .reservation form input[type="submit"][value="Rejeter"] {
            background-color: #dc3545;
        }
        .reservation form input[type="submit"][value="Rejeter"]:hover {
            background-color: #c82333;
        }
        .question form textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .question form input[type="submit"] {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .question form input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            margin: 0 5px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="liste_annonces.php">Accueil</a>
        <a href="creer_annonce.php">Créer une annonce</a>
        <a href="profil.php">Mon profil</a>
        <a href="deconnexion.php">Se déconnecter</a>
    </div>

    <div class="container">
        <h1>Gérer mes annonces</h1>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <?php foreach ($success as $msg): ?>
                <p class="success"><?php echo $msg; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>Tableau de bord</h2>
        <div class="dashboard">
            <div class="stats">
                <div class="stat-card">
                    <h3>Réservations totales</h3>
                    <p><?php echo $total_reservations; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Revenus générés</h3>
                    <p><?php echo number_format($total_revenus, 2); ?> €</p>
                </div>
                <div class="stat-card">
                    <h3>Taux d’occupation</h3>
                    <p><?php echo round($taux_occupation, 1); ?>%</p>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="reservationsChart"></canvas>
            </div>
            <div class="avis-list">
                <h3>Avis moyens par annonce</h3>
                <?php if (empty($avis_data)): ?>
                    <p>Aucun avis pour vos annonces.</p>
                <?php else: ?>
                    <?php foreach ($avis_data as $avis): ?>
                        <div class="avis-item">
                            <?php echo htmlspecialchars($avis['title']); ?> : <span><?php echo $avis['moyenne_avis']; ?>/5</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <h2>Mes annonces</h2>
        <?php if (empty($annonces)): ?>
            <p>Aucune annonce pour le moment.</p>
        <?php else: ?>
            <?php foreach ($annonces as $annonce): ?>
                <div class="annonce">
                    <div class="content">
                        <p><strong>Titre :</strong> <?php echo htmlspecialchars($annonce['title']); ?></p>
                        <p><strong>Description :</strong> <?php echo htmlspecialchars($annonce['description']); ?></p>
                        <p><strong>Localisation :</strong> <?php echo htmlspecialchars($annonce['localisation'] ?? 'Non précisé'); ?></p>
                        <p><strong>Prix par nuit :</strong> <?php echo $annonce['price'] ? number_format($annonce['price'], 2) . ' €' : 'Non précisé'; ?></p>
                    </div>
                    <div class="actions">
                        <a href="modifier_annonce.php?id=<?php echo htmlspecialchars($annonce['id']); ?>" style="background-color: #007bff; color: #fff; padding: 8px 15px; border-radius: 20px; text-decoration: none; margin-right: 10px;">Modifier</a>
                        <form method="POST">
                            <input type="hidden" name="delete_annonce_id" value="<?php echo $annonce['id']; ?>">
                            <input type="submit" value="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?');">
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>Réservations en attente</h2>
        <?php if (empty($pending_reservations)): ?>
            <p>Aucune réservation en attente.</p>
        <?php else: ?>
            <?php foreach ($pending_reservations as $reservation): ?>
                <div class="reservation">
                    <div class="content">
                        <p><strong>Annonce :</strong> <?php echo htmlspecialchars($reservation['title']); ?></p>
                        <p><strong>Client :</strong> <?php echo htmlspecialchars($reservation['client_username']); ?></p>
                        <p><strong>Date de début :</strong> <?php echo $reservation['start_date']; ?></p>
                        <p><strong>Date de fin :</strong> <?php echo $reservation['end_date'] ?: 'Non précisé'; ?></p>
                    </div>
                    <div class="actions">
                        <form method="POST">
                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                            <input type="hidden" name="action" value="confirm">
                            <input type="submit" value="Confirmer">
                        </form>
                        <form method="POST">
                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="submit" value="Rejeter">
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>Réservations confirmées</h2>
        <?php if (empty($confirmed_reservations)): ?>
            <p>Aucune réservation confirmée.</p>
        <?php else: ?>
            <?php foreach ($confirmed_reservations as $reservation): ?>
                <div class="reservation">
                    <div class="content">
                        <p><strong>Annonce :</strong> <?php echo htmlspecialchars($reservation['title']); ?></p>
                        <p><strong>Client :</strong> <?php echo htmlspecialchars($reservation['client_username']); ?></p>
                        <p><strong>Date de début :</strong> <?php echo $reservation['start_date']; ?></p>
                        <p><strong>Date de fin :</strong> <?php echo $reservation['end_date'] ?: 'Non précisé'; ?></p>
                        <p><strong>Statut :</strong> Confirmée</p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>Messages reçus</h2>
        <?php if (empty($questions)): ?>
            <p>Aucun message pour vos annonces.</p>
        <?php else: ?>
            <?php foreach ($questions as $question): ?>
                <div class="question">
                    <div class="content">
                        <p><strong>Annonce :</strong> <?php echo htmlspecialchars($question['title']); ?></p>
                        <p><strong>Message de <?php echo htmlspecialchars($question['client_username']); ?> :</strong> <?php echo htmlspecialchars($question['question']); ?></p>
                        <p><strong>Date :</strong> <?php echo $question['date_question']; ?></p>
                        <?php if ($question['reponse']): ?>
                            <p><strong>Votre réponse :</strong> <?php echo htmlspecialchars($question['reponse']); ?></p>
                            <p><strong>Date de réponse :</strong> <?php echo $question['date_reponse']; ?></p>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                <label>Votre réponse :</label>
                                <textarea name="reponse" required></textarea>
                                <div class="actions">
                                    <input type="submit" value="Répondre">
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" <?php echo $i === $page ? 'style="font-weight:bold;"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Graphique des réservations par mois
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('reservationsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                    datasets: [{
                        label: 'Réservations par mois',
                        data: <?php echo json_encode($reservations_par_mois); ?>,
                        backgroundColor: 'rgba(52, 73, 94, 0.6)',
                        borderColor: 'rgba(52, 73, 94, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de réservations'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Mois'
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>