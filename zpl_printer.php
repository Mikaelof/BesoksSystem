<?php
function generateZPL($besok_id, $namn, $foretag, $kontaktperson, $datum_start, $datum_slut, $fika_fm, $fika_em, $lunch, $specialkost, $allergi) {

    // Logga utskrift i databasen
    global $conn;
    $stmt = $conn->prepare("INSERT INTO etikettutskrift (besok_id) VALUES (?)");
    if (!$stmt) {
        error_log("Failed to prepare statement for etikettutskrift: " . $conn->error);
        return "<div class='alert alert-danger'>Kunde inte förbereda fråga för att logga utskrift: " . $conn->error . "</div>";
    }
    $stmt->bind_param("i", $besok_id);
    if (!$stmt->execute()) {
        $error = $conn->error;
        $stmt->close();
        error_log("Failed to log print in etikettutskrift: $error");
        return "<div class='alert alert-danger'>Kunde inte logga utskrift i databasen: $error</div>";
    }
    $stmt->close();

    // Skrivar-IP - lämna tom för att skriva ut på datorns standardskrivare (A4)
    $printer_ip = "DIN_SKRIVARES_IP"; // Ange IP-adressen till din Zebra-skrivare, eller lämna tom

    if (!empty($printer_ip) && $printer_ip !== "DIN_SKRIVARES_IP") {
        // === ZEBRA-UTSKRIFT ===
        $logo_height_dots = 200;
        $max_width_dots = 424;

        $zpl = "^XA\n";
        $zpl .= "^CI28\n";
        $zpl .= "^PW$max_width_dots\n";
        $zpl .= "^LL680\n";
        $zpl .= "^LH0,$logo_height_dots\n";
        $zpl .= "^POI\n";
        $zpl .= "^LS0\n";

        $zpl .= "^FO20,0^CF0,60^FB384,2,1,L^FD$namn^FS\n";
        $zpl .= "^FO20,120^CF0,40^FD$foretag^FS\n";
        $zpl .= "^FO20,180^CF0,36^FDVärd: $kontaktperson^FS\n";

        if ($datum_start === $datum_slut) {
            $zpl .= "^FO20,220^CF0,30^FDGiltig: $datum_start^FS\n";
        } else {
            $zpl .= "^FO20,220^CF0,22^FDGiltig: $datum_start till $datum_slut^FS\n";
        }

        $symbol_y = 270;
        if ($fika_fm) { $zpl .= "^FO20,$symbol_y^A0N,28^FD[Kaffe] FM^FS\n"; $symbol_y += 35; }
        if ($fika_em) { $zpl .= "^FO20,$symbol_y^A0N,28^FD[Kaffe] EM^FS\n"; $symbol_y += 35; }
        if ($lunch)   { $zpl .= "^FO20,$symbol_y^A0N,28^FD[Lunch]^FS\n";    $symbol_y += 35; }
        if ($specialkost || $allergi) { $zpl .= "^FO20,$symbol_y^A0N,28^FD[!] Specialkost/Allergi^FS\n"; }

        $zpl .= "^FO20,400^CF0,40^FDVälkommen!^FS\n";
        $zpl .= "^XZ\n";

        // Spara ZPL för felsökning
        $zpl_file = "/tmp/zpl_output_{$besok_id}.txt";
        file_put_contents($zpl_file, $zpl);

        // Skicka till Zebra-skrivaren
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return "<div class='alert alert-danger'>Kunde inte skapa socket: " . socket_strerror(socket_last_error()) . "</div>";
        }
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);
        if (!socket_connect($socket, $printer_ip, 9100)) {
            socket_close($socket);
            return "<div class='alert alert-danger'>Kunde inte ansluta till skrivaren ($printer_ip:9100): " . socket_strerror(socket_last_error()) . "</div>";
        }
        socket_write($socket, $zpl, strlen($zpl));
        socket_close($socket);

        error_log("Successfully sent ZPL to printer ($printer_ip:9100) for besok_id $besok_id");
        return "<div class='alert alert-success'>Etikett utskriven på Zebra-skrivare!</div>";

    } else {
        // === A4-UTSKRIFT ===
        // Bygger en HTML-badge och öppnar utskriftsdialogen automatiskt

        $extras = [];
        if ($fika_fm)              $extras[] = "☕ Fika förmiddag";
        if ($fika_em)              $extras[] = "☕ Fika eftermiddag";
        if ($lunch)                $extras[] = "🍽️ Lunch";
        if ($specialkost || $allergi) $extras[] = "⚠️ Specialkost" . ($allergi ? ": $allergi" : "");

        $datum_text = ($datum_start === $datum_slut)
            ? "Giltig: $datum_start"
            : "Giltig: $datum_start till $datum_slut";

        $extras_html = !empty($extras)
            ? "<div class='extras'>" . implode("<br>", array_map('htmlspecialchars', $extras)) . "</div>"
            : "";

        $badge_html = "<!DOCTYPE html>
<html lang='sv'>
<head>
    <meta charset='UTF-8'>
    <title>Besöksbricka - " . htmlspecialchars($namn) . "</title>
    <style>
        @page { size: A4; margin: 0; }
        body {
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #fff;
            font-family: Arial, sans-serif;
        }
        .badge {
            width: 85mm;
            height: 54mm;
            border: 2px dashed #999;
            border-radius: 4mm;
            padding: 4mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .badge-header {
            background: #003366;
            color: white;
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            padding: 3mm;
            border-radius: 2mm;
            letter-spacing: 2px;
        }
        .badge-name {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin-top: 2mm;
        }
        .badge-company {
            font-size: 11pt;
            text-align: center;
            color: #444;
        }
        .badge-host {
            font-size: 9pt;
            text-align: center;
            color: #666;
        }
        .badge-date {
            font-size: 8pt;
            text-align: center;
            color: #666;
        }
        .extras {
            font-size: 8pt;
            text-align: center;
            color: #333;
            margin-top: 1mm;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div>
        <div class='badge'>
            <div class='badge-header'>BESÖKARE</div>
            <div class='badge-name'>" . htmlspecialchars($namn) . "</div>
            <div class='badge-company'>" . htmlspecialchars($foretag) . "</div>
            <div class='badge-host'>Värd: " . htmlspecialchars($kontaktperson) . "</div>
            <div class='badge-date'>$datum_text</div>
            $extras_html
        </div>
        <p class='no-print' style='text-align:center; font-size:10pt; color:#999; margin-top:8mm;'>
            Klipp ut längs den streckade linjen
        </p>
    </div>
    <script>window.onload = function() { window.print(); }</script>
</body>
</html>";

        // Spara badge-HTML temporärt och öppna i nytt fönster via session
        $_SESSION['badge_html'] = $badge_html;
        return "<div class='alert alert-info'>
            Ingen Zebra-skrivare konfigurerad – 
            <a href='print_badge.php' target='_blank' class='alert-link'>Klicka här för att skriva ut brickan på din skrivare</a>
        </div>";
    }
}
?>
