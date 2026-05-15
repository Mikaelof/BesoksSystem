# VisitEase 🏢

A free and open source visitor management system built with PHP and MariaDB, designed for small and medium-sized businesses.

## Features

- **Register visitors** – Quickly register new visitors with contact person, dates, and meal preferences if needed
- **Return visitors** – Search and re-register previous visitors with one click
- **Evacuation list** – Always up-to-date list of visitors currently on site, printable in case of emergency
- **Badge printing** – Automatic printing of visitor badges. Supports Zebra label printers (ZPL) with a pre-printed logo. No Zebra printer? No problem – the system automatically falls back to printing a badge on any standard A4 printer instead, just cut it out along the dashed line

> **Note:** The user interface is currently in Swedish. English translation is planned for a future release.

## Requirements

- PHP 7.4 or higher
- MariaDB / MySQL
- Apache web server (LAMP stack)
- Zebra label printer with network connection *(optional – falls back to A4 printing on any standard printer)*

## Quick Installation

1. Clone or download this repository to your web server
2. Create the database by importing `SQL/schema.sql` in phpMyAdmin or via terminal:
   ```bash
   mysql -u root -p < SQL/schema.sql
   ```
3. Copy `SQL/db.php` and fill in your database credentials:
   ```php
   $db_host = "localhost";
   $db_user = "YOUR_DB_USER";
   $db_pass = "YOUR_PASSWORD";
   $db_name = "besokssystem";
   ```
4. Open `index.php` and set your login credentials
5. (Optional) Load test data via `SQL/testdata.sql`
6. (Optional) Set your Zebra printer IP in `zpl_printer.php`

For detailed installation instructions, see [INSTALL.md](INSTALL.md).

## Screenshots

*Coming soon*

## License

This project is licensed under the GNU General Public License v3.0 – see the [LICENSE](LICENSE) file for details.

## Contributing

Contributions, bug reports and feature requests are welcome! Feel free to open an issue or submit a pull request.
