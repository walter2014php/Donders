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
