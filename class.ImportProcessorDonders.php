
Gesprek geopend. 1 ongelezen bericht.

Spring naar content
Gmail gebruiken met schermlezers
1 van 910
donders
Inbox

Walter Pothof | Factif BV
Bijlagen
20:21 (2 minuten geleden)
aan mij

   
Bericht vertalen
Uitzetten voor: Engels
 

7
 bijlagen
  • Gescand door Gmail

<?php

    class ImportProcessorDonders
    {
        private CONST SOURCE = '/var/www/import/donders';
        public const EXTERN_REFERENCE = 1;
        public const EXTERN_REFERENCE_NAME = 'Donders';
        public const SLEUTEL = 'ArtikelNummer';
        private ImportExternProcessor $externImportStockService;
        private ImportExternLogger $importExternLogger;
        private ImportExternValidator $importExternValidator;
        private bool $debug;
        private int $processedArticles = 0;

        public function __construct($debug=false)
        {
            $this->externImportStockService = new ImportExternProcessor(
                ImportProcessorDonders::SLEUTEL,
                ImportProcessorDonders::EXTERN_REFERENCE
            );
            $this->importExternLogger = new ImportExternLogger();
            $this->importExternValidator = new ImportExternValidator(ImportProcessorDonders::EXTERN_REFERENCE_NAME);
            $this->debug = $debug;
        }

        /**
         * Return latest JSON file
         * @return string
         */
        public function getLatestJsonFileName(): string
        {
            // Bekijk de files die klaar staan
            $files = scandir(ImportProcessorDonders::SOURCE, SCANDIR_SORT_DESCENDING);

            // Remove any directory
            foreach ($files as $key => $file) {
                // Remove the storage directory of processed files
                if ($file === 'processed_files') {
                    unset($files[$key]);
                }
                // remove .. and .. (directories)
                if(is_dir($file)) {
                    unset($files[$key]);
                }
            }
            // re-index as we might have unset index[0]
            $files = array_values($files);

            // Validate wat als er geen files zijn gevonden..
            if (count($files) < 10) {
                $this->importExternValidator->noImportFilesFound(ImportProcessorDonders::SOURCE);
            }

            // Validatie op file age (max 3 dagen)
            $this->importExternValidator->validateFileAge(ImportProcessorDonders::SOURCE . '/' . $files[0]);

            return $files[0];
        }

        /**
         * @param string|null $filename
         * @return mixed
         */
        public function geContentJsonFile(?string $filename=null)
        {
            // If you want to test a specific file ...
            if ($filename !== null) {
                $file = file_get_contents(ImportProcessorDonders::SOURCE . '/' . $filename);
            } else {
                $file = file_get_contents(ImportProcessorDonders::SOURCE . '/' . $this->getLatestJsonFileName());
            }

            // Check op lege bestanden
            $this->importExternValidator->validateEmpyFile(ImportProcessorDonders::SOURCE . '/' . $filename);

            return json_decode($file);
        }

        /**
         * Process content JSON
         * @param string|null $filename
         * @return void
         */
        public function processJsonContent(?string $filename=null): void
        {
            $start = microtime(true);
            echo 'Import process started<br>';

            // filename from url or latest JSON file?
            if ($filename === NULL) {
                $filename = $this->getLatestJsonFileName();
            }

            // Get the content of the JSON file to process
            $contentLatestJsonFile = $this->geContentJsonFile($filename);
            //var_dump($contentLatestJsonFile);

            $this->importExternLogger->logStartProcess($filename);

            // Process each article
            foreach ($contentLatestJsonFile as $article) {
                $this->process($article, $filename);

                $this->processedArticles++;
            }

            $this->importExternLogger->logEndProcess($filename);

            // ToDo: DIT WEER AANZETTEN!!, maar ik wil niet steeds die file terug moeten kopieren tijdens testen
            //$this->moveProcesseJsonFile($filename);

            echo 'Import process ended.<br>';
            $totalScriptTime = microtime(true) - $start;
            echo 'Processsed articles: ' . $this->processedArticles . '<br>';
            echo 'Total process time: ' . number_format($totalScriptTime, 2) . ' seconds.<br>';
        }

        private function moveProcesseJsonFile(string $filename): void
        {
            try {
                $source_file = ImportProcessorDonders::SOURCE . '/' . $filename;
                $destination_path = ImportProcessorDonders::SOURCE . '/' . 'processed_files/';
                rename($source_file, $destination_path . pathinfo($source_file, PATHINFO_BASENAME));
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }

        /**
         * @param object $article
         * @param string $filename
         * @return void
         */
        public function process(object $article, string $filename): void
        {
            if ($this->debug) {
                echo 'Processed artile: <br>';
                var_dump($article);
            }

            // Insert or update imp_extern_content record
            $content = $this->externImportStockService->getOrInsertContent($article);

            // Create key/value array from delivered article
            $contentAttributesKeyValues = $this->createContentAttributesKeyValues($article);

            // Create all the attributes for this content record (incl lifecycle)
            $this->externImportStockService->inserOrUpdatetContentAttributes($content, $contentAttributesKeyValues);

            $stockAttributesKeyValues = $this->createStockKeyValues($article);

            // process imp_extern_stock records with gtin and stock
            $this->externImportStockService->insertOrUpdateStockInclStockAttributes($stockAttributesKeyValues, $filename);

            // process images for this article
            $this->externImportStockService->insertOrUpdateImages($article->Images, $content);

            //die('stop na 1 artikel');
        }

        /**
         * Create a key/value array to feed importExternProcessor
         *
         * @param object $article
         * @return array
         */
        private function createContentAttributesKeyValues(object $article): array
        {
            return [
                'ArtikelNummer' => $article->ArtikelNummer,
                'Merk' => $article->Merk,
                'HoofdArtikelgroep' => $article->HoofdArtikelgroep,
                'SubArtikelgroep' => $article->SubArtikelgroep,
                'Doelgroep' => $article->Doelgroep,
                'Seizoen' => $article->Seizoen,
                'ArtikelOmschrijving' => $article->ArtikelOmschrijving,
                'Samenstelling' => $article->Samenstelling,
                'Pasvorm' => $article->Pasvorm,
                'Kleurnummer' => $article->Kleurnummer,
                'Kleur' => $article->Kleur,
                'Inkoopprijs' => $article->Inkoopprijs,
                'Verkoopprijs' => $article->Verkoopprijs,
                'Actieprijs' => $article->Actieprijs
            ];
        }

        /**
         * Create a key/value array to feed importExternProcessor
         *
         * @param object $article
         * @return array
         */
        private function createStockKeyValues(object $article): array
        {
            $stockKeyValues = [];
            foreach ($article->Stock as $articleData) {
                $stockKeyValues[] = [
                    'gtin' => $articleData->Barcode,
                    'ArtikelNummer' => $article->ArtikelNummer,
                    'stock' => $articleData->Voorraad,
                    'size' => $articleData->Maat1
                ];
            }

            return $stockKeyValues;
        }
    }
