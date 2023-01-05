
<?php

    /**
     * Process the imp_extern tabel into imp_Stock
     * Alle externe leveranceirs plaatsen hun stock (en eventueel aanvullende data) in de imp_Extern tabellen)
     * Deze stap verwerkt de stock/voorraad in de sans imp_Stock tabel
     */
    class ProcessExternStockDonders
    {
        private int $externalReference = 0;
        private ImportExternValidator $importExternValidator;
        private ImportExternLogger $importExternalLogger;

        private int $productId = 0;
        private int $maatid = 0;
        private string $maatnaam = '';
        private int $voorraad = 0;
        private float $prijsvan = 0;
        private float $prijsvoor = 0;
        private float $prijsvoorgeheim = 0;
        private float $prijsinkoop = 0;
        private int $loc = 8888;// locatie van donders (uniek nummer)
        private int $gtin = 0;
        private int $gtinExt = 0;
        private int $timestamp = 0;
        private string $datum = '';
        // De volgende heb ik voor Donders niets aan, maar zet ik als default klaar
        private mixed $posmaat = 0; // Geen idee wat hier in te vullen
        private int $alleenonline = 0;
        private int $kortingspercentage= 0;
        private int $kuitwijdte = 0;
        private int $kuitwijdtecm = 0;
        private int $exclusief = 0;
        private int $promotie = 0;
        private int $wiseprice = 0;
        private string $uuid;

        public function __construct(int $externalReference)
        {
            $this->externalReference = $externalReference;
            $this->importExternValidator = new ImportExternValidator($this->externalReference);
            $this->importExternalLogger = new ImportExternLogger();
        }

        public function processStock(): void
        {
            $this->uuid = $this->getUUID();

            $this->importExternalLogger->logStartProcessStock($this->uuid);

            // Ga elke stock record verwerken voor de externe partij
            $stockRecords = $this->getStockRecords();
            //var_dump($stockRecords);

            foreach ($stockRecords as $stockRecord) {
                $this->prepareDataForSqlInsert($stockRecord);

                $this->insertOrUpdateRecordInImpStockTable($stockRecord, $this->externalReference);

                // For debugging inserted data
                //$this->printClassVars();
            }

            // ToDo log end import stock process donders
            $this->importExternalLogger->endProcessStockLog($this->uuid);
        }

        /**
         * @param array $attributes
         *
         * @return void
         */
        private function setPrijsVan(array $attributes): void
        {
            $prijs = 0;
            foreach ($attributes as $attribute) {
                if ($attribute['attribute_name'] === 'Verkoopprijs') {
                    $prijs = $attribute['value'];
                    break;
                }
            }
            $this->prijsvan = $prijs;
        }

        /**
         * @param array $attributes
         *
         * @return void
         */
        private function setPrijsVoor(array $attributes): void
        {
            $prijs = 0;
            foreach ($attributes as $attribute) {
                if ($attribute['attribute_name'] === 'Actieprijs') {
                    $prijs = $attribute['value'];
                    break;
                }
            }
            $this->prijsvoor = $prijs;
        }

        /**
         * @param array $attributes
         *
         * @return void
         */
        private function setPrijsVoorGeheim(array $attributes): void
        {
            $prijs = 0;
            foreach ($attributes as $attribute) {
                if ($attribute['attribute_name'] === 'PrijsGeheim') { // bestaat niet dus wordt 0
                    $prijs = $attribute['value'];
                    break;
                }
            }
            $this->prijsvoorgeheim = $prijs;
        }

        /**
         * @param array $attributes
         *
         * @return void
         */
        private function setPrijsInkoop(array $attributes): void
        {
            $prijs = 0;
            foreach ($attributes as $attribute) {
                if ($attribute['attribute_name'] === 'Inkoopprijs') {
                    $prijs = $attribute['value'];
                    break;
                }
            }
            $this->prijsinkoop = $prijs;
        }

        /**
         * @param array $stockRecord
         *
         * @return void
         */
        private function setStock(array $stockRecord): void
        {
            $this->voorraad = $stockRecord['stock'];
        }

        /**
         * @param array $attributes
         * @param array $stockRecord
         *
         * @return void
         */
        private function setMaatIdAndMaatnaam(array $attributes, array $stockRecord): void
        {
            $maatTxt = '';
            foreach ($attributes as $attribute) {
                if ($attribute['attribute_name'] === 'size') {
                    $maatTxt = $attribute['value'];
                    break;
                }
            }

            $sqlMaat = new Query("SELECT id FROM art_Maten WHERE maattxt = " . $maatTxt . ' LIMIT 1');
            $sqlMaat->execute();
            $maatRecord = $sqlMaat->fetch();

            if (empty($maatRecord)) {
                $this->importExternValidator->validateMaatId($maatRecord, $stockRecord, $this->externalReference);
            }

            $this->maatid = $maatRecord['id'];
            $this->maatnaam = $maatTxt;
        }

        /**
         * @param int $gtin
         * @param array $stockRecord
         *
         * @return int
         */
        private function getProductId(int $gtin, array $stockRecord): int
        {
            $sqlImpArtikelen = new Query("SELECT VART FROM imp_Artikelen WHERE GTIN = " . $gtin);
            $sqlImpArtikelen->execute();
            $impArtikelenRecord = $sqlImpArtikelen->fetch();

            $this->importExternValidator->validateProductId($impArtikelenRecord, $gtin, $stockRecord, $this->externalReference);

            return $impArtikelenRecord['VART'] ?? 0;
        }

        /**
         * @param int $product_id
         *
         * @return array
         */
        private function getContentRecord(int $product_id): array
        {
            $sqlContent = new Query("SELECT * FROM imp_extern_content WHERE product_id = " . $product_id);
            $sqlContent->execute();
            $contentRecord = $sqlContent->fetch();

            return $contentRecord;
        }

        /**
         * @param int $stockRecordId
         * @param int $contentRecordId
         *
         * @return array
         */
        private function getAttributes(int $stockRecordId, int $contentRecordId): array
        {
            $sqlAttributes = new Query(
                "SELECT * FROM imp_extern_attributes WHERE stock_id = " . $stockRecordId . " OR content_id = " . $contentRecordId
            );
            $sqlAttributes->execute();
            $attributes = $sqlAttributes->fetchAll();

            return $attributes;
        }

        /**
         * @return array
         */
        private function getStockRecords(): array
        {
            $sqlStock = new Query(
                "SELECT * FROM imp_extern_stock WHERE external_reference = " . $this->externalReference . " limit 1"
            );
            $sqlStock->execute();
            $stockRecords = $sqlStock->fetchAll();
            return $stockRecords;
        }

        /**
         * For debugging to see what has bin set for each class var before it is saved to the database
         *
         * @return void
         */
        private function printClassVars(): void
        {
            var_dump($this->productId);
            var_dump($this->maatid);
            var_dump($this->maatnaam);
            var_dump($this->voorraad);
            var_dump($this->prijsvan);
            var_dump($this->prijsvoor);
            var_dump($this->prijsinkoop);
            var_dump($this->prijsvoorgeheim);
            var_dump($this->gtin);
            var_dump($this->gtinExt);
            var_dump($this->timestamp);
            var_dump($this->datum);
            var_dump($this->loc);
        }

        /**
         * Sets all the class vars used to insert a record into imp_Stock
         *
         * @param mixed $stockRecord
         *
         * @return void
         */
        private function prepareDataForSqlInsert(mixed $stockRecord): void
        {
            $contentRecord = $this->getContentRecord($stockRecord['product_id']);

            // haal attributes op voor de stock_id en content_id
            $attributes = $this->getAttributes($stockRecord['id'], $contentRecord['id']);
            //var_dump($attributes);

            $this->productId = $this->getProductId($stockRecord['gtin'], $stockRecord);
            $this->setMaatIdAndMaatnaam($attributes, $stockRecord);
            $this->setStock($stockRecord);

            $this->setPrijsVan($attributes);
            $this->setPrijsVoor($attributes);
            $this->setPrijsVoorGeheim($attributes);
            $this->setPrijsInkoop($attributes);

            $this->gtin = $stockRecord['gtin'];
            $this->gtinExt = $stockRecord['gtin'];

            $datetime = new DateTime('now');
            $this->timestamp = $datetime->getTimestamp();
            $this->datum = $datetime->format('Y-m-d');

            // Set locatie Donders
            $this->loc = 8888;
        }

        /**
         * Inserts or updates the imported stock data in imp_Stock
         *
         * @param array $stockRecord
         * @param int $externalReference
         *
         * @return void
         */
        private function insertOrUpdateRecordInImpStockTable(array $stockRecord, int $externalReference): void
        {
            $impStockRecord = $this->isExistingRecord();

            try {
                if ($impStockRecord) {
                    $this->updateImpStockRecord();
                } else {
                    $this->insertImpStockRecord();
                }
            } catch (Exception $e) {
                $this->importExternValidator->validateImpStockQuery($e, $stockRecord, $externalReference);
            }
        }

        private function updateImpStockRecord(): void
        {
            $sql = new Query("UPDATE imp_Stock set maatid = '" . $this->maatid . "', maatnaam = '" . $this->maatnaam . "', voorraad = '" . $this->voorraad . "',
                     prijsvan = '" . $this->prijsvan . "', prijsvoor = '" . $this->prijsvoor . "', prijsvoorgeheim = '" . $this->prijsvoorgeheim . "',
                     prijsinkoop = '" . $this->prijsinkoop . "', posmaat = '" . $this->posmaat . "', loc = '" . $this->loc . "', alleenonline = '" . $this->alleenonline . "',
                     kortingspercentage = '" . $this->kortingspercentage . "', kuitwijdte = '" . $this->kuitwijdte . "', `timestamp` = '" . $this->timestamp . "',
                     datum = '" . $this->datum . "', kuitwijdtecm = '" . $this->kuitwijdtecm . "', gtinExt = '" . $this->gtinExt . "', exclusief = '" . $this->exclusief . "',
                     promotie = '" . $this->promotie . "', wiseprice = '" . $this->wiseprice . "' WHERE gtin = '" . $this->gtin . "' AND productid = '" . $this->productId ."'" );
            $sql->execute();
        }

        private function insertImpStockRecord(): void
        {
            $sql = new Query(
                "
            INSERT INTO imp_Stock 
                (productid, maatid, maatnaam, voorraad, prijsvan, prijsvoor, prijsvoorgeheim, prijsinkoop, posmaat,
                loc, alleenonline, kortingspercentage, kuitwijdte, gtin, `timestamp`, datum, kuitwijdtecm, gtinExt, exclusief, promotie, wiseprice)
                VALUES 
                ('" . $this->productId . "', " . $this->maatid . ", '" . $this->maatnaam . "', " . $this->voorraad . ", " . $this->prijsvan . ", " . $this->prijsvoor . ", " . $this->prijsvoorgeheim . ",
                 " . $this->prijsinkoop . ", '" . $this->posmaat . "', " . $this->loc . ", " . $this->alleenonline . ", " . $this->kortingspercentage . ", " . $this->kuitwijdte . ",
                  '" . $this->gtin . "', '" . $this->timestamp . "', '" . $this->datum . "', " . $this->kuitwijdtecm . ", '" . $this->gtinExt . "', " . $this->exclusief . ",
                   " . $this->promotie . ", " . $this->wiseprice . ")
                 "
            );
            $sql->execute();
        }

        private function isExistingRecord(): bool|array
        {
            $sql = new Query("SELECT id FROM imp_Stock WHERE productid = ". $this->productId ." AND gtin = ". $this->gtin);
            $sql->execute();
            $impStockRecord = $sql->fetch();

            if (!empty($impStockRecord)) {
                return $impStockRecord;
            } else {
                return false;
            }
        }

        function getUUID() {
            return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

                // 16 bits for "time_mid"
                mt_rand( 0, 0xffff ),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand( 0, 0x0fff ) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand( 0, 0x3fff ) | 0x8000,

                // 48 bits for "node"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            );
        }
    }
