<?php
session_start();
if (!isset($_SESSION['badge_html'])) {
    header('Location: home.php');
    exit;
}
echo $_SESSION['badge_html'];
unset($_SESSION['badge_html']);
?>
