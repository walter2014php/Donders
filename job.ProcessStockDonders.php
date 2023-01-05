<?php
    $documentRoot = $_SERVER["DOCUMENT_ROOT"] = "/var/www/public";
    require_once("includes/include.SiteConfig.php");
    //require_once($documentRoot . '/admin/classes/class.ImportProcessorDonders.php');

    $dondersVoorraadVerwerking = new ProcessExternStockDonders(ImportProcessorDonders::EXTERN_REFERENCE);
    $dondersVoorraadVerwerking->ProcessStock();
