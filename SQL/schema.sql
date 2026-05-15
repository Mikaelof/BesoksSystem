-- schema.sql
-- Skapar databas och tabeller för besökssystemet

CREATE DATABASE IF NOT EXISTS besokssystem;
USE besokssystem;

-- Tabell för besökare
CREATE TABLE besokare (
    id INT AUTO_INCREMENT PRIMARY KEY,
    namn VARCHAR(100) NOT NULL,
    foretag VARCHAR(100),
    INDEX idx_namn (namn),
    INDEX idx_foretag (foretag)
);

-- Tabell för kontaktpersoner
CREATE TABLE kontaktpersoner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    namn VARCHAR(100) NOT NULL
);

-- Tabell för besök (utan syfte)
CREATE TABLE besok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    besokare_id INT NOT NULL,
    kontaktperson_id INT NOT NULL,
    datum_start DATE NOT NULL,
    datum_slut DATE NOT NULL,
    fika_fm BOOLEAN DEFAULT FALSE,
    fika_em BOOLEAN DEFAULT FALSE,
    lunch BOOLEAN DEFAULT FALSE,
    specialkost BOOLEAN DEFAULT FALSE,
    allergi TEXT,
    FOREIGN KEY (besokare_id) REFERENCES besokare(id) ON DELETE CASCADE,
    FOREIGN KEY (kontaktperson_id) REFERENCES kontaktpersoner(id) ON DELETE RESTRICT
);

-- Tabell för att logga etikettutskrifter
CREATE TABLE etikettutskrift (
    id INT AUTO_INCREMENT PRIMARY KEY,
    besok_id INT NOT NULL,
    utskriven DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (besok_id) REFERENCES besok(id) ON DELETE CASCADE
);