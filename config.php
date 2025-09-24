<?php
// Konfigurace databáze
define('DB_SERVER', 'localhost'); 
define('DB_USERNAME', 'root'); 
define('DB_PASSWORD', '');
define('DB_NAME', 'evidence_hw'); 

// Pokus o připojení k MySQL databázi
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Kontrola připojení
if($link === false){
    die("CHYBA: Nepodařilo se připojit k databázi. " . mysqli_connect_error());
} else {
   
    mysqli_set_charset($link, "utf8mb4");
    // echo "Připojení k databázi proběhlo úspěšně!"; 
}
?>