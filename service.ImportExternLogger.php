<?php

    /**
     * Logger for processing json files to imp_extern_...
     * Logger for processing imp_extern_... to imp_Stock
     */
    class ImportExternLogger
    {
        /**
         * @param string $filename
         * @return void
         */
        public function logStartProcess(string $filename): void
        {
            if ($this->isExistingLog($filename)) {
                $this->updateLog($filename);
            } else {
                $this->insertLog($filename);
            }
        }

        /**
         * @param string $latestJsonFile
         * @return void
         */
        public function logEndProcess(string $latestJsonFile): void
        {
            $dateArray = $this->getDateInfo();

            $sql = "UPDATE imp_extern_log set `message` = 'Ended processing json file.', `time` = '" . $dateArray['time'] . "',
                `date` = '" . $dateArray['date'] . "', status = 1 where imp_filename = '" . $latestJsonFile . "'";
            DatabaseService::Query($sql);
        }

        /**
         * @param string $filename
         * @return void
         */
        public function updateLog(string $filename): void
        {
            $dateArray = $this->getDateInfo();

            $logQuery = new Query("UPDATE imp_extern_log set `time` = :time, `date` = :date WHERE imp_filenane = :filename");
            $logQuery->bindArray(['time' => $dateArray['time'], 'date' => $dateArray['date'], 'filename' => $filename]);
            $logQuery->execute();
        }

        /**
         * @param string $filename
         * @return void
         */
        public function insertLog(string $filename): void
        {
            $dateArray = $this->getDateInfo();

            $logQuery = new Query("INSERT INTO imp_extern_log (`time`, `date`, imp_filename, `process`, `message`) VALUES (:time, :date, :filename, 'import', 'Started processing json file.')");
            $logQuery->bindArray(['time' => $dateArray['time'], 'date' => $dateArray['date'], 'filename' => $filename]);
            $logQuery->execute();
        }

        /**
         * @param string $filename
         * @return bool
         */
        private function isExistingLog(string $filename): bool
        {
            $logQuery = new Query("SELECT id FROM imp_extern_log  WHERE imp_filename = :filename");
            $logQuery->bind('filename', $filename);
            $logQuery->execute();


            if ($logQuery->count() > 0) {
                return true;
            }

            return false;
        }

        public function logStartProcessStock(string $uuid)
        {
            $this->insertProcessStockLog($uuid);
        }

        public function insertProcessStockLog(string $uuid)
        {
            $dateArray = $this->getDateInfo();

            $sql = new Query("INSERT INTO imp_extern_log (process, uuid, status, message, `time`, `date`) VALUES ('export', '" . $uuid . "', 0, 'Started processing stock.', '".$dateArray['time'] ."', '".$dateArray['date']."')");
            $sql->execute();
        }

        public function endProcessStockLog(string $uuid)
        {
            $dateArray = $this->getDateInfo();

            $logQuery = new Query("UPDATE imp_extern_log set `time` = :time, `date` = :date, status = 1, message = 'Ended processing stock.' WHERE uuid = :uuid");
            $logQuery->bindArray(['time' => $dateArray['time'], 'date' => $dateArray['date'], 'uuid' => $uuid]);
            $logQuery->execute();
        }

        private function getDateInfo(): array
        {
            $time = (new DateTime('now'))->format('h:m:i');
            $date = (new DateTime('now'))->format('Y-m-d h:m:i');
            return ['time' => $time, 'date' => $date]  ;
        }
    }
