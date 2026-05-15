<?php
function generateZPL($besok_id, $namn, $foretag, $kontaktperson, $datum_start, $datum_slut, $fika_fm, $fika_em, $lunch, $specialkost, $allergi) {
    // Anta 203 dpi (8 dots/mm). Etikett: 53 mm bred (424 dots), 85 mm hög (680 dots), logo 20 mm (160 dots)
    $logo_height_dots = 200; // 20 mm * 8 dots/mm
    $max_width_dots = 424; // 53 mm * 8 dots/mm
    $max_text_height_dots = 520; // 65 mm * 8 dots/mm

    $zpl = "^XA\n";
    $zpl .= "^CI28\n"; // UTF-8 encoding for special characters
    $zpl .= "^PW$max_width_dots\n"; // Set print width to 424 dots (53 mm)
    $zpl .= "^LL680\n"; // Full label height (85 mm * 8 = 680 dots)
    $zpl .= "^LH0,$logo_height_dots\n"; // Origin at bottom of logo
    $zpl .= "^POI\n"; // Inverted orientation
    $zpl .= "^LS0\n"; // No left shift

    // Name field (centered with ^FB for wrapping long names)
    $zpl .= "^FO20,0^CF0,60^FB384,2,1,L^FD$namn^FS\n"; // Centered, 384-dot width, 2 lines max
    $zpl .= "^FO20,120^CF0,40^FD$foretag^FS\n";
    $zpl .= "^FO20,180^CF0,36^FDVärd: $kontaktperson^FS\n";
    // Giltighetsdatum - visa endast startdatum om start och slut är samma
if ($datum_start === $datum_slut) {
    $zpl .= "^FO20,220^CF0,30^FDGiltig: $datum_start^FS\n";
} else {
    $zpl .= "^FO20,220^CF0,22^FDGiltig: $datum_start till $datum_slut^FS\n";
}

    // Symbols with adjusted spacing
    $symbol_y = 270; // Start position for symbols
    if ($fika_fm) {
        $zpl .= "^FO20,$symbol_y^A0N,28^FD[Kaffe] FM^FS\n";
        $symbol_y += 35;
    }
    if ($fika_em) {
        $zpl .= "^FO20,$symbol_y^A0N,28^FD[Kaffe] EM^FS\n";
        $symbol_y += 35;
    }
    if ($lunch) {
        $zpl .= "^FO20,$symbol_y^A0N,28^FD[Lunch]^FS\n";
        $symbol_y += 35;
    }
    if ($specialkost || $allergi) {
        $zpl .= "^FO20,$symbol_y^A0N,28^FD[!] Specialkost/Allergi^FS\n";
        $symbol_y += 35;
    }

    $zpl .= "^FO20,400^CF0,40^FDVälkommen!^FS\n";
    $zpl .= "^XZ\n";

    // Spara ZPL-koden till en fil för felsökning
    $zpl_file = "/tmp/zpl_output_{$besok_id}.txt";
    if (!file_put_contents($zpl_file, $zpl)) {
        error_log("Failed to save ZPL code to $zpl_file: " . error_get_last()['message']);
        return "<div class='alert alert-danger'>Kunde inte spara ZPL-kod till fil: $zpl_file<br>ZPL-kod:<br><pre>$zpl</pre></div>";
    }

    // Logga utskrift i databasen
    global $conn;
    $stmt = $conn->prepare("INSERT INTO etikettutskrift (besok_id) VALUES (?)");
    if (!$stmt) {
        error_log("Failed to prepare statement for etikettutskrift: " . $conn->error);
        return "<div class='alert alert-danger'>Kunde inte förbereda fråga för att logga utskrift: " . $conn->error . "<br>ZPL-kod:<br><pre>$zpl</pre></div>";
    }
    $stmt->bind_param("i", $besok_id);
    if (!$stmt->execute()) {
        $error = $conn->error;
        $stmt->close();
        error_log("Failed to log print in etikettutskrift: $error");
        return "<div class='alert alert-danger'>Kunde inte logga utskrift i databasen: $error<br>ZPL-kod:<br><pre>$zpl</pre></div>";
    }
    $stmt->close();

    // Försök skicka ZPL till skrivaren
    $printer_ip = "DIN_SKRIVARES_IP"; // Ange IP-adressen till din Zebra-skrivare
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        $error = socket_strerror(socket_last_error());
        error_log("Failed to create socket: $error");
        return "<div class='alert alert-danger'>Kunde inte skapa socket: $error<br>ZPL-kod:<br><pre>$zpl</pre></div>";
    }
    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 5, 'usec' => 0)); // Timeout på 5 sekunder
    $result = socket_connect($socket, $printer_ip, 9100);
    if ($result === false) {
        $error = socket_strerror(socket_last_error());
        socket_close($socket);
        error_log("Failed to connect to printer ($printer_ip:9100): $error");
        return "<div class='alert alert-danger'>Kunde inte ansluta till skrivaren ($printer_ip:9100): $error<br>ZPL-kod:<br><pre>$zpl</pre></div>";
    }
    $bytes_written = socket_write($socket, $zpl, strlen($zpl));
    if ($bytes_written === false || $bytes_written < strlen($zpl)) {
        $error = socket_strerror(socket_last_error());
        socket_close($socket);
        error_log("Failed to write to printer: $error");
        return "<div class='alert alert-danger'>Kunde inte skriva till skrivaren: $error<br>ZPL-kod:<br><pre>$zpl</pre></div>";
    }
    socket_close($socket);
    error_log("Successfully sent ZPL to printer ($printer_ip:9100) for besok_id $besok_id");
    return "<div class='alert alert-success'>Etikett utskriven!<br>ZPL-kod:<br><pre>$zpl</pre></div>";
}
?>