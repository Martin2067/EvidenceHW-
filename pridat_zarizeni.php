<?php
require_once 'config.php';

$message = ''; // Pro zprávy uživateli

// Získání typů zařízení pro dropdown menu
$typy_zarizeni = [];
$sql_typy = "SELECT IdTypZarizeni, NazevTypu FROM AG_tblTypZarizeni ORDER BY NazevTypu";
$result_typy = mysqli_query($link, $sql_typy);
while ($row = mysqli_fetch_assoc($result_typy)) {
    $typy_zarizeni[] = $row;
}

// Získání zákazníků pro dropdown menu
$zakaznici = [];
$sql_zakaznici = "SELECT IdZakaznik, Jmeno FROM AG_tblZakaznik ORDER BY Jmeno";
$result_zakaznici = mysqli_query($link, $sql_zakaznici);
while ($row = mysqli_fetch_assoc($result_zakaznici)) {
    $zakaznici[] = $row;
}

// Získání všech definovaných atributů pro dynamické přidávání
$atributy = [];
$sql_atributy = "SELECT IdAtribut, NazevAtributu FROM AG_tblAtribut ORDER BY NazevAtributu";
$result_atributy = mysqli_query($link, $sql_atributy);
while ($row = mysqli_fetch_assoc($result_atributy)) {
    $atributy[] = $row;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Zpracování dat z formuláře
    $id_typ_zarizeni = trim($_POST['typ_zarizeni']);
    $id_zakaznik = trim($_POST['zakaznik']);
    $nazev_zarizeni = trim($_POST['nazev_zarizeni']);
    $datum_porizeni = trim($_POST['datum_porizeni']);
    $konec_zaruky = trim($_POST['konec_zaruky']);
    $qr_string = trim($_POST['qr_string']);
    $parametry = $_POST['parametry'] ?? []; // Pole dynamických parametrů

    // Validace (jednoduchá, v reálné aplikaci by byla komplexnější)
    if (empty($id_typ_zarizeni) || empty($id_zakaznik) || empty($nazev_zarizeni) || empty($qr_string)) {
        $message = "<p class='error'>Prosím vyplňte všechna povinná pole (Typ zařízení, Zákazník, Název zařízení, QR kód).</p>";
    } else {
        mysqli_begin_transaction($link); // Spustíme transakci

        try {
            // 1. Vložení nového zařízení
            $sql_insert_zarizeni = "INSERT INTO AG_tblZarizeni (IdTypZarizeni, IdZakaznik, NazevZarizeni, DatumPorizeni, KonecZaruky) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql_insert_zarizeni)) {
                mysqli_stmt_bind_param($stmt, "iisss", $id_typ_zarizeni, $id_zakaznik, $nazev_zarizeni, $datum_porizeni, $konec_zaruky);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Chyba při vkládání zařízení: " . mysqli_stmt_error($stmt));
                }
                $id_noveho_zarizeni = mysqli_insert_id($link);
                mysqli_stmt_close($stmt);
            } else {
                throw new Exception("Příprava dotazu na zařízení selhala: " . mysqli_error($link));
            }

            // 2. Vložení QR kódu a jeho přiřazení k zařízení
            $sql_insert_qr = "INSERT INTO AG_tblQRKod (QRString, IdZarizeni, JePouzit) VALUES (?, ?, 1)";
            if ($stmt = mysqli_prepare($link, $sql_insert_qr)) {
                mysqli_stmt_bind_param($stmt, "si", $qr_string, $id_noveho_zarizeni);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Chyba při vkládání QR kódu: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            } else {
                throw new Exception("Příprava dotazu na QR kód selhala: " . mysqli_error($link));
            }

            // 3. Vložení dynamických parametrů (atributů)
            $sql_insert_hodnota_atributu = "INSERT INTO AG_tblHodnotaAtributu (IdZarizeni, IdAtribut, Hodnota) VALUES (?, ?, ?)";
            if ($stmt_attr = mysqli_prepare($link, $sql_insert_hodnota_atributu)) {
                foreach ($parametry as $id_atribut => $hodnota) {
                    if (!empty(trim($hodnota))) { // Vkládáme jen vyplněné hodnoty
                        mysqli_stmt_bind_param($stmt_attr, "iis", $id_noveho_zarizeni, $id_atribut, $hodnota);
                        if (!mysqli_stmt_execute($stmt_attr)) {
                            throw new Exception("Chyba při vkládání atributu " . htmlspecialchars($atributy[$id_atribut-1]['NazevAtributu']) . ": " . mysqli_stmt_error($stmt_attr));
                        }
                    }
                }
                mysqli_stmt_close($stmt_attr);
            } else {
                 throw new Exception("Příprava dotazu na atributy selhala: " . mysqli_error($link));
            }

            mysqli_commit($link); // Potvrdíme transakci
            $message = "<p class='success'>Nové zařízení a QR kód byly úspěšně přidány!</p>";
            // Zde by mohlo být přesměrování na detail zákazníka nebo detail zařízení
        } catch (Exception $e) {
            mysqli_rollback($link); // Vrátíme transakci zpět v případě chyby
            $message = "<p class='error'>Došlo k chybě: " . $e->getMessage() . "</p>";
        }
    }
}
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přidat nové zařízení</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <p><a href="index.php" class="button back-button">&larr; Zpět na seznam zákazníků</a></p>
        <h1>Přidat nové zařízení</h1>
        <?php echo $message; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="zakaznik">Zákazník:</label>
                <select id="zakaznik" name="zakaznik" required>
                    <option value="">Vyberte zákazníka</option>
                    <?php foreach ($zakaznici as $zak): ?>
                        <option value="<?php echo $zak['IdZakaznik']; ?>"><?php echo htmlspecialchars($zak['Jmeno']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="typ_zarizeni">Typ zařízení:</label>
                <select id="typ_zarizeni" name="typ_zarizeni" required>
                    <option value="">Vyberte typ zařízení</option>
                    <?php foreach ($typy_zarizeni as $typ): ?>
                        <option value="<?php echo $typ['IdTypZarizeni']; ?>"><?php echo htmlspecialchars($typ['NazevTypu']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nazev_zarizeni">Název zařízení:</label>
                <input type="text" id="nazev_zarizeni" name="nazev_zarizeni" required>
            </div>

            <div class="form-group">
                <label for="datum_porizeni">Datum pořízení:</label>
                <input type="date" id="datum_porizeni" name="datum_porizeni">
            </div>

            <div class="form-group">
                <label for="konec_zaruky">Konec záruky:</label>
                <input type="date" id="konec_zaruky" name="konec_zaruky">
            </div>

            <div class="form-group">
                <label for="qr_string">QR kód (řetězec):</label>
                <input type="text" id="qr_string" name="qr_string" required>
            </div>

            <h3>Parametry zařízení:</h3>
            <p>Vyplňte pouze relevantní parametry pro tento typ zařízení.</p>
            <?php foreach ($atributy as $attr): ?>
                <div class="form-group">
                    <label for="param_<?php echo $attr['IdAtribut']; ?>"><?php echo htmlspecialchars($attr['NazevAtributu']); ?>:</label>
                    <input type="text" id="param_<?php echo $attr['IdAtribut']; ?>" name="parametry[<?php echo $attr['IdAtribut']; ?>]">
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <input type="submit" value="Přidat zařízení" class="button">
            </div>
        </form>
    </div>
</body>
</html>