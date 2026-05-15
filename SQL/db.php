

<?php
// Databasinställningar - ändra dessa vid installation
$db_host = "localhost";
$db_user = "besok";
$db_pass = "DITT_LÖSENORD_HÄR";
$db_name = "besokssystem";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Kunde inte ansluta till databasen: " . $conn->connect_error);
}
?>
