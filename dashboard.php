<?php
// Spustí nebo obnoví PHP session
session_start();

// Zkontrolovat, zda je uživatel přihlášen. Pokud ne, přesměrovat na přihlašovací stránku.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php"); // Přesměruje na přihlašovací formulář
    exit(); // Ukončí skript, aby se zabránilo dalšímu zpracování
}

// Zahrne konfigurační soubor pro připojení k databázi
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidence HW - Zákazníci</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Seznam zákazníků</h1>
        <p>
            
        
        <a href="zalozeni_zakaznik_zarizeni_servis.php" class="button">Založit nového zákazníka vč. zařízení a servisu</a>
            </p>

        <?php
        // SQL dotaz pro získání všech zákazníků
        $sql = "SELECT IdZakaznik, Jmeno, Email, Telefon, DatumRegistrace FROM AG_tblZakaznik ORDER BY Jmeno";
        $result = mysqli_query($link, $sql);

        // Kontrola, zda existují zákazníci
        if(mysqli_num_rows($result) > 0){
            echo "<table>";
            echo "<thead>";
            echo "<tr>";
            echo "<th>Jméno</th>";
            echo "<th>Email</th>";
            echo "<th>Telefon</th>";
            echo "<th>Datum registrace</th>";
            echo "<th>Akce</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";

            while($row = mysqli_fetch_assoc($result)){
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['Jmeno']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Telefon']) . "</td>";
                echo "<td>" . htmlspecialchars(date('d.m.Y H:i', strtotime($row['DatumRegistrace']))) . "</td>";
                echo "<td><a href='detail_zakaznika.php?id=" . $row['IdZakaznik'] . "' class='button'>Zobrazit detaily</a></td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        } else {
            echo "<p>V databázi nejsou žádní zákazníci.</p>";
        }

        // Uzavření připojení k databázi
        mysqli_close($link);
        ?>
    </div>
</body>
</html>