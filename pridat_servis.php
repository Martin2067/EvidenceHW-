<?php
require_once 'config.php';

$message = '';
$id_zarizeni = null;
$nazev_zarizeni = '';
$jmeno_zakaznika = '';

// Získání ID zařízení z URL
if (isset($_GET['id_zarizeni']) && !empty(trim($_GET['id_zarizeni']))) {
    $id_zarizeni = mysqli_real_escape_string($link, $_GET['id_zarizeni']);

    // Načtení názvu zařízení a jména zákazníka pro zobrazení
    $sql_info = "SELECT za.NazevZarizeni, z.Jmeno AS JmenoZakaznika
                 FROM AG_tblZarizeni AS za
                 JOIN AG_tblZakaznik AS z ON za.IdZakaznik = z.IdZakaznik
                 WHERE za.IdZarizeni = ?";
    if ($stmt_info = mysqli_prepare($link, $sql_info)) {
        mysqli_stmt_bind_param($stmt_info, "i", $id_zarizeni);
        if (mysqli_stmt_execute($stmt_info)) {
            $result_info = mysqli_stmt_get_result($stmt_info);
            if (mysqli_num_rows($result_info) == 1) {
                $row_info = mysqli_fetch_assoc($result_info);
                $nazev_zarizeni = $row_info['NazevZarizeni'];
                $jmeno_zakaznika = $row_info['JmenoZakaznika'];
            } else {
                $message = "<p class='error'>Zařízení nebylo nalezeno.</p>";
                $id_zarizeni = null; // Resetujeme ID, aby se formulář nezobrazoval
            }
        }
        mysqli_stmt_close($stmt_info);
    }
} else {
    $message = "<p class='error'>Nebylo zadáno ID zařízení.</p>";
}


if ($id_zarizeni && $_SERVER["REQUEST_METHOD"] == "POST") {
    // Zpracování dat z formuláře
    $datum_servisu = trim($_POST['datum_servisu']);
    $typ_servisu = trim($_POST['typ_servisu']);
    $popis = trim($_POST['popis']);
    $servisman = trim($_POST['servisman']);

    if (empty($datum_servisu) || empty($typ_servisu) || empty($popis) || empty($servisman)) {
        $message = "<p class='error'>Prosím vyplňte všechna povinná pole.</p>";
    } else {
        $sql_insert_servis = "INSERT INTO AG_tblServis (IdZarizeni, DatumServisu, TypServisu, Popis, Servisman) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql_insert_servis)) {
            mysqli_stmt_bind_param($stmt, "issss", $id_zarizeni, $datum_servisu, $typ_servisu, $popis, $servisman);

            if (mysqli_stmt_execute($stmt)) {
                $message = "<p class='success'>Servisní záznam byl úspěšně přidán!</p>";
                // Volitelně: přesměrování zpět na detail zařízení
                // header("location: detail_zakaznika.php?id=" . $_GET['id_zakaznika_original']); // Museli bychom předat i ID zákazníka
            } else {
                $message = "<p class='error'>Chyba při přidávání servisního záznamu: " . mysqli_stmt_error($stmt) . "</p>";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "<p class='error'>Příprava dotazu selhala: " . mysqli_error($link) . "</p>";
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
    <title>Přidat servisní záznam</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <p><a href="detail_zakaznika.php?id=<?php echo htmlspecialchars($_GET['id_zakaznika_original'] ?? ''); ?>" class="button back-button">&larr; Zpět na detail zákazníka</a></p>
        <h1>Přidat servisní záznam pro zařízení</h1>

        <?php if ($id_zarizeni && $nazev_zarizeni): ?>
            <p>Zařízení: <strong><?php echo htmlspecialchars($nazev_zarizeni); ?></strong> (Zákazník: <?php echo htmlspecialchars($jmeno_zakaznika); ?>)</p>
            <?php echo $message; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id_zarizeni=' . htmlspecialchars($id_zarizeni) . '&id_zakaznika_original=' . htmlspecialchars($_GET['id_zakaznika_original'] ?? ''); ?>" method="post">
                <div class="form-group">
                    <label for="datum_servisu">Datum a čas servisu:</label>
                    <input type="datetime-local" id="datum_servisu" name="datum_servisu" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="typ_servisu">Typ servisu:</label>
                    <input type="text" id="typ_servisu" name="typ_servisu" required>
                </div>

                <div class="form-group">
                    <label for="popis">Popis:</label>
                    <textarea id="popis" name="popis" rows="5" required></textarea>
                </div>

                <div class="form-group">
                    <label for="servisman">Servisman:</label>
                    <input type="text" id="servisman" name="servisman" required>
                </div>

                <div class="form-group">
                    <input type="submit" value="Přidat servisní záznam" class="button">
                </div>
            </form>
        <?php else: ?>
            <?php echo $message; ?>
            <p>Nelze přidat servisní záznam, protože nebylo platné ID zařízení.</p>
        <?php endif; ?>
    </div>
</body>
</html>