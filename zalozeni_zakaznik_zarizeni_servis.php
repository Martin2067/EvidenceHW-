<?php
require_once 'config.php';

$message = ''; // Pro zprávy uživateli

// --- Načtení dat pro dropdown menu ---
// Typy zařízení
$typy_zarizeni = [];
$sql_typy = "SELECT IdTypZarizeni, NazevTypu FROM AG_tblTypZarizeni ORDER BY NazevTypu";
$result_typy = mysqli_query($link, $sql_typy);
while ($row = mysqli_fetch_assoc($result_typy)) {
    $typy_zarizeni[] = $row;
}

// Atributy (parametry) zařízení
$atributy = [];
$sql_atributy = "SELECT IdAtribut, NazevAtributu FROM AG_tblAtribut ORDER BY NazevAtributu";
$result_atributy = mysqli_query($link, $sql_atributy);
while ($row = mysqli_fetch_assoc($result_atributy)) {
    $atributy[] = $row;
}

// --- Zpracování formuláře po odeslání ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Data zákazníka
    $jmeno_zakaznika = trim($_POST['jmeno_zakaznika']);
    $email_zakaznika = trim($_POST['email_zakaznika']);
    $telefon_zakaznika = trim($_POST['telefon_zakaznika']);

    // Data zařízení
    $id_typ_zarizeni = trim($_POST['typ_zarizeni']);
    $nazev_zarizeni = trim($_POST['nazev_zarizeni']);
    $datum_porizeni = trim($_POST['datum_porizeni']);
    $konec_zaruky = trim($_POST['konec_zaruky']);
    $qr_string = trim($_POST['qr_string']);
    $parametry = $_POST['parametry'] ?? []; // Pole dynamických parametrů

    // Data servisu (volitelné)
    $datum_servisu = trim($_POST['datum_servisu'] ?? '');
    $typ_servisu = trim($_POST['typ_servisu'] ?? '');
    $popis_servisu = trim($_POST['popis_servisu'] ?? '');
    $servisman = trim($_POST['servisman'] ?? '');

    // --- Validace ---
    if (empty($jmeno_zakaznika) || empty($email_zakaznika) || empty($id_typ_zarizeni) || empty($nazev_zarizeni) || empty($qr_string)) {
        $message = "<p class='error'>Prosím vyplňte všechna povinná pole v sekcích Zákazník a Zařízení (označena *).</p>";
    } elseif (!filter_var($email_zakaznika, FILTER_VALIDATE_EMAIL)) {
        $message = "<p class='error'>Zadejte prosím platnou emailovou adresu pro zákazníka.</p>";
    } else {
        // Kontrola, zda email zákazníka již neexistuje
        $sql_check_email = "SELECT IdZakaznik FROM AG_tblZakaznik WHERE Email = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt_check, "s", $param_email_check);
            $param_email_check = $email_zakaznika;
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $message = "<p class='error'>Zákazník s tímto emailem již existuje. Použijte jiný email nebo jej přidejte k existujícímu zákazníkovi přes detail zákazníka.</p>";
            }
            mysqli_stmt_close($stmt_check);
        }

        // Pokud dosud nejsou žádné chyby, pokračujeme
        if (empty($message)) {
            mysqli_begin_transaction($link); // Spustíme transakci

            try {
                $id_noveho_zakaznika = null;
                $id_noveho_zarizeni = null;

                // 1. Vložení nového zákazníka
                $sql_insert_zakaznik = "INSERT INTO AG_tblZakaznik (Jmeno, Email, Telefon) VALUES (?, ?, ?)";
                if ($stmt_zak = mysqli_prepare($link, $sql_insert_zakaznik)) {
                    mysqli_stmt_bind_param($stmt_zak, "sss", $jmeno_zakaznika, $email_zakaznika, $telefon_zakaznika);
                    if (!mysqli_stmt_execute($stmt_zak)) {
                        throw new Exception("Chyba při vkládání zákazníka: " . mysqli_stmt_error($stmt_zak));
                    }
                    $id_noveho_zakaznika = mysqli_insert_id($link);
                    mysqli_stmt_close($stmt_zak);
                } else {
                    throw new Exception("Příprava dotazu na zákazníka selhala: " . mysqli_error($link));
                }

                // 2. Vložení nového zařízení pro nového zákazníka
                $sql_insert_zarizeni = "INSERT INTO AG_tblZarizeni (IdTypZarizeni, IdZakaznik, NazevZarizeni, DatumPorizeni, KonecZaruky) VALUES (?, ?, ?, ?, ?)";
                if ($stmt_zar = mysqli_prepare($link, $sql_insert_zarizeni)) {
                    mysqli_stmt_bind_param($stmt_zar, "iisss", $id_typ_zarizeni, $id_noveho_zakaznika, $nazev_zarizeni, $datum_porizeni, $konec_zaruky);
                    if (!mysqli_stmt_execute($stmt_zar)) {
                        throw new Exception("Chyba při vkládání zařízení: " . mysqli_stmt_error($stmt_zar));
                    }
                    $id_noveho_zarizeni = mysqli_insert_id($link);
                    mysqli_stmt_close($stmt_zar);
                } else {
                    throw new Exception("Příprava dotazu na zařízení selhala: " . mysqli_error($link));
                }

                // 3. Vložení QR kódu a jeho přiřazení k zařízení
                $sql_insert_qr = "INSERT INTO AG_tblQRKod (QRString, IdZarizeni, JePouzit) VALUES (?, ?, 1)";
                if ($stmt_qr = mysqli_prepare($link, $sql_insert_qr)) {
                    mysqli_stmt_bind_param($stmt_qr, "si", $qr_string, $id_noveho_zarizeni);
                    if (!mysqli_stmt_execute($stmt_qr)) {
                        throw new Exception("Chyba při vkládání QR kódu: " . mysqli_stmt_error($stmt_qr));
                    }
                    mysqli_stmt_close($stmt_qr);
                } else {
                    throw new Exception("Příprava dotazu na QR kód selhala: " . mysqli_error($link));
                }

                // 4. Vložení dynamických parametrů (atributů)
                $sql_insert_hodnota_atributu = "INSERT INTO AG_tblHodnotaAtributu (IdZarizeni, IdAtribut, Hodnota) VALUES (?, ?, ?)";
                if ($stmt_attr = mysqli_prepare($link, $sql_insert_hodnota_atributu)) {
                    foreach ($atributy as $attr_def) { // Procházíme definované atributy
                        $current_id_atribut = $attr_def['IdAtribut'];
                        $hodnota = $parametry[$current_id_atribut] ?? '';
                        if (!empty(trim($hodnota))) { // Vkládáme jen vyplněné hodnoty
                            mysqli_stmt_bind_param($stmt_attr, "iis", $id_noveho_zarizeni, $current_id_atribut, $hodnota);
                            if (!mysqli_stmt_execute($stmt_attr)) {
                                throw new Exception("Chyba při vkládání atributu " . htmlspecialchars($attr_def['NazevAtributu']) . ": " . mysqli_stmt_error($stmt_attr));
                            }
                        }
                    }
                    mysqli_stmt_close($stmt_attr);
                } else {
                     throw new Exception("Příprava dotazu na atributy selhala: " . mysqli_error($link));
                }

                // 5. Vložení servisního záznamu (pokud je vyplněn)
                if (!empty($typ_servisu) && !empty($popis_servisu) && !empty($servisman)) {
                    $sql_insert_servis = "INSERT INTO AG_tblServis (IdZarizeni, DatumServisu, TypServisu, Popis, Servisman) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt_servis = mysqli_prepare($link, $sql_insert_servis)) {
                        mysqli_stmt_bind_param($stmt_servis, "issss", $id_noveho_zarizeni, $datum_servisu, $typ_servisu, $popis_servisu, $servisman);
                        if (!mysqli_stmt_execute($stmt_servis)) {
                            throw new Exception("Chyba při vkládání servisního záznamu: " . mysqli_stmt_error($stmt_servis));
                        }
                        mysqli_stmt_close($stmt_servis);
                    } else {
                        throw new Exception("Příprava dotazu na servis selhala: " . mysqli_error($link));
                    }
                }

                mysqli_commit($link); // Potvrdíme transakci
                $message = "<p class='success'>Nový zákazník, zařízení a související záznamy byly úspěšně přidány!</p>";
                // Volitelné: Po úspěšném vložení vyčistíme formulář
                $_POST = array(); // Resetuje POST data pro zobrazení prázdného formuláře
            } catch (Exception $e) {
                mysqli_rollback($link); // Vrátíme transakci zpět v případě chyby
                $message = "<p class='error'>Došlo k chybě: " . $e->getMessage() . "</p>";
            }
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
    <title>Založit nového zákazníka, zařízení a servis</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <p><a href="index.php" class="button back-button">&larr; Zpět na seznam zákazníků</a></p>
        <h1>Založit nového zákazníka, zařízení a servis</h1>
        <?php echo $message; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <fieldset>
                <legend>Informace o zákazníkovi</legend>
                <div class="form-group">
                    <label for="jmeno_zakaznika">Jméno zákazníka: <span class="required">*</span></label>
                    <input type="text" id="jmeno_zakaznika" name="jmeno_zakaznika" value="<?php echo htmlspecialchars($_POST['jmeno_zakaznika'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email_zakaznika">Email: <span class="required">*</span></label>
                    <input type="email" id="email_zakaznika" name="email_zakaznika" value="<?php echo htmlspecialchars($_POST['email_zakaznika'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="telefon_zakaznika">Telefon:</label>
                    <input type="text" id="telefon_zakaznika" name="telefon_zakaznika" value="<?php echo htmlspecialchars($_POST['telefon_zakaznika'] ?? ''); ?>">
                </div>
            </fieldset>

            <fieldset>
                <legend>Informace o zařízení</legend>
                <div class="form-group">
                    <label for="typ_zarizeni">Typ zařízení: <span class="required">*</span></label>
                    <select id="typ_zarizeni" name="typ_zarizeni" required>
                        <option value="">Vyberte typ zařízení</option>
                        <?php
                        $selected_type = $_POST['typ_zarizeni'] ?? '';
                        foreach ($typy_zarizeni as $typ): ?>
                            <option value="<?php echo $typ['IdTypZarizeni']; ?>" <?php echo ($selected_type == $typ['IdTypZarizeni']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($typ['NazevTypu']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nazev_zarizeni">Název zařízení: <span class="required">*</span></label>
                    <input type="text" id="nazev_zarizeni" name="nazev_zarizeni" value="<?php echo htmlspecialchars($_POST['nazev_zarizeni'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="datum_porizeni">Datum pořízení:</label>
                    <input type="date" id="datum_porizeni" name="datum_porizeni" value="<?php echo htmlspecialchars($_POST['datum_porizeni'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="konec_zaruky">Konec záruky:</label>
                    <input type="date" id="konec_zaruky" name="konec_zaruky" value="<?php echo htmlspecialchars($_POST['konec_zaruky'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="qr_string">QR kód (řetězec): <span class="required">*</span></label>
                    <input type="text" id="qr_string" name="qr_string" value="<?php echo htmlspecialchars($_POST['qr_string'] ?? ''); ?>" required>
                </div>

                <h3>Parametry zařízení:</h3>
                <p>Vyplňte pouze relevantní parametry pro toto zařízení.</p>
                <?php foreach ($atributy as $attr): ?>
                    <div class="form-group">
                        <label for="param_<?php echo $attr['IdAtribut']; ?>"><?php echo htmlspecialchars($attr['NazevAtributu']); ?>:</label>
                        <input type="text" id="param_<?php echo $attr['IdAtribut']; ?>" name="parametry[<?php echo $attr['IdAtribut']; ?>]" value="<?php echo htmlspecialchars($_POST['parametry'][$attr['IdAtribut']] ?? ''); ?>">
                    </div>
                <?php endforeach; ?>
            </fieldset>

            <fieldset>
                <legend>Servisní záznam (volitelné)</legend>
                <p>Pokud chcete přidat první servisní záznam hned teď.</p>
                <div class="form-group">
                    <label for="datum_servisu">Datum a čas servisu:</label>
                    <input type="datetime-local" id="datum_servisu" name="datum_servisu" value="<?php echo htmlspecialchars($_POST['datum_servisu'] ?? date('Y-m-d\TH:i')); ?>">
                </div>

                <div class="form-group">
                    <label for="typ_servisu">Typ servisu:</label>
                    <input type="text" id="typ_servisu" name="typ_servisu" value="<?php echo htmlspecialchars($_POST['typ_servisu'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="popis_servisu">Popis:</label>
                    <textarea id="popis_servisu" name="popis_servisu" rows="5"><?php echo htmlspecialchars($_POST['popis_servisu'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="servisman">Servisman:</label>
                    <input type="text" id="servisman" name="servisman" value="<?php echo htmlspecialchars($_POST['servisman'] ?? ''); ?>">
                </div>
            </fieldset>

            <div class="form-group">
                <input type="submit" value="Založit vše" class="button">
            </div>
        </form>
    </div>
</body>
</html>