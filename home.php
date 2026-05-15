<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Handle visitor status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['besok_id'])) {
    require_once 'SQL/db.php';
    
    $besok_id = intval($_POST['besok_id']);
    $action = $_POST['action'];
    
    switch ($action) {
        case 'left':
            // Mark as left - set end date to now
            $sql = "UPDATE besok SET datum_slut = NOW(), status = 'left' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $besok_id);
            $stmt->execute();
            $stmt->close();
            break;
            
        case 'no_show':
            // Mark as no-show
            $sql = "UPDATE besok SET status = 'no_show' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $besok_id);
            $stmt->execute();
            $stmt->close();
            break;
            
        case 'reprint':
            // Reprint badge - get visit details and print
            include 'zpl_printer.php';
            
            $sql = "SELECT 
                besokare.namn,
                besokare.foretag,
                kontaktpersoner.namn AS kontaktperson,
                besok.datum_start,
                besok.datum_slut,
                besok.fika_fm,
                besok.fika_em,
                besok.lunch,
                besok.specialkost,
                besok.allergi
            FROM besok
            INNER JOIN besokare ON besok.besokare_id = besokare.id
            INNER JOIN kontaktpersoner ON besok.kontaktperson_id = kontaktpersoner.id
            WHERE besok.id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $besok_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $visit = $result->fetch_assoc();
            
            if ($visit) {
                generateZPL(
                    $besok_id,
                    $visit['namn'],
                    $visit['foretag'],
                    $visit['kontaktperson'],
                    $visit['datum_start'],
                    $visit['datum_slut'],
                    $visit['fika_fm'],
                    $visit['fika_em'],
                    $visit['lunch'],
                    $visit['specialkost'],
                    $visit['allergi']
                );
                $_SESSION['success_message'] = "Ny bricka utskriven för " . htmlspecialchars($visit['namn']);
            }
            $stmt->close();
            break;
            
        default:
            header('Location: home.php');
            exit;
    }
    
    $conn->close();
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Besökssystem - Startsida</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; }
        .nav-box { max-width: 1000px; margin: 80px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-buttons button { font-size: 0.85rem; padding: 0.25rem 0.5rem; }
    </style>
</head>
<body>
    <div class="nav-box text-center">
        <h2>Välkommen, <?= htmlspecialchars($_SESSION['user']) ?>!</h2>
        <p>Välj en funktion nedan:</p>
        <div class="d-grid gap-3 mt-4">
            <a href="registrera.php" class="btn btn-primary btn-lg">Registrera nytt besök</a>
            <a href="sok.php" class="btn btn-info btn-lg">Sök tidigare besökare</a>
            <a href="utrym.php" class="btn btn-warning btn-lg">Utrymningslista</a>
            <a href="logout.php" class="btn btn-danger btn-lg">Logga ut</a>
        </div>

        <!-- Success message from reprint or registration -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php
        require_once 'SQL/db.php';
        $today = date('Y-m-d');
        $sql = "SELECT 
            besok.id AS besok_id,
            besokare.namn AS besokare_namn, 
            besokare.foretag, 
            kontaktpersoner.namn AS kontaktperson_namn,
            besok.datum_start, 
            besok.datum_slut,
            besok.status
        FROM besok
        INNER JOIN besokare ON besok.besokare_id = besokare.id
        INNER JOIN kontaktpersoner ON besok.kontaktperson_id = kontaktpersoner.id
        WHERE besok.datum_start <= ? 
            AND besok.datum_slut >= ? 
            AND (besok.status IS NULL OR besok.status NOT IN ('left', 'no_show'))
        ORDER BY besokare.namn";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $today, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        ?>
        <div class="mt-5">
            <h3>Dagens besökare (<?= $today ?>)</h3>
            <?php if ($result->num_rows > 0): ?>
                <table class='table table-striped'>
                    <thead>
                        <tr>
                            <th>Namn</th>
                            <th>Företag</th>
                            <th>Kontaktperson</th>
                            <th>Giltig från</th>
                            <th>Giltig till</th>
                            <th>Åtgärder</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['besokare_namn']) ?></td>
                                <td><?= htmlspecialchars($row['foretag']) ?></td>
                                <td><?= htmlspecialchars($row['kontaktperson_namn']) ?></td>
                                <td><?= htmlspecialchars($row['datum_start']) ?></td>
                                <td><?= htmlspecialchars($row['datum_slut']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="besok_id" value="<?= $row['besok_id'] ?>">
                                            <input type="hidden" name="action" value="reprint">
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Skriv ut ny bricka för <?= htmlspecialchars($row['besokare_namn']) ?>?')">
                                                Skriv ut
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="besok_id" value="<?= $row['besok_id'] ?>">
                                            <input type="hidden" name="action" value="left">
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Bekräfta att besökaren har lämnat byggnaden?')">
                                                Lämnat
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="besok_id" value="<?= $row['besok_id'] ?>">
                                            <input type="hidden" name="action" value="no_show">
                                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Markera som uteblivet besök?')">
                                                Uteblev
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class='alert alert-secondary'>Inga besökare registrerade idag.</p>
            <?php endif; ?>
        </div>
        <?php
        $stmt->close();
        ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>