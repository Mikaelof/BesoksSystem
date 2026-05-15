-- testdata.sql
-- Testdata för besökssystemet

USE besokssystem;

-- Rensa befintliga data (valfritt, kommentera bort om du vill behålla tidigare data)
DELETE FROM etikettutskrift;
DELETE FROM besok;
DELETE FROM besokare;
DELETE FROM kontaktpersoner;

-- Lägg till kontaktpersoner
INSERT INTO kontaktpersoner (namn) VALUES
('Robert Bouveng'),
('Mikael Gunnarsson'),
('Anna Svensson');

-- Lägg till besökare
INSERT INTO besokare (namn, foretag) VALUES
('Jon Snow', 'Night''s Watch'),
('Daenerys Targaryen', 'House Targaryen'),
('Arya Stark', 'House Stark'),
('Tyrion Lannister', 'House Lannister'),
('Brienne of Tarth', 'House Tarth');

-- Lägg till besök (utan syfte)
INSERT INTO besok (
    besokare_id, kontaktperson_id, datum_start, datum_slut,
    fika_fm, fika_em, lunch, specialkost, allergi
) VALUES
(1, 1, '2025-10-03', '2025-10-03', TRUE, FALSE, TRUE, FALSE, ''),
(2, 2, '2025-10-03', '2025-10-04', TRUE, TRUE, TRUE, TRUE, 'Glutenfri'),
(3, 1, '2025-10-04', '2025-10-04', FALSE, TRUE, FALSE, FALSE, ''),
(4, 2, '2025-10-03', '2025-10-05', TRUE, FALSE, TRUE, TRUE, 'Nötallergi'),
(5, 3, '2025-10-03', '2025-10-03', FALSE, FALSE, FALSE, FALSE, '');

-- Lägg till exempel på etikettutskrifter
INSERT INTO etikettutskrift (besok_id, utskriven) VALUES
(1, '2025-10-03 09:00:00'),
(2, '2025-10-03 10:30:00'),
(4, '2025-10-03 11:00:00');