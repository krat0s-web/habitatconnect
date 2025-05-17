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

// Si l'utilisateur est déjà connecté, rediriger vers liste_annonces.php
if (isset($_SESSION['user_id'])) {
    header("Location: liste_annonces.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = $translations['fill_all_fields'];
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: liste_annonces.php");
                exit;
            } else {
                $errors[] = $translations['invalid_credentials'];
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations['login']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
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
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
        }
        .error {
            color: #dc3545;
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .register-link, .back-link {
            text-align: center;
            margin-top: 10px;
        }
        .register-link a, .back-link a {
            color: #007bff;
            text-decoration: none;
        }
        .register-link a:hover, .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span><?php echo $translations['welcome']; ?></span>
        <div class="nav-links">
            <a href="inscription.php"><?php echo $translations['register']; ?></a>
            <div class="lang-selector">
                <a href="?lang=fr"><?php echo $translations['french']; ?></a>
                <a href="?lang=en"><?php echo $translations['english']; ?></a>
            </div>
        </div>
    </div>

    <div class="container">
        <h2><?php echo $translations['login']; ?></h2>
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
        <form method="POST">
            <label for="email"><?php echo $translations['email']; ?></label>
            <input type="email" id="email" name="email" required>
            <label for="password"><?php echo $translations['password']; ?></label>
            <input type="password" id="password" name="password" required>
            <input type="submit" value="<?php echo $translations['login']; ?>">
        </form>
        <div class="register-link">
            <p><?php echo $translations['no_account']; ?> <a href="inscription.php"><?php echo $translations['register_here']; ?></a></p>
        </div>
        <div class="back-link">
            <p><a href="liste_annonces.php"><?php echo $translations['back_to_list']; ?></a></p>
        </div>
    </div>
</body>
</html>