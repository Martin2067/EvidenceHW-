<?php
require_once 'config.php';

$message = ''; // Proměnná pro zprávy uživateli (úspěch/chyba)
$id_zarizeni = null;
$id_zakaznika_original = null; // Uchování ID zákazníka pro návrat zpět
$nazev_zarizeni = '';
$jmeno_zakaznika = '';
$existujici_parametry = []; // Asociativní pole pro snadné předvyplnění formuláře (klíč = IdAtribut)
$vsechny_atributy = []; // Všechny definované atributy v DB (pro možnost přidání nových)

// --- Získání ID zařízení a ID zákazníka z URL parametrů ---
if (isset($_GET['id_zarizeni']) && !empty(trim($_GET['id_zarizeni']))) {
    $id_zarizeni = mysqli_real_escape_string($link, $_GET['id_zarizeni']);
    $id_zakaznika_original = mysqli_real_escape_string($link, $_GET['id_zakaznika_original'] ?? '');

    // Načtení názvu zařízení a jména zákazníka pro zobrazení v záhlaví
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
                $message = "<p class='error'>Zařízení s ID " . htmlspecialchars($id_zarizeni) . " nebylo nalezeno.</p>";
                $id_zarizeni = null; // Invalidní ID, formulář se nezobrazí
            }
        }
        mysqli_stmt_close($stmt_info);
    }

    // Načtení existujících parametrů (hodnot atributů) pro toto zařízení
    $sql_exist_param = "SELECT ha.IdHodnotaAtributu, ha.IdAtribut, a.NazevAtributu, ha.Hodnota
                        FROM AG_tblHodnotaAtributu AS ha
                        JOIN AG_tblAtribut AS a ON ha.IdAtribut = a.IdAtribut
                        WHERE ha.IdZarizeni = ?";
    if ($stmt_exist = mysqli_prepare($link, $sql_exist_param)) {
        mysqli_stmt_bind_param($stmt_exist, "i", $id_zarizeni);
        if (mysqli_stmt_execute($stmt_exist)) {
            $result_exist = mysqli_stmt_get_result($stmt_exist);
            while ($row = mysqli_fetch_assoc($result_exist)) {
                // Ukládáme parametry do asociativního pole pro snadné předvyplnění
                $existujici_parametry[$row['IdAtribut']] = [
                    'IdHodnotaAtributu' => $row['IdHodnotaAtributu'],
                    'NazevAtributu' => $row['NazevAtributu'],
                    'Hodnota' => $row['Hodnota']
                ];
            }
        }
        mysqli_stmt_close($stmt_exist);
    }

    // Načtení VŠECH definovaných atributů z AG_tblAtribut (aby bylo možné přidat i nové, dosud nepoužité parametry)
    $sql_all_attr = "SELECT IdAtribut, NazevAtributu FROM AG_tblAtribut ORDER BY NazevAtributu";
    $result_all_attr = mysqli_query($link, $sql_all_attr);
    while ($row = mysqli_fetch_assoc($result_all_attr)) {
        $vsechny_atributy[] = $row;
    }

} else {
    $message = "<p class='error'>Nebylo zadáno ID zařízení pro editaci parametrů.</p>";
}

// --- Zpracování formuláře po odeslání (metoda POST) ---
// Formulář se zpracuje pouze, pokud bylo platné ID zařízení a formulář byl odeslán
if ($id_zarizeni && $_SERVER["REQUEST_METHOD"] == "POST") {
    $parametry_odeslane = $_POST['parametry'] ?? []; // Získáme pole parametrů z formuláře

    mysqli_begin_transaction($link); // Spustíme databázovou transakci pro atomické operace

    try {
        // SQL dotaz pro aktualizaci existující hodnoty atributu
        $sql_update_atributu = "UPDATE AG_tblHodnotaAtributu SET Hodnota = ? WHERE IdHodnotaAtributu = ?";
        // SQL dotaz pro vložení nové hodnoty atributu
        $sql_insert_atributu = "INSERT INTO AG_tblHodnotaAtributu (IdZarizeni, IdAtribut, Hodnota) VALUES (?, ?, ?)";

        // Připravíme si prepared statements před smyčkou pro efektivitu
        if ($stmt_update = mysqli_prepare($link, $sql_update_atributu)) {
            if ($stmt_insert = mysqli_prepare($link, $sql_insert_atributu)) {
                // Projdeme VŠECHNY definované atributy, abychom zkontrolovali, zda byly změněny, přidány nebo smazány
                foreach ($vsechny_atributy as $attr_def) {
                    $id_aktualniho_atributu_def = $attr_def['IdAtribut'];
                    // Získáme novou hodnotu z odeslaného formuláře (může být prázdná)
                    $nova_hodnota = trim($parametry_odeslane[$id_aktualniho_atributu_def] ?? '');

                    // --- Logika pro aktualizaci, vložení nebo smazání ---
                    // 1. Pokud atribut pro toto zařízení již existoval (má záznam v AG_tblHodnotaAtributu)
                    if (isset($existujici_parametry[$id_aktualniho_atributu_def])) {
                        $id_hodnota_atributu = $existujici_parametry[$id_aktualniho_atributu_def]['IdHodnotaAtributu'];

                        if (empty($nova_hodnota)) {
                            // Pokud uživatel vyprázdnil pole, smažeme existující záznam atributu
                            $sql_delete_atributu = "DELETE FROM AG_tblHodnotaAtributu WHERE IdHodnotaAtributu = ?";
                            if ($stmt_delete = mysqli_prepare($link, $sql_delete_atributu)) {
                                mysqli_stmt_bind_param($stmt_delete, "i", $id_hodnota_atributu);
                                if (!mysqli_stmt_execute($stmt_delete)) {
                                    throw new Exception("Chyba při mazání atributu: " . mysqli_stmt_error($stmt_delete));
                                }
                                mysqli_stmt_close($stmt_delete);
                            } else {
                                throw new Exception("Příprava dotazu na mazání selhala: " . mysqli_error($link));
                            }
                        } else {
                            // Pokud je hodnota změněna nebo zůstala stejná a není prázdná, aktualizujeme
                            mysqli_stmt_bind_param($stmt_update, "si", $nova_hodnota, $id_hodnota_atributu);
                            if (!mysqli_stmt_execute($stmt_update)) {
                                throw new Exception("Chyba při aktualizaci atributu '" . htmlspecialchars($attr_def['NazevAtributu']) . "': " . mysqli_stmt_error($stmt_update));
                            }
                        }
                    } elseif (!empty($nova_hodnota)) {
                        // 2. Pokud atribut pro toto zařízení neexistoval, ale uživatel zadal novou hodnotu, vložíme nový záznam
                        mysqli_stmt_bind_param($stmt_insert, "iis", $id_zarizeni, $id_aktualniho_atributu_def, $nova_hodnota);
                        if (!mysqli_stmt_execute($stmt_insert)) {
                            throw new Exception("Chyba při vkládání nového atributu '" . htmlspecialchars($attr_def['NazevAtributu']) . "': " . mysqli_stmt_error($stmt_insert));
                        }
                    }
                }
                mysqli_stmt_close($stmt_update);
                mysqli_stmt_close($stmt_insert);
            } else {
                 throw new Exception("Příprava dotazu na vložení atributu selhala: " . mysqli_error($link));
            }
        } else {
            throw new Exception("Příprava dotazu na aktualizaci atributu selhala: " . mysqli_error($link));
        }

        mysqli_commit($link); // Pokud vše proběhlo bez chyby, potvrdíme transakci
        $message = "<p class='success'>Parametry zařízení byly úspěšně aktualizovány!</p>";

        // Po úspěšné aktualizaci znovu načteme existující parametry, aby se formulář zobrazil s novými daty
        // Toto je důležité, pokud uživatel smaže/přidá hodnotu a zůstane na stránce.
        $existujici_parametry = [];
        $result_exist_after_update = mysqli_query($link, $sql_exist_param);
        if ($result_exist_after_update) {
             while ($row = mysqli_fetch_assoc($result_exist_after_update)) {
                $existujici_parametry[$row['IdAtribut']] = [
                    'IdHodnotaAtributu' => $row['IdHodnotaAtributu'],
                    'NazevAtributu' => $row['NazevAtributu'],
                    'Hodnota' => $row['Hodnota']
                ];
            }
            mysqli_free_result($result_exist_after_update);
        }

        // Volitelně: Přesměrování zpět na detail zákazníka po úspěšné operaci
        // header("location: detail_zakaznika.php?id=" . $id_zakaznika_original);
        // exit();

    } catch (Exception $e) {
        mysqli_rollback($link); // Pokud došlo k chybě, vrátíme celou transakci zpět
        $message = "<p class='error'>Došlo k chybě při aktualizaci parametrů: " . $e->getMessage() . "</p>";
    }
}
mysqli_close($link); // Na konci skriptu vždy uzavřeme připojení k databázi
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editovat parametry zařízení</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <p><a href="detail_zakaznika.php?id=<?php echo htmlspecialchars($id_zakaznika_original); ?>" class="button back-button">&larr; Zpět na detail zákazníka</a></p>

        <h1>Editovat parametry zařízení</h1>

        <?php if ($id_zarizeni): // Zobrazíme formulář, pouze pokud bylo nalezeno platné ID zařízení ?>
            <p>Zařízení: <strong><?php echo htmlspecialchars($nazev_zarizeni); ?></strong> (Zákazník: <?php echo htmlspecialchars($jmeno_zakaznika); ?>)</p>
            <?php echo $message; // Zobrazíme zprávu o úspěchu/chybě ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id_zarizeni=' . htmlspecialchars($id_zarizeni) . '&id_zakaznika_original=' . htmlspecialchars($id_zakaznika_original); ?>" method="post">
                <h3>Parametry:</h3>
                <p>Upravte existující hodnoty nebo vyplňte nové. Pro smazání parametru jeho pole vyprázdněte.</p>
                <?php foreach ($vsechny_atributy as $attr_def): // Projdeme všechny definované atributy ?>
                    <?php
                    $id_attr = $attr_def['IdAtribut'];
                    $nazev_attr = $attr_def['NazevAtributu'];
                    // Předvyplníme hodnotu, pokud existuje pro toto zařízení, jinak prázdný řetězec
                    $hodnota_predvyplneni = $existujici_parametry[$id_attr]['Hodnota'] ?? '';
                    ?>
                    <div class="form-group">
                        <label for="param_<?php echo $id_attr; ?>"><?php echo htmlspecialchars($nazev_attr); ?>:</label>
                        <input type="text" id="param_<?php echo $id_attr; ?>" name="parametry[<?php echo $id_attr; ?>]" value="<?php echo htmlspecialchars($hodnota_predvyplneni); ?>">
                    </div>
                <?php endforeach; ?>

                <div class="form-group">
                    <input type="submit" value="Uložit parametry" class="button">
                </div>
            </form>
        <?php else: // Pokud nebylo platné ID zařízení ?>
            <?php echo $message; ?>
            <p>Nelze editovat parametry, protože nebylo platné ID zařízení.</p>
        <?php endif; ?>
    </div>
</body>
</html>
