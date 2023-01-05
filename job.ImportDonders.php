
Gesprek geopend. 1 ongelezen bericht.

Spring naar content
Gmail gebruiken met schermlezers
1 van 910
donders
Inbox

Walter Pothof | Factif BV
Bijlagen
20:21 (1 minuut geleden)
aan mij

   
Bericht vertalen
Uitzetten voor: Engels
 

7
 bijlagen
  • Gescand door Gmail

<?php

    // Process filename from url or process latest offered JSON file
    $filename = null;
    if ($_GET['filename']) {
        $filename = $_GET['filename'];
    }
    // Process filename from url or process latest offered JSON file
    $debug = false;
    if ($_GET['debug'] === 'true') {
        $debug = true;
    }

    $documentRoot = $_SERVER["DOCUMENT_ROOT"] = "/var/www/public";
    require_once("includes/include.SiteConfig.php");
    //require_once($documentRoot . '/admin/classes/class.ImportProcessorDonders.php');

    $dondersVoorraadVerwerking = new ImportProcessorDonders($debug);
    $dondersVoorraadVerwerking->processJsonContent($filename);
