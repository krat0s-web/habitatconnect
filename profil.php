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

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = [];

try {
    // Récupérer les informations de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: connexion.php");
        exit;
    }

    $is_proprietaire = $user['role'] === 'proprietaire';

    // Récupérer le profil affiché (soit l'utilisateur connecté, soit un autre via id)
    $profile_user = $user;
    if (isset($_GET['id']) && $_GET['id'] != $user_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile_user) {
            header("Location: liste_annonces.php");
            exit;
        }
    }

    // Compter le nombre d'abonnements pour un client
    $subscription_count = 0;
    if ($profile_user['role'] === 'client') {
        $stmt_subscriptions = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE client_id = ?");
        $stmt_subscriptions->execute([$profile_user['id']]);
        $subscription_count = $stmt_subscriptions->fetchColumn();
    }

    // Pour un propriétaire : récupérer les annonces et statistiques
    $annonces = [];
    $follower_count = 0;
    $announce_count = 0;
    $like_count = 0;
    if ($profile_user['role'] === 'proprietaire') {
        $stmt = $pdo->prepare("SELECT a.* FROM annonces a WHERE proprietaire_id = ? ORDER BY created_at DESC");
        $stmt->execute([$profile_user['id']]);
        $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_followers = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE proprietaire_id = ?");
        $stmt_followers->execute([$profile_user['id']]);
        $follower_count = $stmt_followers->fetchColumn();

        $stmt_announce_count = $pdo->prepare("SELECT COUNT(*) FROM annonces WHERE proprietaire_id = ?");
        $stmt_announce_count->execute([$profile_user['id']]);
        $announce_count = $stmt_announce_count->fetchColumn();

        $stmt_like_count = $pdo->prepare("
            SELECT COUNT(*) 
            FROM likes l 
            JOIN annonces a ON l.annonce_id = a.id 
            WHERE a.proprietaire_id = ?
        ");
        $stmt_like_count->execute([$profile_user['id']]);
        $like_count = $stmt_like_count->fetchColumn();
    }

    // Récupérer les images pour chaque annonce
    $annonce_images = [];
    foreach ($annonces as $index => $annonce) {
        $stmt_images = $pdo->prepare("SELECT photo FROM annonce_images WHERE annonce_id = ? LIMIT 1");
        $stmt_images->execute([$annonce['id']]);
        $annonce_images[$index] = $stmt_images->fetchColumn() ?: 'images/default.jpg'; // Image par défaut si aucune photo
    }

    // Gérer le suivi (pour les clients)
    $is_following = false;
    if (!$is_proprietaire && isset($_GET['id']) && $_GET['id'] != $user_id && $profile_user['role'] === 'proprietaire') {
        $stmt = $pdo->prepare("SELECT * FROM followers WHERE client_id = ? AND proprietaire_id = ?");
        $stmt->execute([$user_id, $_GET['id']]);
        $is_following = $stmt->fetch() ? true : false;

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'follow' && !$is_following) {
                $proprietaire_id = $_GET['id'];
                if ($proprietaire_id && $proprietaire_id != $user_id) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO followers (client_id, proprietaire_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $proprietaire_id]);
                    $success[] = $translations['follow_success'];
                    header("Refresh:0"); // Recharge automatique
                }
            } elseif ($_POST['action'] === 'unfollow' && $is_following) {
                $proprietaire_id = $_GET['id'];
                if ($proprietaire_id && $proprietaire_id != $user_id) {
                    $stmt = $pdo->prepare("DELETE FROM followers WHERE client_id = ? AND proprietaire_id = ?");
                    $stmt->execute([$user_id, $proprietaire_id]);
                    $success[] = $translations['unfollow_success'];
                    header("Refresh:0"); // Recharge automatique
                }
            }
        }
    }

    // Gérer les likes (pour les clients) avec toggle
    $likes = [];
    if (!$is_proprietaire && isset($_GET['id']) && $profile_user['role'] === 'proprietaire') {
        $stmt = $pdo->prepare("SELECT annonce_id FROM likes WHERE client_id = ?");
        $stmt->execute([$user_id]);
        $likes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'like') {
            $annonce_id = $_POST['annonce_id'] ?? null;
            if ($annonce_id) {
                $stmt_check = $pdo->prepare("SELECT * FROM likes WHERE client_id = ? AND annonce_id = ?");
                $stmt_check->execute([$user_id, $annonce_id]);
                if ($stmt_check->fetch()) {
                    // Si déjà aimé, supprimer le like (déslike)
                    $stmt = $pdo->prepare("DELETE FROM likes WHERE client_id = ? AND annonce_id = ?");
                    $stmt->execute([$user_id, $annonce_id]);
                    $success[] = $translations['unlike_success'];
                } else {
                    // Sinon, ajouter le like
                    $stmt = $pdo->prepare("INSERT INTO likes (client_id, annonce_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $annonce_id]);
                    $success[] = $translations['like_success'];
                }
                header("Refresh:0"); // Recharge automatique
            }
        }
    }

    // Compter les likes pour chaque annonce
    $annonce_likes = [];
    if (!empty($annonces)) {
        foreach ($annonces as $annonce) {
            $stmt_like = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE annonce_id = ?");
            $stmt_like->execute([$annonce['id']]);
            $annonce_likes[$annonce['id']] = $stmt_like->fetchColumn();
        }
    }

    // Mettre à jour le profil (pour l'utilisateur connecté)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile']) && $user_id == ($profile_user['id'] ?? null)) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $bio = trim($_POST['bio'] ?? '');

        // Gestion de la photo de profil
        $profile_photo = $user['profile_photo'];
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['name']) {
            $target_dir = "images/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $profile_photo = time() . "_" . basename($_FILES['profile_photo']['name']);
            $target_file = $target_dir . $profile_photo;
            move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file);
        }

        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, bio = ?, profile_photo = ? WHERE id = ?");
        $stmt->execute([$username, $email, $phone, $bio, $profile_photo, $user_id]);
        $success[] = $translations['profile_updated'];
        header("Refresh:0"); // Recharge automatique
    }

    // Mettre à jour le mot de passe
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($current_password, $current_user['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success[] = $translations['password_updated'];
            } else {
                $errors[] = $translations['password_mismatch'];
            }
        } else {
            $errors[] = $translations['current_password_incorrect'];
        }
    }

} catch (PDOException $e) {
    $errors[] = $translations['error'] . " " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations['profile_of'] . " " . htmlspecialchars($profile_user['username']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/HabitatConnect/styles.css">
</head>
<body>
    <div class="navbar">
        <span><?php echo $translations['welcome']; ?></span>
        <div class="nav-links" id="nav-links">
            <a href="liste_annonces.php"><i class="fas fa-home"></i> <?php echo $translations['home']; ?></a>
            <?php if ($_SESSION['role'] == 'proprietaire'): ?>
                <a href="creer_annonce.php"><i class="fas fa-plus-circle"></i> <?php echo $translations['create_ad']; ?></a>
                <a href="annonces_proprietaire.php"><i class="fas fa-list"></i> <?php echo $translations['manage_ads']; ?></a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] == 'client'): ?>
                <a href="messages_client.php"><i class="fas fa-bookmark"></i> <?php echo $translations['my_bookings']; ?></a>
            <?php endif; ?>
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
        <h1><?php echo $translations['profile_of'] . " " . htmlspecialchars($profile_user['username']); ?></h1>

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

        <div class="profile-info">
            <div class="profile-photo">
                <?php if ($profile_user['profile_photo']): ?>
                    <img src="images/<?php echo htmlspecialchars($profile_user['profile_photo']); ?>" alt="<?php echo $translations['profile_photo']; ?>">
                <?php else: ?>
                    <p><?php echo $translations['no_profile_photo']; ?></p>
                <?php endif; ?>
            </div>
            <p><strong><?php echo $translations['name']; ?>:</strong> <?php echo htmlspecialchars($profile_user['username']); ?></p>
            <p><strong><?php echo $translations['email']; ?>:</strong> <?php echo htmlspecialchars($profile_user['email']); ?></p>
            <p><strong><?php echo $translations['bio']; ?>:</strong> <?php echo htmlspecialchars($profile_user['bio'] ?? $translations['no_bio']); ?></p>
            <p><strong><?php echo $translations['phone']; ?>:</strong> <?php echo htmlspecialchars($profile_user['phone'] ?? $translations['not_available']); ?></p>

            <?php if ($profile_user['role'] === 'client'): ?>
                <div class="client-stats">
                    <span><?php echo $translations['subscriptions']; ?>: <?php echo $subscription_count; ?></span>
                </div>
            <?php elseif ($profile_user['role'] === 'proprietaire'): ?>
                <div class="stats">
                    <span><?php echo $translations['followers']; ?>: <?php echo $follower_count; ?></span>
                    <span><?php echo $translations['ads']; ?>: <?php echo $announce_count; ?></span>
                    <span><?php echo $translations['likes']; ?>: <?php echo $like_count; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($user_id == ($profile_user['id'] ?? null)): ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                <h2><?php echo $translations['account_info']; ?></h2>
                <p><?php echo $translations['update_your_profile_info']; ?></p>
                <label><?php echo $translations['name']; ?></label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                <label><?php echo $translations['email']; ?></label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                <label><?php echo $translations['phone_number']; ?></label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                <label><?php echo $translations['bio']; ?></label>
                <textarea name="bio"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                <label><?php echo $translations['profile_photo']; ?></label>
                <input type="file" name="profile_photo">
                <input type="submit" value="<?php echo $translations['save']; ?>">
            </form>

            <form method="POST">
                <input type="hidden" name="update_password" value="1">
                <h2><?php echo $translations['update_password']; ?></h2>
                <p><?php echo $translations['password_security_tip']; ?></p>
                <label><?php echo $translations['current_password']; ?></label>
                <input type="password" name="current_password" required>
                <label><?php echo $translations['new_password']; ?></label>
                <input type="password" name="new_password" required>
                <label><?php echo $translations['confirm_new_password']; ?></label>
                <input type="password" name="confirm_password" required>
                <input type="submit" value="<?php echo $translations['confirm']; ?>">
            </form>
        <?php endif; ?>

        <?php if (!$is_proprietaire && isset($_GET['id']) && $_GET['id'] != $user_id && $profile_user['role'] === 'proprietaire'): ?>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $is_following ? 'unfollow' : 'follow'; ?>">
                <?php if ($is_following): ?>
                    <button type="submit" class="unfollow-btn"><?php echo $translations['unfollow']; ?></button>
                <?php else: ?>
                    <button type="submit" class="follow-btn"><?php echo $translations['follow']; ?></button>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($profile_user['role'] === 'proprietaire'): ?>
            <h2><?php echo $translations['ads_by'] . " " . htmlspecialchars($profile_user['username']); ?></h2>
            <?php if (!empty($annonces)): ?>
                <?php foreach ($annonces as $index => $annonce): ?>
                    <div class="announce-item">
                        <div class="announce-image">
                            <?php if (isset($annonce_images[$index])): ?>
                                <img src="/HabitatConnect/<?php echo htmlspecialchars($annonce_images[$index]); ?>" alt="<?php echo $translations['ad_image']; ?>" style="max-width: <?php echo min(200, 150 + ($annonce['price'] / 100)); ?>px; height: auto;">
                            <?php else: ?>
                                <p><?php echo $translations['no_image_available']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="announce-details">
                            <p><strong><?php echo $translations['title']; ?>:</strong> <?php echo htmlspecialchars($annonce['title']); ?></p>
                            <p><strong><?php echo $translations['description']; ?>:</strong> <?php echo htmlspecialchars($annonce['description']); ?></p>
                            <p><strong><?php echo $translations['price']; ?>:</strong> <?php echo $annonce['price'] ? number_format($annonce['price'], 2) . ' €' : $translations['price_not_specified']; ?></p>
                            <?php if (!$is_proprietaire && isset($_GET['id'])): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="like">
                                    <input type="hidden" name="annonce_id" value="<?php echo $annonce['id']; ?>">
                                    <button type="submit" class="like-btn" <?php echo in_array($annonce['id'], $likes) ? '' : ''; ?>>
                                        <?php echo in_array($annonce['id'], $likes) ? '❤️ ' . $translations['liked'] : '❤️ ' . $translations['like']; ?>
                                    </button>
                                    <?php
                                    $stmt_like_count = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE annonce_id = ?");
                                    $stmt_like_count->execute([$annonce['id']]);
                                    $like_count_per_annonce = $stmt_like_count->fetchColumn();
                                    ?>
                                    <span style="margin-left: 10px;">(<?php echo $like_count_per_annonce; ?>)</span>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php echo $translations['no_ads_for_owner']; ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function toggleMenu() {
            const navLinks = document.getElementById('nav-links');
            navLinks.classList.toggle('active');
        }
    </script>
</body>
</html>