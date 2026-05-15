<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once 'SQL/db.php';

$search_results = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search = $_POST['search_term'];
    $stmt = $conn->prepare("SELECT DISTINCT b.id, b.namn, b.foretag, k.namn AS kontaktperson 
                            FROM besokare b 
                            LEFT JOIN besok bs ON b.id = bs.besokare_id 
                            LEFT JOIN kontaktpersoner k ON bs.kontaktperson_id = k.id 
                            WHERE b.namn LIKE ? OR b.foretag LIKE ?");
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Sök tidigare besökare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Sök tidigare besökare</h1>
        <form method="POST">
            <div class="mb-3">
                <label for="search_term" class="form-label">Sök namn eller företag:</label>				
                <input type="text" id="search_term" name="search_term" class="form-control">
            </div>       
        </form>		
        <hr>
        <div id="resultat"></div>
       
        <?php if (!empty($search_results)): ?>
            <h2 class="mt-4">Sökresultat</h2>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Namn</th>
                        <th>Företag</th>
                        <th>Kontaktperson</th>
                        <th>Åtgärd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $result): ?>
                        <tr>
                            <td><?= htmlspecialchars($result['namn']) ?></td>
                            <td><?= htmlspecialchars($result['foretag'] ?? '') ?></td>
                            <td><?= htmlspecialchars($result['kontaktperson'] ?? 'Ingen') ?></td>
                            <td><a href="registrera.php?besokare_id=<?= $result['id'] ?>" class="btn btn-sm btn-primary">Registrera återbesök</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="mt-3"><a href="home.php" class="btn btn-secondary">Tillbaka</a></p>
    </div>
    <script>
        document.getElementById('search_term').addEventListener('input', function() {
            const query = this.value;
            fetch('ajax_sok.php?q=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('resultat').innerHTML = data;
                });
        });
    </script>
</body>
</html>
