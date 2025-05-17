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

// Récupérer les paramètres de filtrage
$localisation = isset($_GET['localisation']) ? trim($_GET['localisation']) : '';
$price_min = isset($_GET['price_min']) && is_numeric($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$price_max = isset($_GET['price_max']) && is_numeric($_GET['price_max']) ? (float)$_GET['price_max'] : PHP_INT_MAX;
$equipments = isset($_GET['equipments']) ? (array)$_GET['equipments'] : [];
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    // Récupérer les équipements disponibles pour le filtre
    $stmt_equip = $pdo->query("SELECT DISTINCT name FROM equipements ORDER BY name");
    $available_equipments = $stmt_equip->fetchAll(PDO::FETCH_COLUMN);

    // Construction de la requête SQL avec filtres, incluant les promotions
    $sql = "
        SELECT a.id, a.title, a.price, a.localisation, a.created_at, u.username,
               p.discount_percentage, p.start_date, p.end_date
        FROM annonces a 
        JOIN users u ON a.proprietaire_id = u.id
        LEFT JOIN promotions p ON a.id = p.annonce_id 
            AND p.start_date <= CURDATE() 
            AND p.end_date >= CURDATE()
    ";
    $params = [];
    $conditions = [];

    // Filtre par localisation
    if (!empty($localisation)) {
        $conditions[] = "a.localisation LIKE ?";
        $params[] = "%$localisation%";
    }

    // Filtre par prix
    if ($price_min > 0) {
        $conditions[] = "a.price >= ?";
        $params[] = $price_min;
    }
    if ($price_max < PHP_INT_MAX) {
        $conditions[] = "a.price <= ?";
        $params[] = $price_max;
    }

    // Filtre par équipements
    if (!empty($equipments)) {
        $placeholders = [];
        foreach ($equipments as $index => $equip) {
            $placeholders[] = "?";
            $params[] = $equip;
        }
        $sql .= " AND a.id IN (
            SELECT annonce_id 
            FROM equipements 
            WHERE name IN (" . implode(',', $placeholders) . ")
            GROUP BY annonce_id
            HAVING COUNT(DISTINCT name) = " . count($equipments) . "
        )";
    }

    // Filtre par dates disponibles
    if (!empty($start_date) && !empty($end_date)) {
        $conditions[] = "a.id NOT IN (
            SELECT annonce_id 
            FROM reservations 
            WHERE status = 'confirmed'
            AND NOT (end_date < ? OR start_date > ?)
        )";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY a.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les annonces recommandées (basées sur les vues ou les favoris)
    $recommended_annonces = [];
    if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'client') {
        $client_id = $_SESSION['user_id'];
        $sql_recommend = "
            SELECT a.id, a.title, a.price, a.localisation, u.username,
                   p.discount_percentage, p.start_date, p.end_date
            FROM annonces a
            JOIN users u ON a.proprietaire_id = u.id
            LEFT JOIN (
                SELECT annonce_id, COUNT(*) as view_count
                FROM views
                WHERE client_id = ?
                GROUP BY annonce_id
            ) v ON a.id = v.annonce_id
            LEFT JOIN likes l ON a.id = l.annonce_id AND l.client_id = ?
            LEFT JOIN promotions p ON a.id = p.annonce_id 
                AND p.start_date <= CURDATE() 
                AND p.end_date >= CURDATE()
            WHERE (v.annonce_id IS NOT NULL OR l.annonce_id IS NOT NULL)
            ORDER BY COALESCE(v.view_count, 0) DESC, l.annonce_id IS NOT NULL DESC
            LIMIT 3
        ";
        $stmt_recommend = $pdo->prepare($sql_recommend);
        $stmt_recommend->execute([$client_id, $client_id]);
        $recommended_annonces = $stmt_recommend->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations['find_next_stay']; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
    <style>
        /* Styles spécifiques pour liste_annonces.php */
        .welcome-banner {
            background: linear-gradient(90deg, rgba(52, 152, 219, 0.9), rgba(44, 62, 80, 0.9)), url('/HabitatConnect/images/welcome-bg.jpg');
            background-size: cover;
            background-position: center;
            color: #fff;
            text-align: center;
            padding: 80px 20px;
            margin-top: 60px; /* Espacement avec la navbar */
            margin-bottom: 40px;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .welcome-banner h1 {
            font-size: 3em;
            margin: 0 0 15px 0;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .welcome-banner p {
            font-size: 1.4em;
            margin: 0;
            opacity: 0.9;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }
        .carousel {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto 40px auto;
            overflow: hidden;
            position: relative;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .carousel-inner {
            display: flex;
            transition: transform 0.5s ease;
            width: 400%; /* 4 images */
        }
        .carousel-item {
            width: 25%; /* 1/4 de la largeur totale */
            flex-shrink: 0;
            position: relative;
        }
        .carousel-item img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }
        .filter-toggle {
            max-width: 1200px;
            margin: 0 auto 20px auto;
            text-align: center;
        }
        .filter-toggle button {
            background-color: #3498db;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .filter-toggle button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .filter-form {
            max-width: 1200px;
            margin: 0 auto 40px auto;
            padding: 15px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: none; /* Caché par défaut */
            flex-direction: row !important; /* Forcer l'affichage horizontal */
            flex-wrap: nowrap !important; /* Empêche le retour à la ligne */
            gap: 15px;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
            overflow-x: auto; /* Ajouter un défilement horizontal si nécessaire */
            white-space: nowrap; /* Empêche le retour à la ligne */
        }
        .filter-form.active {
            display: flex !important; /* Affiché lorsque la classe active est ajoutée */
        }
        .filter-form label {
            margin-right: 5px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9em;
            white-space: nowrap; /* Empêche le texte de se diviser */
        }
        .filter-form input, .filter-form select {
            padding: 8px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.9em;
            width: 120px; /* Réduire la largeur pour éviter le débordement */
            transition: border-color 0.3s ease;
            flex-shrink: 0; /* Empêche les champs de se rétrécir */
        }
        .filter-form input:focus, .filter-form select:focus {
            border-color: #3498db;
            outline: none;
        }
        .filter-form select[multiple] {
            height: 80px;
            width: 150px; /* Réduire la largeur pour éviter le débordement */
            flex-shrink: 0;
        }
        .filter-form button {
            padding: 8px 20px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            flex-shrink: 0;
        }
        .filter-form button:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .promotion {
            color: #e74c3c;
            font-weight: 600;
            font-size: 0.9em;
        }
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column !important; /* Vertical sur petits écrans */
                align-items: stretch;
                padding: 15px;
                overflow-x: hidden; /* Désactiver le défilement horizontal sur petits écrans */
                white-space: normal; /* Permettre le retour à la ligne */
            }
            .filter-form label, .filter-form input, .filter-form select, .filter-form button {
                margin: 5px 0;
                width: 100%;
            }
            .carousel-item img {
                height: 250px;
            }
            .welcome-banner {
                padding: 50px 20px;
            }
            .welcome-banner h1 {
                font-size: 2em;
            }
            .welcome-banner p {
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span><?php echo $translations['welcome']; ?></span>
        <div class="nav-links" id="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] == 'proprietaire'): ?>
                    <a href="creer_annonce.php"><i class="fas fa-plus-circle"></i> <?php echo $translations['create_ad']; ?></a>
                    <a href="annonces_proprietaire.php"><i class="fas fa-list"></i> <?php echo $translations['manage_ads']; ?></a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] == 'client'): ?>
                    <a href="messages_client.php"><i class="fas fa-bookmark"></i> <?php echo $translations['my_bookings']; ?></a>
                <?php endif; ?>
                <a href="profil.php"><i class="fas fa-user"></i> <?php echo $translations['my_profile']; ?></a>
                <a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i> <?php echo $translations['logout']; ?></a>
            <?php else: ?>
                <a href="connexion.php"><i class="fas fa-sign-in-alt"></i> <?php echo $translations['login']; ?></a>
                <a href="inscription.php"><i class="fas fa-user-plus"></i> <?php echo $translations['register']; ?></a>
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

    <div class="welcome-banner">
        <h1><?php echo $translations['find_next_stay']; ?></h1>
        <p><?php echo $translations['search_offers']; ?></p>
    </div>

    <div class="carousel">
        <div class="carousel-inner">
            <div class="carousel-item">
                <img src="/HabitatConnect/images/carousel1.png" alt="Image 1">
            </div>
            <div class="carousel-item">
                <img src="/HabitatConnect/images/carousel2.png" alt="Image 2">
            </div>
            <div class="carousel-item">
                <img src="/HabitatConnect/images/carousel3.png" alt="Image 3">
            </div>
            <div class="carousel-item">
                <img src="/HabitatConnect/images/carousel4.png" alt="Image 4">
            </div>
        </div>
    </div>

    <div class="filter-toggle">
        <button onclick="toggleFilterForm()"><?php echo $translations['filter']; ?> <i class="fas fa-filter"></i></button>
    </div>

    <div class="filter-form" id="filter-form">
        <form method="GET" action="">
            <label for="localisation"><?php echo $translations['location']; ?></label>
            <input type="text" id="localisation" name="localisation" value="<?php echo htmlspecialchars($localisation); ?>" placeholder="<?php echo $translations['location_placeholder']; ?>">
            <label for="price_min"><?php echo $translations['price_min']; ?></label>
            <input type="number" id="price_min" name="price_min" value="<?php echo $price_min > 0 ? htmlspecialchars($price_min) : ''; ?>" min="0" placeholder="<?php echo $translations['price_min_placeholder']; ?>">
            <label for="price_max"><?php echo $translations['price_max']; ?></label>
            <input type="number" id="price_max" name="price_max" value="<?php echo $price_max < PHP_INT_MAX ? htmlspecialchars($price_max) : ''; ?>" min="0" placeholder="<?php echo $translations['price_max_placeholder']; ?>">
            <label for="start_date"><?php echo $translations['start_date']; ?></label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <label for="end_date"><?php echo $translations['end_date']; ?></label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            <label for="equipments"><?php echo $translations['equipments']; ?></label>
            <select multiple id="equipments" name="equipments[]">
                <?php foreach ($available_equipments as $equip): ?>
                    <option value="<?php echo htmlspecialchars($equip); ?>" <?php echo in_array($equip, $equipments) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($equip); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><?php echo $translations['filter']; ?></button>
        </form>
    </div>

    <div class="grid-container">
        <?php if (empty($annonces)): ?>
            <p><?php echo $translations['no_ads_found']; ?></p>
        <?php else: ?>
            <?php foreach ($annonces as $annonce): ?>
                <?php
                $stmt_img = $pdo->prepare("SELECT photo FROM annonce_images WHERE annonce_id = ? LIMIT 1");
                $stmt_img->execute([$annonce['id']]);
                $image = $stmt_img->fetch(PDO::FETCH_ASSOC);
                $image_path = $image && $image['photo'] ? "/HabitatConnect/" . htmlspecialchars($image['photo']) : "/HabitatConnect/image/default.jpg";
                ?>
                <a href="details_annonce.php?id=<?php echo $annonce['id']; ?>" class="annonce-card">
                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($annonce['title']); ?>">
                    <div class="annonce-details">
                        <h2><?php echo htmlspecialchars($annonce['title']); ?></h2>
                        <p><?php echo htmlspecialchars($annonce['localisation'] ?? $translations['price_not_specified']); ?></p>
                        <p>
                            <?php 
                            if (isset($annonce['discount_percentage']) && $annonce['discount_percentage'] !== null) {
                                $discounted_price = $annonce['price'] * (1 - $annonce['discount_percentage'] / 100);
                                echo '<span style="text-decoration: line-through;">' . number_format($annonce['price'], 2) . ' €</span> ';
                                echo number_format($discounted_price, 2) . ' € <span class="promotion">(-' . $annonce['discount_percentage'] . '%)</span>';
                            } else {
                                echo $annonce['price'] ? number_format($annonce['price'], 2) . ' ' . $translations['price_per_night'] : $translations['price_not_specified'];
                            }
                            ?>
                        </p>
                        <p><strong><?php echo $translations['published_by']; ?></strong> <?php echo htmlspecialchars($annonce['username']); ?></p>
                        <p><strong><?php echo $translations['published_date']; ?></strong> <?php echo $annonce['created_at']; ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($recommended_annonces)): ?>
        <div class="grid-container">
            <h2><?php echo $translations['similar_ads']; ?></h2>
            <?php foreach ($recommended_annonces as $recommended): ?>
                <?php
                $stmt_img = $pdo->prepare("SELECT photo FROM annonce_images WHERE annonce_id = ? LIMIT 1");
                $stmt_img->execute([$recommended['id']]);
                $image = $stmt_img->fetch(PDO::FETCH_ASSOC);
                $image_path = $image && $image['photo'] ? "/HabitatConnect/" . htmlspecialchars($image['photo']) : "/HabitatConnect/image/default.jpg";
                ?>
                <a href="details_annonce.php?id=<?php echo $recommended['id']; ?>" class="annonce-card">
                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($recommended['title']); ?>">
                    <div class="annonce-details">
                        <h2><?php echo htmlspecialchars($recommended['title']); ?></h2>
                        <p><?php echo htmlspecialchars($recommended['localisation'] ?? $translations['price_not_specified']); ?></p>
                        <p>
                            <?php 
                            if (isset($recommended['discount_percentage']) && $recommended['discount_percentage'] !== null) {
                                $discounted_price = $recommended['price'] * (1 - $recommended['discount_percentage'] / 100);
                                echo '<span style="text-decoration: line-through;">' . number_format($recommended['price'], 2) . ' €</span> ';
                                echo number_format($discounted_price, 2) . ' € <span class="promotion">(-' . $recommended['discount_percentage'] . '%)</span>';
                            } else {
                                echo $recommended['price'] ? number_format($recommended['price'], 2) . ' ' . $translations['price_per_night'] : $translations['price_not_specified'];
                            }
                            ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentSlide = 0;
            const slides = document.querySelectorAll('.carousel-item');
            const totalSlides = slides.length;

            function showSlide(index) {
                if (index >= totalSlides) currentSlide = 0;
                else if (index < 0) currentSlide = totalSlides - 1;
                else currentSlide = index;

                const offset = -currentSlide * 25; // 25% par slide (100% / 4)
                const carouselInner = document.querySelector('.carousel-inner');
                if (carouselInner) {
                    carouselInner.style.transform = `translateX(${offset}%)`;
                }
            }

            function nextSlide() {
                showSlide(currentSlide + 1);
            }

            // Changer toutes les 4 secondes (4000 ms)
            setInterval(nextSlide, 4000);

            // Démarrer le carrousel
            showSlide(currentSlide);

            // Fonction pour afficher/masquer le menu
            window.toggleMenu = function() {
                const navLinks = document.getElementById('nav-links');
                if (navLinks) {
                    navLinks.classList.toggle('active');
                }
            };

            // Fonction pour afficher/masquer le formulaire de filtrage
            window.toggleFilterForm = function() {
                const filterForm = document.getElementById('filter-form');
                if (filterForm) {
                    filterForm.classList.toggle('active');
                }
            };
        });
    </script>
</body>
</html>