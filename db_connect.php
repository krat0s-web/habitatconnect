<?php
// Paramètres de connexion
$host = 'localhost';          // Hôte (XAMPP = localhost)
$dbname = 'habitaconnect_db'; // Nom de votre base de données
$username = 'root';           // Utilisateur par défaut dans XAMPP
$password = 'root';               // Mot de passe par défaut (vide dans XAMPP)

try {
    // Connexion à la base de données avec PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Configurer PDO pour afficher les erreurs
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // En cas d’erreur, afficher le message
    echo "Erreur de connexion : " . $e->getMessage();
}
?>