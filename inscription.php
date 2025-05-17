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
$success = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $errors[] = $translations['fill_all_fields'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $translations['invalid_email'];
    } elseif (strlen($password) < 6) {
        $errors[] = $translations['password_too_short'];
    } elseif (!in_array($role, ['client', 'proprietaire'])) {
        $errors[] = $translations['invalid_role'];
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = $translations['email_exists'];
            } else {
                // Insérer l'utilisateur dans la base de données
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role]);
                $success[] = $translations['registration_success'];
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
    <title><?php echo $translations['register']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
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
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .error {
            color: #dc3545;
            margin-bottom: 10px;
        }
        .success {
            color: #28a745;
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
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
        .login-link {
            text-align: center;
            margin-top: 10px;
        }
        .login-link a {
            color: #007bff;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span><?php echo $translations['welcome']; ?></span>
        <div class="nav-links">
            <a href="connexion.php"><?php echo $translations['login']; ?></a>
            <div class="lang-selector">
                <a href="?lang=fr"><?php echo $translations['french']; ?></a>
                <a href="?lang=en"><?php echo $translations['english']; ?></a>
            </div>
        </div>
    </div>

    <div class="container">
        <h2><?php echo $translations['register']; ?></h2>
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
            <label for="username"><?php echo $translations['username']; ?></label>
            <input type="text" id="username" name="username" required>
            <label for="email"><?php echo $translations['email']; ?></label>
            <input type="email" id="email" name="email" required>
            <label for="password"><?php echo $translations['password']; ?></label>
            <input type="password" id="password" name="password" required>
            <label for="role"><?php echo $translations['role']; ?></label>
            <select id="role" name="role" required>
                <option value="client"><?php echo $translations['client']; ?></option>
                <option value="proprietaire"><?php echo $translations['proprietaire']; ?></option>
            </select>
            <input type="submit" value="<?php echo $translations['register']; ?>">
        </form>
        <div class="login-link">
            <p><?php echo $translations['already_have_account']; ?> <a href="connexion.php"><?php echo $translations['login_here']; ?></a></p>
        </div>
    </div>
</body>
</html>