-- Création de la base de données
DROP DATABASE IF EXISTS habitaconnect_db;
CREATE DATABASE habitaconnect_db;
USE habitaconnect_db;

-- Table utilisateurs (avec rôle)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('proprietaire', 'client') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    profile_photo VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL
);

-- Table annonces (créées par les propriétaires)
CREATE TABLE annonces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proprietaire_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type VARCHAR(50) DEFAULT NULL,
    localisation VARCHAR(255),
    latitude DECIMAL(9,6) DEFAULT NULL,
    longitude DECIMAL(9,6) DEFAULT NULL,
    FOREIGN KEY (proprietaire_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table équipements
CREATE TABLE equipements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annonce_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    `condition` VARCHAR(50),
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
);

-- Table réservations (faites par les clients)
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    annonce_id INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    start_date DATE NOT NULL,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
);

-- Table images des annonces
CREATE TABLE annonce_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annonce_id INT NOT NULL,
    photo VARCHAR(255),
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
);

-- Table questions
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annonce_id INT NOT NULL,
    client_id INT NOT NULL,
    question TEXT NOT NULL,
    reponse TEXT,
    date_question TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_reponse TIMESTAMP NULL,
    lu TINYINT(1) DEFAULT 0,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table commentaires
CREATE TABLE commentaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annonce_id INT NOT NULL,
    user_id INT NOT NULL,
    commentaire TEXT NOT NULL,
    etoiles INT NOT NULL CHECK (etoiles BETWEEN 1 AND 5),
    date_commentaire TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table followers
CREATE TABLE followers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    proprietaire_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (proprietaire_id) REFERENCES users(id),
    UNIQUE (client_id, proprietaire_id)
);

-- Table likes
CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    annonce_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (annonce_id) REFERENCES annonces(id),
    UNIQUE (client_id, annonce_id)
);

-- Table vues
CREATE TABLE views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    annonce_id INT,
    date_viewed DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (annonce_id) REFERENCES annonces(id)
);

-- Table promotions
CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annonce_id INT,
    discount_percentage DECIMAL(5,2),
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id)
);

-- Insertion des utilisateurs existants
INSERT INTO users (username, email, password, role, profile_photo, bio, phone) VALUES 
('JeanDupont', 'proprietaire1@example.com', 'hashed_password_jean', 'proprietaire', 'images/jean.jpg', 'Propriétaire expérimenté, passionné par le bricolage.', '0601234567'),
('MarieLeclerc', 'proprietaire2@example.com', 'hashed_password_marie', 'proprietaire', 'images/marie.jpg', 'Fournisseur d’outils de qualité pour vos projets.', '0609876543'),
('PaulMartin', 'client1@example.com', 'hashed_password_paul', 'client', NULL, NULL, '0612345678'),
('ClaireDurand', 'client2@example.com', 'hashed_password_claire', 'client', NULL, NULL, '0698765432');

-- Insérer les 10 nouvelles annonces
INSERT INTO annonces (proprietaire_id, title, description, price, type, localisation, latitude, longitude) VALUES 
(1, 'Maison spacieuse avec jardin', 'Grande maison de 150m² avec un jardin fleuri, idéale pour une famille.', 120.00, 'MAISON', 'Paris, France', 48.8566, 2.3522),
(2, 'Appartement moderne en centre-ville', 'Appartement rénové de 60m² avec tout le confort moderne.', 90.00, 'APPARTEMENT', 'Lyon, France', 45.7640, 4.8357),
(1, 'Villa de luxe avec piscine', 'Villa de 200m² avec piscine privée et vue panoramique.', 250.00, 'VILLA', 'Paris, France', 48.8566, 2.3522),
(2, 'Studio cosy près de la gare', 'Petit studio de 30m², parfait pour un séjour pratique.', 50.00, 'APPARTEMENT', 'Lyon, France', 45.7640, 4.8357),
(1, 'Maison de campagne chaleureuse', 'Maison de 120m² avec cheminée, idéale pour un séjour reposant.', 100.00, 'MAISON', 'Paris, France', 48.8566, 2.3522),
(2, 'Appartement avec vue sur la ville', 'Appartement de 80m² au 10e étage avec une vue imprenable.', 110.00, 'APPARTEMENT', 'Lyon, France', 45.7640, 4.8357),
(1, 'Villa familiale avec terrasse', 'Villa de 180m² avec grande terrasse et espace barbecue.', 200.00, 'VILLA', 'Paris, France', 48.8566, 2.3522),
(2, 'Loft industriel lumineux', 'Loft de 100m² avec grandes fenêtres et design moderne.', 130.00, 'APPARTEMENT', 'Lyon, France', 45.7640, 4.8357),
(1, 'Maison contemporaine avec garage', 'Maison moderne de 140m² avec garage et espace de rangement.', 150.00, 'MAISON', 'Paris, France', 48.8566, 2.3522),
(2, 'Appartement duplex avec balcon', 'Duplex de 90m² avec balcon et vue sur le parc.', 120.00, 'APPARTEMENT', 'Lyon, France', 45.7640, 4.8357);

-- Insérer des équipements pour les annonces
INSERT INTO equipements (annonce_id, name, `condition`) VALUES 
(1, 'Cuisine', 'Bon état'), (1, 'WiFi', 'Bon état'), (1, 'Jardin', 'Bon état'),
(2, 'Climatisation', 'Bon état'), (2, 'Télévision', 'Bon état'), (2, 'Cuisine', 'Bon état'),
(3, 'Piscine', 'Bon état'), (3, 'Climatisation', 'Bon état'), (3, 'WiFi', 'Bon état'),
(4, 'Toilettes', 'Bon état'), (4, 'Lit', 'Bon état'), (4, 'WiFi', 'Bon état'),
(5, 'Cheminée', 'Bon état'), (5, 'Chauffage', 'Bon état'), (5, 'Cuisine', 'Bon état'),
(6, 'Télévision', 'Bon état'), (6, 'WiFi', 'Bon état'), (6, 'Espace de rangement pour vêtements : placard', 'Bon état'),
(7, 'Terrasse', 'Bon état'), (7, 'Cuisine', 'Bon état'), (7, 'WiFi', 'Bon état'),
(8, 'Télévision', 'Bon état'), (8, 'Climatisation', 'Bon état'), (8, 'Cuisine', 'Bon état'),
(9, 'Garage', 'Bon état'), (9, 'WiFi', 'Bon état'), (9, 'Chauffage', 'Bon état'),
(10, 'Balcon', 'Bon état'), (10, 'Cuisine', 'Bon état'), (10, 'WiFi', 'Bon état');

-- Insérer des images pour les annonces (2 images par annonce)
INSERT INTO annonce_images (annonce_id, photo) VALUES 
(1, 'images/maison_jardin1.png'), (1, 'images/maison_jardin2.png'),
(2, 'images/appartement_centre1.png'), (2, 'images/appartement_centre2.png'), 
(3, 'images/villa_piscine1.jpg'), (3, 'images/villa_piscine2.jpg'), (3, 'images/villa_piscine3.jpg'),
(4, 'images/studio_gare1.jpg'), (4, 'images/studio_gare2.jpg'),
(5, 'images/maison_campagne1.jpg'), (5, 'images/maison_campagne2.jpg'),
(6, 'images/appartement_vue1.jpg'), (6, 'images/appartement_vue2.jpg'),
(7,'images/villa_familiale1.jpg'), (7, 'images/villa_familiale2.jpg'),
(8, 'images/loft_industriel1.jpg'), (8, 'images/loft_industriel2.jpg'),
(9, 'images/maison_contemporaine1.jpg'), (9, 'images/maison_contemporaine2.jpg'),
(10, 'images/appartement_duplex1.jpg'), (10, 'images/appartement_duplex2.jpg');

-- Insérer des réservations pour les annonces (par Paul Martin et Claire Durand)
INSERT INTO reservations (client_id, annonce_id, status, start_date, end_date) VALUES 
(3, 1, 'pending', '2025-06-01', '2025-06-03'),
(4, 2, 'pending', '2025-06-04', '2025-06-06'),
(3, 3, 'pending', '2025-06-07', '2025-06-09'),
(4, 4, 'pending', '2025-06-10', '2025-06-12'),
(3, 5, 'pending', '2025-06-13', '2025-06-15'),
(4, 6, 'pending', '2025-06-16', '2025-06-18'),
(3, 7, 'pending', '2025-06-19', '2025-06-21'),
(4, 8, 'pending', '2025-06-22', '2025-06-24'),
(3, 9, 'pending', '2025-06-25', '2025-06-27'),
(4, 10, 'pending', '2025-06-28', '2025-06-30');

-- Insérer des questions pour les annonces
INSERT INTO questions (annonce_id, client_id, question, lu) VALUES 
(1, 4, 'Le jardin est-il sécurisé pour les enfants ?', 0),
(2, 3, 'Y a-t-il un parking à proximité ?', 0),
(3, 4, 'La piscine est-elle chauffée ?', 0),
(4, 3, 'Le studio est-il équipé d’une cuisine ?', 0),
(5, 4, 'Y a-t-il des sentiers de randonnée à proximité ?', 0),
(6, 3, 'L’appartement est-il calme la nuit ?', 0),
(7, 4, 'La terrasse est-elle couverte ?', 0),
(8, 3, 'Le loft est-il bien isolé ?', 0),
(9, 4, 'Le garage peut-il accueillir deux voitures ?', 0),
(10, 3, 'Le balcon est-il assez grand pour une table ?', 0);

-- Insérer des commentaires pour les annonces
INSERT INTO commentaires (annonce_id, user_id, commentaire, etoiles) VALUES 
(1, 3, 'Maison très confortable, le jardin est magnifique !', 5),
(2, 4, 'Appartement bien placé, très moderne.', 4),
(3, 3, 'Villa incroyable, la piscine est un gros plus.', 5),
(4, 4, 'Studio pratique pour un court séjour.', 3),
(5, 3, 'Maison chaleureuse, parfait pour se détendre.', 4),
(6, 4, 'Vue splendide, appartement agréable.', 4),
(7, 3, 'Villa idéale pour une famille, la terrasse est top.', 5),
(8, 4, 'Loft très lumineux, j’ai adoré le style.', 4),
(9, 3, 'Maison moderne et fonctionnelle, le garage est un plus.', 4),
(10, 4, 'Duplex charmant, le balcon est un bonus.', 4);

-- Insérer des likes pour les annonces (sans doublons)
INSERT INTO likes (client_id, annonce_id) VALUES 
(4, 1), -- Claire Durand likes annonce 1
(3, 2), -- Paul Martin likes annonce 2
(4, 3), -- Claire Durand likes annonce 3
(3, 4), -- Paul Martin likes annonce 4
(4, 5), -- Claire Durand likes annonce 5
(3, 6), -- Paul Martin likes annonce 6
(4, 7), -- Claire Durand likes annonce 7
(3, 8), -- Paul Martin likes annonce 8
(4, 9), -- Claire Durand likes annonce 9
(3, 10); -- Paul Martin likes annonce 10

-- Insérer des vues pour les annonces
INSERT INTO views (client_id, annonce_id, date_viewed) VALUES 
(3, 1, NOW()), (4, 2, NOW()), (3, 3, NOW()), (4, 4, NOW()), (3, 5, NOW()),
(4, 6, NOW()), (3, 7, NOW()), (4, 8, NOW()), (3, 9, NOW()), (4, 10, NOW());

-- Insérer des promotions pour les annonces
INSERT INTO promotions (annonce_id, discount_percentage, start_date, end_date) VALUES 
(1, 10.00, '2025-06-01', '2025-06-15'),
(2, 15.00, '2025-06-01', '2025-06-15'),
(3, 12.00, '2025-06-01', '2025-06-15'),
(4, 8.00, '2025-06-01', '2025-06-15'),
(5, 10.00, '2025-06-01', '2025-06-15'),
(6, 15.00, '2025-06-01', '2025-06-15'),
(7, 10.00, '2025-06-01', '2025-06-15'),
(8, 12.00, '2025-06-01', '2025-06-15'),
(9, 8.00, '2025-06-01', '2025-06-15'),
(10, 15.00, '2025-06-01', '2025-06-15');