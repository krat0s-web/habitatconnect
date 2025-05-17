<?php
session_start();
include 'db_connect.php';

// Charger la langue
$default_lang = 'fr';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang_code = $_SESSION['lang'] ?? $default_lang;

// Vérifier si le fichier de langue existe
$lang_file = "lang/lang_$lang_code.php";
if (!file_exists($lang_file)) {
    $lang_code = $default_lang;
    $lang_file = "lang/lang_$lang_code.php";
    if (!file_exists($lang_file)) {
        die("Erreur : Les fichiers de langue sont manquants. Veuillez créer les fichiers lang/lang_fr.php et lang/lang_en.php.");
    }
}
require $lang_file;

if (!isset($_GET['id'])) {
    if (isset($_GET['redirected']) && $_GET['redirected'] == 'true') {
        // Si on a déjà été redirigé, arrêter la boucle
        echo "Erreur : ID non défini et redirection déjà effectuée.";
        exit;
    }
    header("Location: liste_annonces.php?redirected=true");
    exit;
}

$annonce_id = $_GET['id'];

try {
    // Récupérer les détails de l’annonce avec la colonne localisation
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.description, a.price, a.localisation, a.created_at, u.username, a.proprietaire_id 
        FROM annonces a 
        JOIN users u ON a.proprietaire_id = u.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$annonce_id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        if (isset($_GET['redirected']) && $_GET['redirected'] == 'true') {
            // Si on a déjà été redirigé, arrêter la boucle
            echo "Erreur : Annonce non trouvée et redirection déjà effectuée.";
            exit;
        }
        header("Location: liste_annonces.php?redirected=true");
        exit;
    }

    // Vérifier si l'utilisateur est le propriétaire
    $is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $annonce['proprietaire_id'];

    // Enregistrer la vue si l'utilisateur est connecté
    if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client') {
        $client_id = $_SESSION['user_id'];
        try {
            $stmt_view = $pdo->prepare("INSERT INTO views (client_id, annonce_id) VALUES (?, ?)");
            $stmt_view->execute([$client_id, $annonce_id]);
        } catch (PDOException $e) {
            // Ignorer l'erreur (si la vue existe déjà, par exemple)
        }
    }

    // Récupérer les images de l’annonce
    $stmt_images = $pdo->prepare("SELECT photo FROM annonce_images WHERE annonce_id = ?");
    $stmt_images->execute([$annonce_id]);
    $images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les équipements actuels
    $stmt_equip = $pdo->prepare("SELECT name FROM equipements WHERE annonce_id = ?");
    $stmt_equip->execute([$annonce_id]);
    $equipements = $stmt_equip->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier si des équipements existent
    $has_equipments = !empty($equipements);

    // Récupérer les réservations confirmées pour afficher les périodes réservées
    $stmt_reservations = $pdo->prepare("
        SELECT start_date, end_date 
        FROM reservations 
        WHERE annonce_id = ? AND status = 'confirmed'
    ");
    $stmt_reservations->execute([$annonce_id]);
    $reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les commentaires
    $stmt_commentaires = $pdo->prepare("
        SELECT c.id, c.commentaire, c.etoiles, c.date_commentaire, u.username
        FROM commentaires c
        JOIN users u ON c.user_id = u.id
        WHERE c.annonce_id = ?
        ORDER BY c.date_commentaire DESC
    ");
    $stmt_commentaires->execute([$annonce_id]);
    $commentaires = $stmt_commentaires->fetchAll(PDO::FETCH_ASSOC);

    // Compter les réponses non lues pour le client
    $unread_count = 0;
    if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client') {
        $stmt_unread = $pdo->prepare("
            SELECT COUNT(*) 
            FROM questions q 
            JOIN annonces a ON q.annonce_id = a.id 
            WHERE q.client_id = ? AND q.reponse IS NOT NULL AND q.lu = 0
        ");
        $stmt_unread->execute([$_SESSION['user_id']]);
        $unread_count = $stmt_unread->fetchColumn();
    }

    // Récupérer la promotion active
    $stmt_promo = $pdo->prepare("
        SELECT discount_percentage, start_date, end_date 
        FROM promotions 
        WHERE annonce_id = ? 
        AND start_date <= CURDATE() 
        AND end_date >= CURDATE()
        LIMIT 1
    ");
    $stmt_promo->execute([$annonce_id]);
    $promotion = $stmt_promo->fetch(PDO::FETCH_ASSOC);

    // Vérifier la disponibilité de l’annonce pour les dates demandées
    $is_available = true;
    $availability_message = '';
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reserver' && isset($_SESSION['user_id']) && $_SESSION['role'] == 'client') {
        $client_id = $_SESSION['user_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        if ($start_date && $end_date && strtotime($start_date) <= strtotime($end_date)) {
            $stmt_check = $pdo->prepare("
                SELECT * FROM reservations 
                WHERE annonce_id = ? AND status = 'confirmed' 
                AND NOT (end_date < ? OR start_date > ?)
            ");
            $stmt_check->execute([$annonce_id, $start_date, $end_date]);
            if ($stmt_check->fetch()) {
                $is_available = false;
                $availability_message = $translations['booked_dates_error'];
            }
        } else {
            $is_available = false;
            $availability_message = $translations['invalid_dates_error'];
        }

        if ($is_available) {
            $stmt_reserv = $pdo->prepare("INSERT INTO reservations (client_id, annonce_id, status, start_date, end_date) VALUES (?, ?, 'pending', ?, ?)");
            $stmt_reserv->execute([$client_id, $annonce_id, $start_date, $end_date]);
            $success[] = $translations['booking_success'];
            header("Location: details_annonce.php?id=$annonce_id");
            exit;
        }
    }

    // Gestion de l’ajout de commentaire
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'ajouter_commentaire' && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt_check->execute([$user_id]);
        $user_exists = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$user_exists) {
            echo "Erreur : Utilisateur introuvable. Veuillez vous reconnecter.";
            exit;
        }

        $commentaire = $_POST['commentaire'];
        $etoiles = (int)$_POST['etoiles'];

        $stmt = $pdo->prepare("INSERT INTO commentaires (annonce_id, user_id, commentaire, etoiles) VALUES (?, ?, ?, ?)");
        $stmt->execute([$annonce_id, $user_id, $commentaire, $etoiles]);
        header("Location: details_annonce.php?id=$annonce_id");
        exit;
    }

    // Gestion de l’envoi de message privé
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'envoyer_message' && isset($_SESSION['user_id']) && $_SESSION['role'] == 'client') {
        $client_id = $_SESSION['user_id'];
        $message = $_POST['message'];

        $stmt = $pdo->prepare("INSERT INTO questions (annonce_id, client_id, question, lu) VALUES (?, ?, ?, 0)");
        $stmt->execute([$annonce_id, $client_id, $message]);
        header("Location: details_annonce.php?id=$annonce_id");
        exit;
    }

    // Gestion du suivi
    $is_following = false;
    if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client') {
        $stmt_follow = $pdo->prepare("SELECT * FROM followers WHERE client_id = ? AND proprietaire_id = ?");
        $stmt_follow->execute([$_SESSION['user_id'], $annonce['proprietaire_id']]);
        $is_following = $stmt_follow->fetch() ? true : false;

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'follow') {
            $proprietaire_id = $annonce['proprietaire_id'];
            if ($proprietaire_id && $proprietaire_id != $_SESSION['user_id']) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO followers (client_id, proprietaire_id) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $proprietaire_id]);
                $success[] = $translations['follow_success'];
                $is_following = true;
                header("Location: details_annonce.php?id=$annonce_id");
                exit;
            }
        }
    }

    // Simuler les coordonnées de la localisation
    $coords = ['lat' => 48.8566, 'lng' => 2.3522]; // Par défaut : Paris
    if (stripos($annonce['localisation'], 'Paris') !== false) {
        $coords = ['lat' => 48.8566, 'lng' => 2.3522];
    } elseif (stripos($annonce['localisation'], 'Lyon') !== false) {
        $coords = ['lat' => 45.7640, 'lng' => 4.8357];
    } elseif (stripos($annonce['localisation'], 'Mulhouse') !== false) {
        $coords = ['lat' => 47.7508, 'lng' => 7.3359]; // Coordonnées de Mulhouse
    }

    // Générer une URL pour un lien vers OpenStreetMap
    $map_link_url = "https://www.openstreetmap.org/?mlat={$coords['lat']}&mlon={$coords['lng']}#map=13/{$coords['lat']}/{$coords['lng']}";

    // Récupérer les annonces similaires (basées sur la localisation et les équipements)
    try {
        $sql_similar = "
            SELECT DISTINCT a.id, a.title, a.price, a.localisation, u.username
            FROM annonces a
            JOIN users u ON a.proprietaire_id = u.id
            JOIN equipements e1 ON a.id = e1.annonce_id
            WHERE a.id != :annonce_id
            AND a.localisation LIKE :localisation
            AND e1.name IN (
                SELECT e2.name
                FROM equipements e2
                WHERE e2.annonce_id = :annonce_id
            )
            GROUP BY a.id, a.title, a.price, a.localisation, u.username
            HAVING COUNT(DISTINCT e1.name) >= 2
            LIMIT 3
        ";
        $stmt_similar = $pdo->prepare($sql_similar);
        $stmt_similar->execute([
            ':annonce_id' => $annonce_id,
            ':localisation' => "%{$annonce['localisation']}%"
        ]);
        $similar_annonces = $stmt_similar->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $similar_annonces = [];
    }
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
    exit;
}

// Associer des icônes aux équipements
$equipment_icons = [
    "Garage" => "🚗",
    "Rangement de bagages" => "🧳",
    "Piscine" => "🏊",
    "Salle de bain" => "🛁",
    "Parking auto" => "🅿️",
    "Produits de nettoyage" => "🧹",
    "Eau chaude" => "🚰",
    "Livres" => "📚",
    "Cuisine" => "🍳",
    "WiFi" => "📶",
    "Couverts de cuisine" => "🍴",
    "Climatisation" => "❄️",
    "Toilettes" => "🚽",
    "Lit" => "🛏️",
    "Savon" => "🧼",
    "Cheminée" => "🔥",
    "Mixeur" => "🔗",
    "Chauffage" => "🌡️",
    "Espace de rangement pour vêtements : placard" => "👕",
    "Télévision" => "📺",
];
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($annonce['title']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Inclure styles.css -->
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
    <!-- Inclure le fichier CSS corrigé -->
    <link rel="stylesheet" href="/HabitatConnect/details_annonce.css">
    <!-- Ajouter des styles pour la liste des périodes réservées -->
    <style>
        .booked-dates {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            color: #721c24;
            display: block !important;
            visibility: visible !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <span><?php echo htmlspecialchars($annonce['title']); ?></span>
            <div class="nav-links" id="nav-links">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'): ?>
                    <div class="message-icon" onclick="window.location.href='messages_client.php'">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="message-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="lang-selector">
                    <a href="?lang=fr"><?php echo $translations['french']; ?></a>
                    <a href="?lang=en"><?php echo $translations['english']; ?></a>
                </div>
            </div>
            <div class="menu-icon" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </div>
        </div>
        <div class="detail-container">
            <div class="detail-content">
                <div class="images">
                    <?php if (empty($images)): ?>
                        <p><?php echo $translations['no_image_available']; ?></p>
                    <?php else: ?>
                        <?php foreach ($images as $image): ?>
                            <img src="/HabitatConnect/<?php echo htmlspecialchars($image['photo']); ?>" alt="Image de l’annonce">
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p><strong><?php echo htmlspecialchars($annonce['title']); ?> - <?php echo htmlspecialchars($annonce['localisation'] ?? $translations['price_not_specified']); ?></strong></p>
                <p><?php echo $translations['max_traveler']; ?> <span class="highlight"><?php echo $translations['traveler_favorite']; ?></span></p>
                <p><?php echo $translations['popular_on_airbnb']; ?></p>
                <p><strong><?php echo $translations['host']; ?></strong> <a href="profil.php?id=<?php echo $annonce['proprietaire_id']; ?>" class="host-link"><?php echo htmlspecialchars($annonce['username']); ?></a></p>
                <p><strong><?php echo $translations['description']; ?></strong> <?php echo nl2br(htmlspecialchars($annonce['description'])); ?></p>
                <p><strong><?php echo $translations['price']; ?></strong> 
                    <?php 
                    if ($promotion) {
                        $discounted_price = $annonce['price'] * (1 - $promotion['discount_percentage'] / 100);
                        echo '<span style="text-decoration: line-through;">' . number_format($annonce['price'], 2) . ' €</span> ';
                        echo number_format($discounted_price, 2) . ' € <span class="promotion">(-' . $promotion['discount_percentage'] . '%)</span>';
                    } else {
                        echo $annonce['price'] ? number_format($annonce['price'], 2) . ' ' . $translations['price_per_night'] : $translations['price_not_specified'];
                    }
                    ?>
                </p>

                <h2><?php echo $translations['what_this_place_offers']; ?></h2>
                <?php if (!$has_equipments): ?>
                    <p><?php echo $translations['no_equipments']; ?></p>
                <?php else: ?>
                    <ul class="equipment-list">
                        <?php foreach ($equipements as $equipement): ?>
                            <li class="equipment-item">
                                <span class="icon"><?php echo isset($equipment_icons[$equipement['name']]) ? $equipment_icons[$equipement['name']] : '🔧'; ?></span>
                                <?php echo htmlspecialchars($equipement['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h2><?php echo $translations['similar_ads']; ?></h2>
                <?php if (empty($similar_annonces)): ?>
                    <p><?php echo $translations['no_similar_ads']; ?></p>
                <?php else: ?>
                    <?php foreach ($similar_annonces as $similar): ?>
                        <div class="annonce">
                            <p><strong><?php echo htmlspecialchars($similar['title']); ?></strong></p>
                            <p><strong><?php echo $translations['similar_location']; ?></strong> <?php echo htmlspecialchars($similar['localisation'] ?? $translations['price_not_specified']); ?></p>
                            <p><strong><?php echo $translations['similar_price_per_night']; ?></strong> <?php echo $similar['price'] ? number_format($similar['price'], 2) . ' €' : $translations['price_not_specified']; ?></p>
                            <p><strong><?php echo $translations['published_by']; ?></strong> <?php echo htmlspecialchars($similar['username']); ?></p>
                            <a href="details_annonce.php?id=<?php echo $similar['id']; ?>"><?php echo $translations['similar_see_details']; ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="detail-sidebar">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'): ?>
                    <div class="reservation-form">
                        <h2><?php echo $translations['booking']; ?></h2>
                        <?php if (!$is_available && $availability_message): ?>
                            <p class="error"><?php echo $availability_message; ?></p>
                        <?php endif; ?>
                        <?php if (!empty($success)): ?>
                            <?php foreach ($success as $msg): ?>
                                <p class="success"><?php echo $msg; ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="reserver">
                            <label><?php echo $translations['booking_start_date']; ?></label>
                            <input type="date" name="start_date" required>
                            <label><?php echo $translations['booking_end_date']; ?></label>
                            <input type="date" name="end_date">
                            <p><?php echo $translations['price_per_night']; ?>: 
                                <?php 
                                if ($promotion) {
                                    $discounted_price = $annonce['price'] * (1 - $promotion['discount_percentage'] / 100);
                                    echo '<span style="text-decoration: line-through;">' . number_format($annonce['price'], 2) . ' €</span> ';
                                    echo number_format($discounted_price, 2) . ' € <span class="promotion">(-' . $promotion['discount_percentage'] . '%)</span>';
                                } else {
                                    echo $annonce['price'] ? number_format($annonce['price'], 2) . ' €' : $translations['price_not_specified'];
                                }
                                ?>
                            </p>
                            <input type="submit" value="<?php echo $translations['book']; ?>">
                        </form>
                        <!-- Afficher les périodes réservées -->
                        <?php if (!empty($reservations)): ?>
                            <div class="booked-dates">
                                <p><strong>Périodes réservées :</strong></p>
                                <ul>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <li><?php echo htmlspecialchars($reservation['start_date']); ?> au <?php echo htmlspecialchars($reservation['end_date']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p>Aucune période réservée pour le moment.</p>
                        <?php endif; ?>
                    </div>
                    <div class="message-form">
                        <h2><?php echo $translations['send_private_message']; ?></h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="envoyer_message">
                            <label><?php echo $translations['your_message']; ?></label>
                            <textarea name="message" required></textarea>
                            <input type="submit" value="<?php echo $translations['send']; ?>">
                        </form>
                        <!-- Afficher uniquement le lien vers OpenStreetMap -->
                        <p><strong>Localisation :</strong> <?php echo htmlspecialchars($annonce['localisation'] ?? $translations['price_not_specified']); ?></p>
                        <p><a href="<?php echo $map_link_url; ?>" target="_blank">Voir sur OpenStreetMap</a></p>
                    </div>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <p><a href="connexion.php"><?php echo $translations['login_to_book']; ?></a></p>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="follow">
                        <button type="submit" class="follow-btn" <?php echo $is_following ? 'disabled' : ''; ?>>
                            <?php echo $is_following ? $translations['following'] : $translations['follow']; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <h2><?php echo $translations['comments']; ?></h2>
        <?php if (empty($commentaires)): ?>
            <p><?php echo $translations['no_comments']; ?></p>
        <?php else: ?>
            <div class="commentaires">
                <?php foreach ($commentaires as $commentaire): ?>
                    <div class="commentaire">
                        <p><strong><?php echo htmlspecialchars($commentaire['username']); ?> :</strong> <?php echo htmlspecialchars($commentaire['commentaire']); ?></p>
                        <p><strong><?php echo $translations['rating']; ?> :</strong> <span class="etoiles"><?php echo str_repeat('★', $commentaire['etoiles']); ?></span></p>
                        <p><strong><?php echo $translations['published_date']; ?> :</strong> <?php echo $commentaire['date_commentaire']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'): ?>
            <div class="comment-form">
                <h2><?php echo $translations['leave_comment']; ?></h2>
                <form method="POST">
                    <input type="hidden" name="action" value="ajouter_commentaire">
                    <label><?php echo $translations['your_comment']; ?></label>
                    <textarea name="commentaire" required></textarea>
                    <label><?php echo $translations['rating']; ?></label>
                    <select name="etoiles" required>
                        <option value="1">1 <?php echo $translations['star']; ?></option>
                        <option value="2">2 <?php echo $translations['stars']; ?></option>
                        <option value="3">3 <?php echo $translations['stars']; ?></option>
                        <option value="4">4 <?php echo $translations['stars']; ?></option>
                        <option value="5">5 <?php echo $translations['stars']; ?></option>
                    </select>
                    <input type="submit" value="<?php echo $translations['send']; ?>">
                </form>
            </div>
        <?php endif; ?>

        <p><a href="liste_annonces.php"><?php echo $translations['back_to_list']; ?></a></p>
    </div>

    <script>
        // Fonction pour afficher/masquer le menu
        function toggleMenu() {
            const navLinks = document.getElementById('nav-links');
            if (navLinks) {
                navLinks.classList.toggle('active');
            }
        }

        // Débogage : Vérifier si des réservations sont récupérées
        console.log('Réservations récupérées :', <?php echo json_encode($reservations); ?>);
    </script>
</body>
</html>