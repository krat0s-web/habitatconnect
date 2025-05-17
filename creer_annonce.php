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

// Vérifier si l’utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'proprietaire') {
    header("Location: connexion.php");
    exit;
}

// Vérifier si l’utilisateur existe dans la base
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_exists) {
        $errors[] = $translations['user_not_found'];
        header("Location: connexion.php");
        exit;
    }
} catch (PDOException $e) {
    $errors[] = $translations['error'] . " " . $e->getMessage();
    exit;
}

$errors = [];
$selected_equipments = isset($_POST['equipments']) ? (array)$_POST['equipments'] : [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $localisation = trim($_POST['localisation']);
    $type = $_POST['type'];
    $selected_equipments = isset($_POST['equipments']) ? (array)$_POST['equipments'] : [];

    // Validation des champs
    if (empty($title) || empty($description) || empty($price) || empty($localisation) || empty($type)) {
        $errors[] = $translations['fill_all_fields'];
    } elseif (!is_numeric($price) || $price <= 0) {
        $errors[] = $translations['invalid_price'];
    } elseif (empty($selected_equipments)) {
        $errors[] = $translations['equipments_required'];
    }

    if (empty($errors)) {
        try {
            // Insérer l’annonce
            $stmt = $pdo->prepare("INSERT INTO annonces (proprietaire_id, title, description, price, localisation, type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $description, $price, $localisation, $type]);
            $annonce_id = $pdo->lastInsertId();

            // Traiter les images de l’annonce
            if (isset($_FILES['annonce_photos']) && !empty($_FILES['annonce_photos']['name'][0])) {
                $annonce_photos = $_FILES['annonce_photos'];
                for ($i = 0; $i < count($annonce_photos['name']); $i++) {
                    if ($annonce_photos['error'][$i] == 0) {
                        $target_dir = "image/";
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0755, true);
                        }
                        $photo_name = time() . "_" . basename($annonce_photos['name'][$i]);
                        $photo_path = $target_dir . $photo_name;
                        if (move_uploaded_file($annonce_photos['tmp_name'][$i], $photo_path)) {
                            $stmt = $pdo->prepare("INSERT INTO annonce_images (annonce_id, photo) VALUES (?, ?)");
                            $stmt->execute([$annonce_id, $photo_path]);
                        } else {
                            $errors[] = $translations['photo_upload_failed'];
                        }
                    }
                }
            }

            // Insérer les équipements
            foreach ($selected_equipments as $equip_name) {
                $stmt = $pdo->prepare("INSERT INTO equipements (annonce_id, name, `condition`) VALUES (?, ?, ?)");
                $stmt->execute([$annonce_id, $equip_name, $translations['good_condition']]);
            }

            if (empty($errors)) {
                header("Location: liste_annonces.php");
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = $translations['error'] . " " . $e->getMessage();
        }
    }
}

// Liste des équipements disponibles
$available_equipments = [
    "Garage",
    "Rangement de bagages",
    "Piscine",
    "Salle de bain",
    "Parking auto",
    "Produits de nettoyage",
    "Eau chaude",
    "Livres",
    "Cuisine",
    "WiFi",
    "Couverts de cuisine",
    "Climatisation",
    "Toilettes",
    "Lit",
    "Savon",
    "Cheminée",
    "Mixeur",
    "Chauffage",
    "Espace de rangement pour vêtements : placard",
    "Télévision"
];
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations['create_ad']; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
    <style>
        #equipment-list {
            height: 150px;
        }
        .selected-equipments {
            margin-top: 10px;
        }
        .equipment-item {
            display: inline-block;
            background-color: #2c3e50;
            color: #ddd;
            padding: 6px 12px;
            border-radius: 15px;
            margin-right: 8px;
            margin-bottom: 8px;
            border: 1px solid #555;
            font-size: 0.9em;
        }
        .equipment-item button {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span><?php echo $translations['welcome']; ?></span>
        <div class="nav-links" id="nav-links">
            <a href="liste_annonces.php"><i class="fas fa-home"></i> <?php echo $translations['home']; ?></a>
            <a href="annonces_proprietaire.php"><i class="fas fa-list"></i> <?php echo $translations['manage_ads']; ?></a>
            <a href="profil.php"><i class="fas fa-user"></i> <?php echo $translations['my_profile']; ?></a>
            <a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i> <?php echo $translations['logout']; ?></a>
            <div class="lang-selector">
                <a href="?lang=fr"><?php echo $translations['french']; ?></a>
                <a href="?lang=en"><?php echo $translations['english']; ?></a>
            </div>
        </div>
        <div class="menu-icon" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </div>
    </div>

    <div class="container">
        <h1><?php echo $translations['create_ad']; ?></h1>
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <label><?php echo $translations['title']; ?></label>
            <input type="text" name="title" placeholder="<?php echo $translations['enter_ad_title']; ?>" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
            <label><?php echo $translations['description']; ?></label>
            <textarea name="description" placeholder="<?php echo $translations['enter_ad_description']; ?>" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            <label><?php echo $translations['location']; ?></label>
            <input type="text" name="localisation" placeholder="<?php echo $translations['enter_ad_location']; ?>" value="<?php echo isset($_POST['localisation']) ? htmlspecialchars($_POST['localisation']) : ''; ?>" required>
            <label><?php echo $translations['price_per_night_label']; ?></label>
            <input type="number" name="price" step="0.01" placeholder="<?php echo $translations['enter_price_per_night']; ?>" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
            <label><?php echo $translations['type']; ?></label>
            <select name="type" required>
                <option value="MAISON" <?php echo (isset($_POST['type']) && $_POST['type'] == 'MAISON') ? 'selected' : ''; ?>><?php echo $translations['house']; ?></option>
                <option value="APPARTEMENT" <?php echo (isset($_POST['type']) && $_POST['type'] == 'APPARTEMENT') ? 'selected' : ''; ?>><?php echo $translations['apartment']; ?></option>
                <option value="VILLA" <?php echo (isset($_POST['type']) && $_POST['type'] == 'VILLA') ? 'selected' : ''; ?>><?php echo $translations['villa']; ?></option>
            </select>
            <label><?php echo $translations['equipments']; ?></label>
            <select multiple name="equipments[]" id="equipment-list">
                <?php foreach ($available_equipments as $equip): ?>
                    <option value="<?php echo htmlspecialchars($equip); ?>" <?php echo in_array($equip, $selected_equipments) ? 'selected' : ''; ?>><?php echo htmlspecialchars($equip); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="selected-equipments" id="selected-equipments">
                <?php foreach ($selected_equipments as $equip): ?>
                    <div class="equipment-item">
                        <span><?php echo htmlspecialchars($equip); ?></span>
                        <button type="button" onclick="removeEquipment('<?php echo htmlspecialchars($equip); ?>')">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <label><?php echo $translations['ad_photos']; ?></label>
            <input type="file" name="annonce_photos[]" multiple>
            <input type="submit" value="<?php echo $translations['submit_ad']; ?>">
        </form>
    </div>

    <script>
        const equipmentList = document.getElementById('equipment-list');
        const selectedEquipmentsDiv = document.getElementById('selected-equipments');

        // Mettre à jour les équipements sélectionnés à chaque changement
        equipmentList.addEventListener('change', function() {
            selectedEquipmentsDiv.innerHTML = '';
            const selectedOptions = Array.from(this.selectedOptions).map(option => option.value);
            selectedOptions.forEach(equip => {
                const item = document.createElement('div');
                item.className = 'equipment-item';
                item.innerHTML = `
                    <span>${equip}</span>
                    <button type="button" onclick="removeEquipment('${equip}')">✕</button>
                `;
                selectedEquipmentsDiv.appendChild(item);
            });
        });

        // Supprimer un équipement de la sélection
        function removeEquipment(equip) {
            const options = Array.from(equipmentList.options);
            options.forEach(option => {
                if (option.value === equip) {
                    option.selected = false;
                }
            });
            selectedEquipmentsDiv.innerHTML = '';
            const selectedOptions = Array.from(equipmentList.selectedOptions).map(option => option.value);
            selectedOptions.forEach(equip => {
                const item = document.createElement('div');
                item.className = 'equipment-item';
                item.innerHTML = `
                    <span>${equip}</span>
                    <button type="button" onclick="removeEquipment('${equip}')">✕</button>
                `;
                selectedEquipmentsDiv.appendChild(item);
            });
        }

        function toggleMenu() {
            const navLinks = document.getElementById('nav-links');
            navLinks.classList.toggle('active');
        }
    </script>
</body>
</html>