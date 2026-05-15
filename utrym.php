<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once 'SQL/db.php';

$today = date("Y-m-d");
$result = $conn->query("SELECT b.namn, b.foretag, k.namn AS kontaktperson 
                        FROM besok bs 
                        JOIN besokare b ON bs.besokare_id = b.id 
                        JOIN kontaktpersoner k ON bs.kontaktperson_id = k.id 
                        WHERE bs.datum_start <= '$today' AND bs.datum_slut >= '$today'");
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Utrymningslista</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Utrymningslista för <?php echo $today; ?></h1>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Namn</th>
                    <th>Företag</th>
                    <th>Kontaktperson</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['namn']) ?></td>
                        <td><?= htmlspecialchars($row['foretag'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['kontaktperson']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <button onclick="window.print()" class="btn btn-primary">Skriv ut</button>
        <p class="mt-3"><a href="home.php" class="btn btn-secondary">Tillbaka</a></p>
    </div>
</body>
</html>
