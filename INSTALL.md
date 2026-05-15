# Installationsguide – VisitEase

Det finns två sätt att installera VisitEase:

- **Alternativ A** – Automatisk installation med ett script (rekommenderas för Ubuntu Server)
- **Alternativ B** – Manuell installation på en befintlig LAMP-server

---

## Alternativ A – Automatisk installation (Ubuntu Server)

Det här alternativet installerar allt från grunden: Apache, PHP, MariaDB och VisitEase.
Scriptet ställer frågor om lösenord och inställningar och sköter resten automatiskt.

### Krav
- Ubuntu Server 20.04 eller 22.04
- Internetanslutning
- Root-åtkomst (sudo)

### Steg för steg

**1. Ladda ner VisitEase**

```bash
cd ~
wget https://github.com/Mikaelof/BesoksSystem/archive/refs/heads/main.zip
unzip main.zip
cd BesoksSystem-main
```

**2. Gör installationssriptet körbart och kör det**

```bash
chmod +x install.sh
sudo bash install.sh
```

Scriptet kommer att fråga efter:
- Databasnamn, användare och lösenord
- Lösenord för MariaDB root-användaren
- Användarnamn och lösenord för inloggning i VisitEase
- IP-adress till Zebra-skrivare (lämna tomt för A4-utskrift)
- Om phpMyAdmin ska installeras

**3. Klart!**

När scriptet är klart visas adressen till ditt system, t.ex.:
```
http://192.168.1.100/besokssystem/
```

---

## Alternativ B – Manuell installation

Använd det här alternativet om du redan har en LAMP-server igång.

### Krav
- Apache 2.4 eller senare
- PHP 7.4 eller senare (med `php-mysqli`)
- MariaDB 10.3 eller senare

### Steg för steg

**1. Ladda ner och packa upp VisitEase**

```bash
cd /var/www/html
wget https://github.com/Mikaelof/BesoksSystem/archive/refs/heads/main.zip
unzip main.zip
mv BesoksSystem-main besokssystem
```

**2. Skapa databasen**

Logga in i MariaDB:
```bash
sudo mysql -u root -p
```

Kör följande kommandon:
```sql
CREATE DATABASE besokssystem CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci;
CREATE USER 'besok'@'localhost' IDENTIFIED BY 'DITT_LÖSENORD';
GRANT ALL PRIVILEGES ON besokssystem.* TO 'besok'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**3. Importera databasschemat**

```bash
mysql -u root -p besokssystem < /var/www/html/besokssystem/SQL/schema.sql
```

Vill du ha testdata i databasen:
```bash
mysql -u root -p besokssystem < /var/www/html/besokssystem/SQL/testdata.sql
```

**4. Konfigurera databasanslutningen**

Öppna `SQL/db.php` och fyll i dina uppgifter:
```php
$db_host = "localhost";
$db_user = "besok";
$db_pass = "DITT_LÖSENORD";
$db_name = "besokssystem";
```

**5. Konfigurera inloggning**

Öppna `index.php` och ändra användarnamn och lösenord:
```php
$users = [
    'admin' => 'DITT_LÖSENORD_HÄR',
];
```

**6. Konfigurera skrivare**

Öppna `zpl_printer.php` och ange IP-adressen till din Zebra-skrivare:
```php
$printer_ip = "192.168.1.50"; // Din skrivares IP
```

Lämna värdet som `DIN_SKRIVARES_IP` om du inte har en Zebra-skrivare – systemet skriver då ut brickan på din vanliga skrivare i A4-format.

**7. Sätt rättigheter**

```bash
sudo chown -R www-data:www-data /var/www/html/besokssystem
sudo chmod -R 755 /var/www/html/besokssystem
```

**8. Starta om Apache**

```bash
sudo systemctl restart apache2
```

**9. Öppna systemet**

Gå till `http://din-servers-ip/besokssystem/` i webbläsaren och logga in.

---

## Felsökning

**Kan inte ansluta till databasen**
Kontrollera att uppgifterna i `SQL/db.php` stämmer och att MariaDB körs:
```bash
sudo systemctl status mariadb
```

**Sidan visas inte**
Kontrollera att Apache körs:
```bash
sudo systemctl status apache2
```

**Zebra-skrivaren svarar inte**
Kontrollera att skrivarens IP-adress stämmer och att skrivaren är ansluten till nätverket. Porten som används är `9100`.

---

## Uppgradering

Ladda ner den senaste versionen och ersätt filerna, men **behåll** din `SQL/db.php` och `index.php` med dina egna inställningar.
