<?php

    class ImportExternValidator
    {
        private int $maxFileAgeInDays = 3; // Hoe oud de laatst aangeleverde file mag zijn
        private string $externReferenceName;

        public function __construct(string $externReferenceName='')
        {
            $this->externReferenceName = $externReferenceName;
        }

        /**
         * @param string $filepath
         * @return void
         */
        public function validateFileAge(string $filepath): void
        {
            $datetimeNow = new DateTime('now');

            $timestampFile = filemtime($filepath);
            $datetimeFile = new DateTime();
            $datetimeFile->setTimestamp($timestampFile);

            $diffDateTimeNowAndFile = date_diff($datetimeNow, $datetimeFile);

            if ($diffDateTimeNowAndFile->d > $this->maxFileAgeInDays) {
                EventLogger::log(
                    "Age of import file exceeds the maximum configured file age.",
                    sprintf("The age of import file `%s` is older then the maximum configured age (%s) for external reference `%s`. This is a problem.",
                        $filepath,
                        $this->maxFileAgeInDays,
                        $this->externReferenceName
                    ),
                    new EventType("imp_extern_max_file_age"),
                    EventSeverity::WARNING,
                    'File age problem.'
                );
            }
        }

        /**
         * @param string $filepath
         * @return void
         */
        public function validateEmpyFile(string $filepath): void
        {
            $filesize = filesize($filepath);
            if ($filesize < 1) {
                EventLogger::log(
                    "Import file is empty.",
                    sprintf("The file `%s` is empty for external reference `%s`.", $filepath, $this->externReferenceName),
                    new EventType("imp_extern_empty_file"),
                    EventSeverity::WARNING,
                    'Cannot process empty file.'
                );
            }
        }

        /**
         * @param string $source
         * @return void
         */
        public function noImportFilesFound(string $source): void
        {
            EventLogger::log(
                "No import files found.",
                sprintf("No import files found to process for external reference `%s`.", $this->externReferenceName),
                new EventType("imp_extern_no_files"),
                EventSeverity::WARNING,
                'No import files found to precess.'
            );
        }

        /**
         * Return true if one fo the mandatory files is missing so we do not process these values
         *
         * @param array $stockData
         * @param string $filename
         * @param int $externalReferenceId
         * @return bool
         */
        public function missingGtinAndStock(array $stockData, string $filename, int $externalReferenceId): bool
        {
            if (!array_key_exists('gtin', $stockData)) {
                $this->logMissingData('gtin', $filename, $externalReferenceId);
                return true;
            }
            if (!array_key_exists('stock', $stockData)) {
                $this->logMissingData('stock', $filename, $externalReferenceId);
                return true;
            }
            return false;
        }

        /**
         * @param string $value
         * @param string $filename
         * @param int $externalReferenceId
         * @return void
         */
        public function logMissingData(string $value, string $filename, int $externalReferenceId): void
        {
            $referenceName = $this->getReferenceNAme($externalReferenceId);

            EventLogger::log(
                "Mandatory data missing in import file.",
                sprintf("Mandatory data `%s` missing in file `%s` for external reference `%s`.",
                    $value,
                    $filename,
                    $referenceName
                ),
                new EventType("imp_extern_missing_data"),
                EventSeverity::WARNING,
                'Mandatory data missing in import.'
            );
        }

        public function validateMaatId($maatRecord, $stockRecord, int $externalReferenceId)
        {
            $referenceName = $this->getReferenceNAme($externalReferenceId);

            if (empty($maatRecord)) {
                EventLogger::log(
                    "Unable to determine maattxt for article.",
                    sprintf("Unable to determine maattxt for product id `%s` for external reference `%s`.",
                        $stockRecord['product_id'],
                        $referenceName
                    ),
                    new EventType("imp_extern_missing_data"),
                    EventSeverity::WARNING,
                    'Mandatory data not found in import.'
                );
            }

            if (count($maatRecord) > 1) {
                EventLogger::log(
                    "Unable to determine maattxt for article.",
                    sprintf("Multiple maattxt found for product `%s` for external reference `%s`.",
                        $stockRecord['product_id'],
                        $referenceName
                    ),
                    new EventType("imp_extern_missing_data"),
                    EventSeverity::WARNING,
                    'Mandatory data not found in import.'
                );
            }
        }

        public function validateProductId($impArtikelenRecord, $gtin, $stockRecord, $externalReferenceId)
        {
            $referenceName = $this->getReferenceNAme($externalReferenceId);

            if (empty($impArtikelenRecord)) {
                EventLogger::log(
                    "Unable to find productId for article.",
                    sprintf("Unable to find productId for product `%s` using gtin `%d`for external reference `%s`.",
                        $stockRecord['product_id'],
                        $gtin,
                        $referenceName
                    ),
                    new EventType("imp_extern_missing_data"),
                    EventSeverity::WARNING,
                    'Mandatory data not found in import.'
                );        }

            if (count($impArtikelenRecord) > 1) {
                EventLogger::log(
                    "found multiple productIds for article.",
                    sprintf("Found more then one productId for product `%s` using gtin `%d`for external reference `%s`.",
                        $stockRecord['product_id'],
                        $gtin,
                        $referenceName
                    ),
                    new EventType("imp_extern_missing_data"),
                    EventSeverity::WARNING,
                    'found multiple ids for product in import.'
                );
            }
        }

        public function validateImpStockQuery(Exception $e, array $stockRecord, int $externalReferenceId)
        {
            $referenceName = $this->getReferenceNAme($externalReferenceId);

            EventLogger::log(
                "Error during insert into imp_Stock during import.",
                sprintf("An error occurred during insert or update imp_Stock record for product `%s` for external reference `%s`. Error: " . $e->getMessage(),
                  $stockRecord['product_id'],
                   $referenceName
                ),
                new EventType("imp_extern_sql_error"),
                EventSeverity::CRITICAL,
                'Query in imp_Stock failed during import.'
            );
        }

        private function getReferenceNAme($externalReferenceId)
        {
            $sql = new Query("SELECT name FROM imp_extern_reference WHERE id = :externalReferenceId");
            $sql->bindArray(['externalReferenceId' => $externalReferenceId]);
            $sql->execute();
            $result = $sql->fetch();

            return $result['name'];
        }
    }
