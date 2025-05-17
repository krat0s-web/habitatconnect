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

if (!isset($_GET['id']) || !isset($_SESSION['user_id']) || $_SESSION['role'] != 'proprietaire') {
    header("Location: liste_annonces.php");
    exit;
}

$annonce_id = $_GET['id'];

try {
    // Récupérer les détails de l’annonce
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.description, a.price, a.localisation, a.type 
        FROM annonces a 
        WHERE a.id = ? AND a.proprietaire_id = ?
    ");
    $stmt->execute([$annonce_id, $_SESSION['user_id']]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        header("Location: liste_annonces.php");
        exit;
    }

    // Récupérer les équipements actuels
    $stmt_equip = $pdo->prepare("SELECT name FROM equipements WHERE annonce_id = ?");
    $stmt_equip->execute([$annonce_id]);
    $current_equipments = $stmt_equip->fetchAll(PDO::FETCH_ASSOC);
    $current_equipment_names = array_column($current_equipments, 'name');

    // Liste complète des équipements disponibles
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

    // Gestion de l’ajout d’une promotion
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_promotion'])) {
        $discount_percentage = $_POST['discount_percentage'];
        $promo_start_date = $_POST['promo_start_date'];
        $promo_end_date = $_POST['promo_end_date'];

        // Validation des dates
        if (strtotime($promo_start_date) >= strtotime($promo_end_date)) {
            $errors[] = "La date de fin doit être postérieure à la date de début.";
        } elseif ($discount_percentage <= 0 || $discount_percentage > 100) {
            $errors[] = "Le pourcentage de réduction doit être compris entre 0 et 100.";
        } else {
            try {
                $stmt_promo = $pdo->prepare("
                    INSERT INTO promotions (annonce_id, discount_percentage, start_date, end_date)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_promo->execute([$annonce_id, $discount_percentage, $promo_start_date, $promo_end_date]);
                $success[] = "Promotion ajoutée avec succès !";
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de l’ajout de la promotion : " . $e->getMessage();
            }
        }
    }

    // Récupérer les promotions existantes
    $stmt_promos = $pdo->prepare("SELECT * FROM promotions WHERE annonce_id = ?");
    $stmt_promos->execute([$annonce_id]);
    $promotions = $stmt_promos->fetchAll(PDO::FETCH_ASSOC);

    // Gestion de la mise à jour de l’annonce
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['add_promotion'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $localisation = $_POST['localisation'];
        $type = $_POST['type'];
        $equipments = isset($_POST['equipments']) ? (array)$_POST['equipments'] : [];

        try {
            // Mettre à jour l’annonce
            $stmt_update = $pdo->prepare("
                UPDATE annonces 
                SET title = ?, description = ?, price = ?, localisation = ?, type = ? 
                WHERE id = ? AND proprietaire_id = ?
            ");
            $stmt_update->execute([$title, $description, $price, $localisation, $type, $annonce_id, $_SESSION['user_id']]);

            // Supprimer les anciens équipements
            $stmt_delete = $pdo->prepare("DELETE FROM equipements WHERE annonce_id = ?");
            $stmt_delete->execute([$annonce_id]);

            // Insérer les nouveaux équipements
            foreach ($equipments as $equip_name) {
                $stmt_insert = $pdo->prepare("INSERT INTO equipements (annonce_id, name, `condition`) VALUES (?, ?, ?)");
                $stmt_insert->execute([$annonce_id, $equip_name, 'Bon état']);
            }

            header("Location: details_annonce.php?id=$annonce_id");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la mise à jour de l’annonce : " . $e->getMessage();
        }
    }

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier une annonce</title>
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
    <style>
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: #34495e;
            color: #fff;
        }
        .navbar span {
            font-size: 1.2em;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .nav-links a {
            color: #fff;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 3px;
            transition: background-color 0.3s ease;
        }
        .nav-links a:hover {
            background-color: #2c3e50;
        }
        .lang-selector {
            display: flex;
            gap: 10px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 20px auto;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="number"], input[type="date"], textarea, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        select[multiple] {
            height: 150px;
        }
        .selected-equipments {
            margin-top: 10px;
        }
        .equipment-item-selected {
            display: inline-block;
            background-color: #2c3e50;
            color: #ddd;
            padding: 5px 10px;
            border-radius: 15px;
            margin-right: 5px;
            margin-bottom: 5px;
            border: 1px solid #555;
        }
        .equipment-item-selected button {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            margin-left: 5px;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .error {
            color: #dc3545;
            margin-bottom: 10px;
        }
        .success {
            color: #28a745;
            margin-bottom: 10px;
        }
        @media (max-width: 600px) {
            .nav-links {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Gérer mes annonces</span>
        <div class="nav-links">
            <a href="profil.php">Mon profil</a>
            <a href="deconnexion.php">Se déconnecter</a>
            <div class="lang-selector">
                <a href="?lang=fr">Français</a>
                <a href="?lang=en">English</a>
            </div>
        </div>
    </div>

    <div class="container">
        <h2>Modifier une annonce</h2>
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
        <form method="POST">
            <label for="title">Titre</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($annonce['title']); ?>" required>

            <label for="description">Description</label>
            <textarea id="description" name="description" required><?php echo htmlspecialchars($annonce['description']); ?></textarea>

            <label for="localisation">Adresse du logement</label>
            <input type="text" id="localisation" name="localisation" value="<?php echo htmlspecialchars($annonce['localisation']); ?>" required>

            <label for="price">Prix par nuit (€)</label>
            <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($annonce['price']); ?>" required>

            <label for="type">Type</label>
            <select id="type" name="type" required>
                <option value="MAISON" <?php echo $annonce['type'] == 'MAISON' ? 'selected' : ''; ?>>MAISON</option>
                <option value="APPARTEMENT" <?php echo $annonce['type'] == 'APPARTEMENT' ? 'selected' : ''; ?>>APPARTEMENT</option>
                <option value="VILLA" <?php echo $annonce['type'] == 'VILLA' ? 'selected' : ''; ?>>VILLA</option>
            </select>

            <label for="equipments">Équipements</label>
            <select multiple name="equipments[]" id="equipment-list-update">
                <?php foreach ($available_equipments as $equip): ?>
                    <option value="<?php echo htmlspecialchars($equip); ?>" <?php echo in_array($equip, $current_equipment_names) ? 'selected' : ''; ?>><?php echo htmlspecialchars($equip); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="selected-equipments" id="selected-equipments-update">
                <?php foreach ($current_equipment_names as $equip): ?>
                    <div class="equipment-item-selected">
                        <span><?php echo htmlspecialchars($equip); ?></span>
                        <button type="button" onclick="removeEquipmentUpdate('<?php echo htmlspecialchars($equip); ?>')">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <label for="photos">Photos de l’annonce</label>
            <input type="file" name="annonce_photos[]" multiple>

            <input type="submit" value="Mettre à jour">
        </form>

        <h3>Gérer les promotions</h3>
        <form method="POST">
            <input type="hidden" name="add_promotion" value="1">
            <label for="discount_percentage">Pourcentage de réduction (%)</label>
            <input type="number" id="discount_percentage" name="discount_percentage" min="0" max="100" step="0.01" required>
            <label for="promo_start_date">Date de début</label>
            <input type="date" id="promo_start_date" name="promo_start_date" required>
            <label for="promo_end_date">Date de fin</label>
            <input type="date" id="promo_end_date" name="promo_end_date" required>
            <input type="submit" value="Ajouter la promotion">
        </form>

        <?php if (!empty($promotions)): ?>
            <h4>Promotions existantes</h4>
            <ul>
                <?php foreach ($promotions as $promo): ?>
                    <li>
                        Réduction de <?php echo $promo['discount_percentage']; ?>% du <?php echo $promo['start_date']; ?> au <?php echo $promo['end_date']; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <script>
        const equipmentListUpdate = document.getElementById('equipment-list-update');
        const selectedEquipmentsUpdateDiv = document.getElementById('selected-equipments-update');

        equipmentListUpdate.addEventListener('change', function() {
            selectedEquipmentsUpdateDiv.innerHTML = '';
            const selectedOptions = Array.from(this.selectedOptions).map(option => option.value);
            selectedOptions.forEach(equip => {
                const item = document.createElement('div');
                item.className = 'equipment-item-selected';
                item.innerHTML = `
                    <span>${equip}</span>
                    <button type="button" onclick="removeEquipmentUpdate('${equip}')">✕</button>
                `;
                selectedEquipmentsUpdateDiv.appendChild(item);
            });
        });

        function removeEquipmentUpdate(equip) {
            const options = Array.from(equipmentListUpdate.options);
            options.forEach(option => {
                if (option.value === equip) {
                    option.selected = false;
                }
            });
            selectedEquipmentsUpdateDiv.innerHTML = '';
            const selectedOptions = Array.from(equipmentListUpdate.selectedOptions).map(option => option.value);
            selectedOptions.forEach(equip => {
                const item = document.createElement('div');
                item.className = 'equipment-item-selected';
                item.innerHTML = `
                    <span>${equip}</span>
                    <button type="button" onclick="removeEquipmentUpdate('${equip}')">✕</button>
                `;
                selectedEquipmentsUpdateDiv.appendChild(item);
            });
        }
    </script>
</body>
</html>