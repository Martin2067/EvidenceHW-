<?php
// Zahrne konfigurační soubor pro připojení k databázi
require_once 'config.php';

$zakaznik_id = null;
// Kontrola, zda bylo předáno ID zákazníka v URL a je neprázdné
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    // Bezpečné získání ID zákazníka z URL pro použití v SQL dotazu
    $zakaznik_id = mysqli_real_escape_string($link, $_GET['id']);
} else {
    // Pokud není platné ID zákazníka, přesměrujeme uživatele zpět na seznam zákazníků
    header("location: index.php");
    exit(); // Ukončíme skript, aby se zabránilo dalšímu zpracování
}

$zakaznik_info = null; // Proměnná pro uložení informací o zákazníkovi
$zarizeni_info = [];   // Pole pro uložení informací o zařízeních zákazníka

// --- Získání informací o zákazníkovi ---
// Používáme prepared statement pro bezpečné dotazy
$sql_zakaznik = "SELECT Jmeno, Email, Telefon FROM AG_tblZakaznik WHERE IdZakaznik = ?";
if ($stmt = mysqli_prepare($link, $sql_zakaznik)) {
    // Naváže proměnnou $param_id na placeholder (?) v dotazu
    mysqli_stmt_bind_param($stmt, "i", $param_id);
    $param_id = $zakaznik_id; // Přiřadí hodnotu ID zákazníka parametru

    // Provede připravený dotaz
    if (mysqli_stmt_execute($stmt)) {
        $result_zakaznik = mysqli_stmt_get_result($stmt); // Získá výsledek dotazu
        if (mysqli_num_rows($result_zakaznik) == 1) {
            // Pokud byl zákazník nalezen, získáme jeho data jako asociativní pole
            $zakaznik_info = mysqli_fetch_assoc($result_zakaznik);
        }
    }
    // Uzavře prepared statement
    mysqli_stmt_close($stmt);
}

// --- Získání zařízení pro daného zákazníka ---
// Spojujeme tabulky AG_tblZarizeni a AG_tblTypZarizeni, abychom získali název typu zařízení
$sql_zarizeni = "SELECT
                    za.IdZarizeni, za.NazevZarizeni, za.DatumPorizeni, za.KonecZaruky, tz.NazevTypu AS TypZarizeni
                 FROM
                    AG_tblZarizeni AS za
                 JOIN
                    AG_tblTypZarizeni AS tz ON za.IdTypZarizeni = tz.IdTypZarizeni
                 WHERE
                    za.IdZakaznik = ?";
if ($stmt = mysqli_prepare($link, $sql_zarizeni)) {
    mysqli_stmt_bind_param($stmt, "i", $param_id);
    $param_id = $zakaznik_id;

    if (mysqli_stmt_execute($stmt)) {
        $result_zarizeni = mysqli_stmt_get_result($stmt);
        // Projdeme všechny nalezené záznamy zařízení a uložíme je do pole
        while ($row_zarizeni = mysqli_fetch_assoc($result_zarizeni)) {
            $zarizeni_info[] = $row_zarizeni;
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail zákazníka - <?php echo htmlspecialchars($zakaznik_info['Jmeno'] ?? 'Neznámý zákazník'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <p><a href="index.php" class="button back-button">&larr; Zpět na seznam zákazníků</a></p>

        <?php if ($zakaznik_info): // Zobrazíme detaily zákazníka, pokud byl nalezen ?>
            <h1>Detail zákazníka: <?php echo htmlspecialchars($zakaznik_info['Jmeno']); ?></h1>

            <p><a href="pridat_zarizeni.php?id_zakaznika_predvyplnit=<?php echo $zakaznik_id; ?>" class="button">Přidat nové zařízení pro tohoto zákazníka</a></p>

            <p><strong>Email:</strong> <?php echo htmlspecialchars($zakaznik_info['Email']); ?></p>
            <p><strong>Telefon:</strong> <?php echo htmlspecialchars($zakaznik_info['Telefon']); ?></p>

            <h2>Zařízení zákazníka</h2>
            <?php if (!empty($zarizeni_info)): // Pokud zákazník má nějaká zařízení ?>
                <?php foreach ($zarizeni_info as $zarizeni): // Projdeme každé zařízení a zobrazíme jeho detaily ?>
                    <div class="device-card">
                        <h3><?php echo htmlspecialchars($zarizeni['NazevZarizeni']); ?> (<?php echo htmlspecialchars($zarizeni['TypZarizeni']); ?>)</h3>
                        <p><strong>Datum pořízení:</strong> <?php echo htmlspecialchars(date('d.m.Y', strtotime($zarizeni['DatumPorizeni']))); ?></p>
                        <p><strong>Konec záruky:</strong> <?php echo htmlspecialchars(date('d.m.Y', strtotime($zarizeni['KonecZaruky']))); ?></p>

                        <p><a href="pridat_servis.php?id_zarizeni=<?php echo $zarizeni['IdZarizeni']; ?>&id_zakaznika_original=<?php echo $zakaznik_id; ?>" class="button small-button">Přidat servis</a></p>

                        <p><a href="edit_parametry.php?id_zarizeni=<?php echo $zarizeni['IdZarizeni']; ?>&id_zakaznika_original=<?php echo $zakaznik_id; ?>" class="button small-button">Editovat parametry</a></p>

                        <h4>Parametry:</h4>
                        <?php
                        // --- Získání atributů pro konkrétní zařízení ---
                        $sql_atributy = "SELECT
                                            a.NazevAtributu, ha.Hodnota
                                         FROM
                                            AG_tblHodnotaAtributu AS ha
                                         JOIN
                                            AG_tblAtribut AS a ON ha.IdAtribut = a.IdAtribut
                                         WHERE
                                            ha.IdZarizeni = ?";
                        if ($stmt_attr = mysqli_prepare($link, $sql_atributy)) {
                            mysqli_stmt_bind_param($stmt_attr, "i", $param_zarizeni_id);
                            $param_zarizeni_id = $zarizeni['IdZarizeni'];

                            if (mysqli_stmt_execute($stmt_attr)) {
                                $result_atributy = mysqli_stmt_get_result($stmt_attr);
                                if (mysqli_num_rows($result_atributy) > 0) {
                                    echo "<ul>";
                                    while ($row_atribut = mysqli_fetch_assoc($result_atributy)) {
                                        echo "<li><strong>" . htmlspecialchars($row_atribut['NazevAtributu']) . ":</strong> " . htmlspecialchars($row_atribut['Hodnota']) . "</li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "<p>K tomuto zařízení nejsou přiřazeny žádné parametry.</p>";
                                }
                            }
                            mysqli_stmt_close($stmt_attr);
                        }
                        ?>

                        <h4>Servisní historie:</h4>
                        <?php
                        // --- Získání servisní historie pro konkrétní zařízení ---
                        $sql_servis = "SELECT
                                           DatumServisu, TypServisu, Popis, Servisman
                                       FROM
                                           AG_tblServis
                                       WHERE
                                           IdZarizeni = ?
                                       ORDER BY DatumServisu DESC"; // Seřadíme od nejnovějších
                        if ($stmt_servis = mysqli_prepare($link, $sql_servis)) {
                            mysqli_stmt_bind_param($stmt_servis, "i", $param_zarizeni_id_servis);
                            $param_zarizeni_id_servis = $zarizeni['IdZarizeni'];

                            if (mysqli_stmt_execute($stmt_servis)) {
                                $result_servis = mysqli_stmt_get_result($stmt_servis);
                                if (mysqli_num_rows($result_servis) > 0) {
                                    echo "<ul>";
                                    while ($row_servis = mysqli_fetch_assoc($result_servis)) {
                                        echo "<li>";
                                        echo "<strong>Datum:</strong> " . htmlspecialchars(date('d.m.Y H:i', strtotime($row_servis['DatumServisu']))) . "<br>";
                                        echo "<strong>Typ:</strong> " . htmlspecialchars($row_servis['TypServisu']) . "<br>";
                                        echo "<strong>Popis:</strong> " . nl2br(htmlspecialchars($row_servis['Popis'])) . "<br>"; // nl2br pro zobrazení zalomení řádků z DB
                                        echo "<strong>Servisman:</strong> " . htmlspecialchars($row_servis['Servisman']);
                                        echo "</li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "<p>K tomuto zařízení neexistuje žádná servisní historie.</p>";
                                }
                            }
                            mysqli_stmt_close($stmt_servis);
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php else: // Pokud zákazník nemá žádná zařízení ?>
                <p>Tento zákazník zatím nemá žádná evidovaná zařízení.</p>
            <?php endif; ?>

        <?php else: // Pokud zákazník s daným ID nebyl nalezen ?>
            <p>Zákazník s daným ID nebyl nalezen.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// Na konci skriptu vždy uzavřeme připojení k databázi
mysqli_close($link);
?>