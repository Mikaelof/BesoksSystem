#!/bin/bash

# ================================================================
# VisitEase - Installationsscript för Ubuntu Server
# ================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo ""
echo "================================================================"
echo "  VisitEase - Installationsscript"
echo "================================================================"
echo ""

# Kontrollera att scriptet körs som root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Det här scriptet måste köras som root (sudo).${NC}"
    echo "Kör: sudo bash install.sh"
    exit 1
fi

# ----------------------------------------------------------------
# Samla in uppgifter från användaren
# ----------------------------------------------------------------

echo "Vi behöver några uppgifter innan installationen börjar."
echo ""

read -p "Databasnamn [besokssystem]: " DB_NAME
DB_NAME=${DB_NAME:-besokssystem}

read -p "Databasanvändare [besok]: " DB_USER
DB_USER=${DB_USER:-besok}

while true; do
    read -s -p "Lösenord för databasanvändaren: " DB_PASS
    echo ""
    read -s -p "Bekräfta lösenordet: " DB_PASS2
    echo ""
    if [ "$DB_PASS" == "$DB_PASS2" ]; then
        break
    else
        echo -e "${RED}Lösenorden matchar inte. Försök igen.${NC}"
    fi
done

while true; do
    read -s -p "Lösenord för MariaDB root-användaren: " DB_ROOT_PASS
    echo ""
    read -s -p "Bekräfta root-lösenordet: " DB_ROOT_PASS2
    echo ""
    if [ "$DB_ROOT_PASS" == "$DB_ROOT_PASS2" ]; then
        break
    else
        echo -e "${RED}Lösenorden matchar inte. Försök igen.${NC}"
    fi
done

read -p "Användarnamn för inloggning i VisitEase [admin]: " APP_USER
APP_USER=${APP_USER:-admin}

while true; do
    read -s -p "Lösenord för inloggning i VisitEase: " APP_PASS
    echo ""
    read -s -p "Bekräfta lösenordet: " APP_PASS2
    echo ""
    if [ "$APP_PASS" == "$APP_PASS2" ]; then
        break
    else
        echo -e "${RED}Lösenorden matchar inte. Försök igen.${NC}"
    fi
done

echo ""
read -p "IP-adress till Zebra-skrivare (lämna tomt för A4-utskrift): " PRINTER_IP

echo ""
read -p "Vill du installera phpMyAdmin? (j/n) [n]: " INSTALL_PHPMYADMIN
INSTALL_PHPMYADMIN=${INSTALL_PHPMYADMIN:-n}

INSTALL_DIR="/var/www/html/besokssystem"

echo ""
echo "----------------------------------------------------------------"
echo "Sammanfattning:"
echo "  Databas:        $DB_NAME"
echo "  Databasanv.:    $DB_USER"
echo "  App-användare:  $APP_USER"
echo "  Installeras i:  $INSTALL_DIR"
if [ -n "$PRINTER_IP" ]; then
    echo "  Zebra-skrivare: $PRINTER_IP"
else
    echo "  Utskrift:       A4 (ingen Zebra-skrivare)"
fi
echo "----------------------------------------------------------------"
echo ""
read -p "Stämmer det här? Tryck Enter för att fortsätta eller Ctrl+C för att avbryta."

# ----------------------------------------------------------------
# Uppdatera systemet och installera LAMP
# ----------------------------------------------------------------

echo ""
echo -e "${YELLOW}[1/6] Uppdaterar systemet...${NC}"
apt-get update -q
apt-get upgrade -y -q

echo -e "${YELLOW}[2/6] Installerar Apache, PHP och MariaDB...${NC}"
apt-get install -y -q apache2 php libapache2-mod-php php-mysqli mariadb-server unzip curl

# Aktivera Apache
systemctl enable apache2
systemctl start apache2

# Starta MariaDB
systemctl enable mariadb
systemctl start mariadb

# ----------------------------------------------------------------
# Säkra MariaDB och skapa databas
# ----------------------------------------------------------------

echo -e "${YELLOW}[3/6] Konfigurerar MariaDB...${NC}"

# Sätt root-lösenord och säkra installationen
mysql -u root <<MYSQL_SCRIPT
ALTER USER 'root'@'localhost' IDENTIFIED BY '$DB_ROOT_PASS';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

# Skapa databas och användare
mysql -u root -p"$DB_ROOT_PASS" <<MYSQL_SCRIPT
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

# ----------------------------------------------------------------
# Installera VisitEase
# ----------------------------------------------------------------

echo -e "${YELLOW}[4/6] Installerar VisitEase...${NC}"

# Skapa installationsmapp
mkdir -p "$INSTALL_DIR"
mkdir -p "$INSTALL_DIR/SQL"

# Kopiera filer (scriptet antar att det körs från VisitEase-mappen)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cp "$SCRIPT_DIR"/*.php "$INSTALL_DIR/"
cp "$SCRIPT_DIR/SQL/"*.php "$INSTALL_DIR/SQL/"
cp "$SCRIPT_DIR/SQL/"*.sql "$INSTALL_DIR/SQL/"

# Konfigurera db.php
sed -i "s/DITT_LÖSENORD_HÄR/$DB_PASS/g" "$INSTALL_DIR/SQL/db.php"
sed -i "s/\$db_user = \"besok\"/\$db_user = \"$DB_USER\"/g" "$INSTALL_DIR/SQL/db.php"
sed -i "s/\$db_name = \"besokssystem\"/\$db_name = \"$DB_NAME\"/g" "$INSTALL_DIR/SQL/db.php"

# Konfigurera inloggning i index.php
sed -i "s/'admin' => 'DITT_LÖSENORD_HÄR'/'$APP_USER' => '$APP_PASS'/g" "$INSTALL_DIR/index.php"

# Konfigurera skrivar-IP i zpl_printer.php
if [ -n "$PRINTER_IP" ]; then
    sed -i "s/DIN_SKRIVARES_IP/$PRINTER_IP/g" "$INSTALL_DIR/zpl_printer.php"
fi

# Importera databasschemat
mysql -u root -p"$DB_ROOT_PASS" "$DB_NAME" < "$SCRIPT_DIR/SQL/schema.sql"

# Sätt rättigheter
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"

# ----------------------------------------------------------------
# Installera phpMyAdmin (valfritt)
# ----------------------------------------------------------------

if [[ "$INSTALL_PHPMYADMIN" =~ ^[jJyY]$ ]]; then
    echo -e "${YELLOW}[5/6] Installerar phpMyAdmin...${NC}"
    # Förhindra interaktiv prompt
    echo "phpmyadmin phpmyadmin/dbconfig-install boolean true" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/app-password-confirm password $DB_ROOT_PASS" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/mysql/admin-pass password $DB_ROOT_PASS" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/mysql/app-pass password $DB_ROOT_PASS" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections
    apt-get install -y -q phpmyadmin
    echo -e "${GREEN}phpMyAdmin installerat! Nås på: http://$(hostname -I | awk '{print $1}')/phpmyadmin${NC}"
else
    echo -e "${YELLOW}[5/6] Hoppar över phpMyAdmin.${NC}"
fi

# ----------------------------------------------------------------
# Klar!
# ----------------------------------------------------------------

echo -e "${YELLOW}[6/6] Slutför installationen...${NC}"
systemctl restart apache2

SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}================================================================${NC}"
echo -e "${GREEN}  VisitEase är nu installerat!${NC}"
echo -e "${GREEN}================================================================${NC}"
echo ""
echo "  Öppna systemet i webbläsaren:"
echo -e "  ${GREEN}http://$SERVER_IP/besokssystem/${NC}"
echo ""
echo "  Logga in med:"
echo "    Användarnamn: $APP_USER"
echo "    Lösenord:     (det du angav)"
echo ""
if [ -z "$PRINTER_IP" ]; then
    echo "  Utskrift: A4-läge aktiverat (ingen Zebra-skrivare konfigurerad)"
else
    echo "  Zebra-skrivare: $PRINTER_IP"
fi
echo ""
echo -e "${YELLOW}  Tips: Spara den här informationen på ett säkert ställe!${NC}"
echo "================================================================"
echo ""
