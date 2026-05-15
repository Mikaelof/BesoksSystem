<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once 'SQL/db.php';

// Sätt UTF-8 för att hantera svenska tecken
$conn->set_charset("utf8mb4");

// Kontrollera att zpl_printer.php inkluderas korrekt
if (!file_exists('zpl_printer.php')) {
    die("Fel: zpl_printer.php hittades inte.");
}
include 'zpl_printer.php';
if (!function_exists('generateZPL')) {
    die("Fel: Funktionen generateZPL är inte definierad. Kontrollera zpl_printer.php.");
}

$error = '';
$success = '';
$print_status = '';

// Hämta befintlig besökarinfo för återbesök
$besokare = null;
if (isset($_GET['besokare_id'])) {
    $besokare_id = (int)$_GET['besokare_id'];
    $stmt = $conn->prepare("SELECT id, namn, foretag FROM besokare WHERE id = ?");
    if (!$stmt) {
        $error = "Fel vid förberedelse av fråga för besökare: " . $conn->error;
    } else {
        $stmt->bind_param("i", $besokare_id);
        $stmt->execute();
        $besokare = $stmt->get_result()->fetch_assoc();
        if (!$besokare) {
            $error = "Ogiltig besökar-ID.";
        }
    }
}

// Hantera formulärinmatning
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $namn = trim($_POST['namn'] ?? '');
    $foretag = trim($_POST['foretag'] ?? '');
    $kontaktperson_namn = trim($_POST['kontaktperson'] ?? '');
    $datum_start = $_POST['datum_start'] ?? '';
    $datum_slut = $_POST['datum_slut'] ?? '';
    $fika_fm = isset($_POST['fika_fm']) ? 1 : 0;
    $fika_em = isset($_POST['fika_em']) ? 1 : 0;
    $lunch = isset($_POST['lunch']) ? 1 : 0;
    $specialkost = isset($_POST['specialkost']) ? 1 : 0;
    $allergi = trim($_POST['allergi'] ?? '');

    if (empty($namn)) {
        $error = "Namn måste anges.";
    } elseif (empty($kontaktperson_namn)) {
        $error = "Kontaktperson måste anges.";
    } elseif (empty($datum_start) || empty($datum_slut)) {
        $error = "Både start- och slutdatum måste anges.";
    } elseif (strtotime($datum_slut) < strtotime($datum_start)) {
        $error = "Slutdatum kan inte vara före startdatum.";
    } else {
        $besokare_id = isset($_POST['besokare_id']) ? (int)$_POST['besokare_id'] : null;
        if ($besokare_id) {
            $stmt = $conn->prepare("SELECT id FROM besokare WHERE id = ?");
            if (!$stmt) {
                $error = "Fel vid förberedelse av fråga för besökar-ID: " . $conn->error;
            } else {
                $stmt->bind_param("i", $besokare_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $error = "Ogiltig besökar-ID.";
                }
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO besokare (namn, foretag) VALUES (?, ?)");
            if (!$stmt) {
                $error = "Fel vid förberedelse av fråga för ny besökare: " . $conn->error;
            } else {
                $stmt->bind_param("ss", $namn, $foretag);
                if ($stmt->execute()) {
                    $besokare_id = $conn->insert_id;
                } else {
                    $error = "Fel vid sparande av besökare: " . $conn->error;
                }
            }
        }

        if (!$error) {
            $stmt = $conn->prepare("SELECT id FROM kontaktpersoner WHERE namn = ?");
            if (!$stmt) {
                $error = "Fel vid förberedelse av fråga för kontaktperson: " . $conn->error;
            } else {
                $stmt->bind_param("s", $kontaktperson_namn);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $kontaktperson_id = $row['id'];
                } else {
                    $stmt = $conn->prepare("INSERT INTO kontaktpersoner (namn) VALUES (?)");
                    if (!$stmt) {
                        $error = "Fel vid förberedelse av fråga för ny kontaktperson: " . $conn->error;
                    } else {
                        $stmt->bind_param("s", $kontaktperson_namn);
                        if ($stmt->execute()) {
                            $kontaktperson_id = $conn->insert_id;
                        } else {
                            $error = "Fel vid sparande av kontaktperson: " . $conn->error;
                        }
                    }
                }
            }

            if (!$error) {
                $stmt = $conn->prepare("INSERT INTO besok (besokare_id, kontaktperson_id, datum_start, datum_slut, fika_fm, fika_em, lunch, specialkost, allergi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $error = "Fel vid förberedelse av fråga för besök: " . $conn->error;
                } else {
                    $stmt->bind_param("iissiiiis", $besokare_id, $kontaktperson_id, $datum_start, $datum_slut, $fika_fm, $fika_em, $lunch, $specialkost, $allergi);
                    if ($stmt->execute()) {
                        $besok_id = $conn->insert_id;
                        $print_status = generateZPL($besok_id, $namn, $foretag, $kontaktperson_namn, $datum_start, $datum_slut, $fika_fm, $fika_em, $lunch, $specialkost, $allergi);
                        $_SESSION['success_message'] = "Besök registrerat för " . htmlspecialchars($namn) . "! Ring kontaktpersonen: " . htmlspecialchars($kontaktperson_namn);
                        header('Location: home.php');
                        exit;
                    } else {
                        $error = "Fel vid registrering av besök: " . $conn->error;
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Registrera nytt besök</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Registrera nytt besök</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?= $print_status ?>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="besokare_id" value="<?= htmlspecialchars($besokare['id'] ?? '') ?>">
            <div class="mb-3">
                <label for="namn" class="form-label">Namn:</label>
                <input type="text" id="namn" name="namn" class="form-control" value="<?= htmlspecialchars($besokare['namn'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="foretag" class="form-label">Företag:</label>
                <input type="text" id="foretag" name="foretag" class="form-control" value="<?= htmlspecialchars($besokare['foretag'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="kontaktperson" class="form-label">Kontaktperson:</label>
                <input type="text" id="kontaktperson" name="kontaktperson" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="datum_start" class="form-label">Från datum:</label>
                <input type="date" id="datum_start" name="datum_start" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="mb-3">
                <label for="datum_slut" class="form-label">Till datum:</label>
                <input type="date" id="datum_slut" name="datum_slut" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Fika:</label><br>
                <input type="checkbox" id="fika_fm" name="fika_fm" value="1">
                <label for="fika_fm">Förmiddag</label>
                <input type="checkbox" id="fika_em" name="fika_em" value="1">
                <label for="fika_em">Eftermiddag</label>
            </div>
            <div class="mb-3">
                <input type="checkbox" id="lunch" name="lunch" value="1">
                <label for="lunch">Lunch</label>
            </div>
            <div class="mb-3">
                <input type="checkbox" id="specialkost" name="specialkost" value="1">
                <label for="specialkost">Specialkost</label>
            </div>
            <div class="mb-3">
                <label for="allergi" class="form-label">Allergi (om specialkost):</label>
                <input type="text" id="allergi" name="allergi" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Registrera besök</button>
        </form>
        <p class="mt-3"><a href="home.php" class="btn btn-secondary">Tillbaka till startsidan</a></p>
    </div>
</body>
</html>
