-- Script de création de la base de données
-- Gestionnaire de Recherche

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS gestionnaire_recherche
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE gestionnaire_recherche;

-- Table des projets
CREATE TABLE IF NOT EXISTS projets (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_nom (nom),
    INDEX idx_date_creation (date_creation),
    INDEX idx_date_modification (date_modification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des sources
CREATE TABLE IF NOT EXISTS sources (
    id INT(11) NOT NULL AUTO_INCREMENT,
    projet_id INT(11) NOT NULL,
    titre VARCHAR(500) NOT NULL,
    url TEXT,
    resume TEXT,
    contenu LONGTEXT,
    pertinente TINYINT(1) NOT NULL DEFAULT 0,
    date_ajout DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_projet_id (projet_id),
    INDEX idx_titre (titre),
    INDEX idx_pertinente (pertinente),
    INDEX idx_date_ajout (date_ajout),
    FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
