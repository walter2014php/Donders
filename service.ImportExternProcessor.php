<?php

    class ImportExternProcessor
    {
        private string $productIdMapping;
        private int $externalReference;
        private array $ignoreAttributes = ['gtin', 'stock'];
        private ImportExternValidator $importExternValidator;

        public function __construct(string $productIdMapping, int $externalReference)
        {
            $this->productIdMapping = $productIdMapping;
            $this->ignoreAttributes[] = $productIdMapping;// Voorkomt dat de productIdMapping wordt verwerkt tot stock attribute
            $this->externalReference = $externalReference;
            $this->importExternValidator = new ImportExternValidator();
        }

        /**
         * @param object $article
         * @return array
         */
        public function getOrInsertContent(object $article): array
        {
            $content = $this->isExistingContent($article);
            if ($content === false) {
                $content = $this->insertContent($article);
            }

            return $content;
        }

        /**
         * @param object $article
         * @return array
         */
        private function insertContent(object $article): array
        {
            $sqlContent = new Query("INSERT INTO imp_extern_content (external_reference, product_id) values (:externalReferenceId, :productId)");
            $sqlContent->bindArray(['externalReferenceId' => $this->externalReference, 'productId' => $article->{$this->productIdMapping}]);
            $sqlContent->execute();

            $sqlInsertedContent = new Query("SELECT * FROM imp_extern_content WHERE external_reference = :externalReferenceId AND product_id = :productId");
            $sqlInsertedContent->bindArray(['externalReferenceId' => $this->externalReference, 'productId' => $article->{$this->productIdMapping}]);
            $sqlInsertedContent->execute();

            return $sqlInsertedContent->fetch();
        }

        /**
         * @param object $article
         * @return array|false
         */
        private function isExistingContent(object $article): bool|array
        {
            $sqlContent = new Query("SELECT * FROM imp_extern_content WHERE product_id = :productId AND external_reference = :externalReferenceId");
            $sqlContent->bindArray(['productId' => $article->{$this->productIdMapping}, 'externalReferenceId' => $this->externalReference]);
            $sqlContent->execute();
            $result = $sqlContent->fetch();

            if ($sqlContent->count() > 0) {
                return $result;
            }

            return false;
        }

        /**
         * Insert imp_extern_stock record and add key/values in the imp_extern_attributes table
         *
         * @param array $stockKeyValues
         * @param string $filename
         * @return void
         */
        public function insertOrUpdateStockInclStockAttributes(array $stockKeyValues, string $filename): void
        {
            foreach ($stockKeyValues as $stockData) {
                // gtin and stock are mandatory values
                if ($this->importExternValidator->missingGtinAndStock($stockData, $filename, $this->externalReference)) { continue; }

                $productId = $stockData[$this->productIdMapping];
                $stock = $this->isExistingStock($stockData);

                if ($stock === false) {
                    $stock = $this->insertStock($stockData);

                    $this->insertStockAttributes($stockData, $stock['id'], $productId);
                } else {
                    $stock = $this->updateStock($stockData);

                    $this->insertOrUpdateStockAttributes($stockData, $stock['id'], $productId);
                }
            }
        }

        /**
         * @param array $stockData
         * @return array|false
         */
        private function isExistingStock(array $stockData): bool|array
        {
            $sql = new Query("SELECT * FROM imp_extern_stock WHERE external_reference = :externalReference AND gtin = :gtin");
            $sql->bindArray(['externalReference' => $this->externalReference, 'gtin' => $stockData['gtin']]);
            $sql->execute();
            $result = $sql->fetch();
            //var_dump($result);

            if (!empty($result)) {
                return $result;
            } else {
                return false;
            }
        }

        /**
         * @param array $stockData
         * @return array
         */
        private function insertStock(array $stockData): array
        {
            $sql = new Query("INSERT INTO imp_extern_stock (gtin, stock, external_reference, product_id) VALUES (:gtin, :stock, :externalReference, :productId)");
            $sql->bindArray(['gtin' => $stockData['gtin'], 'stock' => $stockData['stock'], 'externalReference' => $this->externalReference, 'productId' => $stockData[$this->productIdMapping]]);
            $sql->execute();
            //echo $sql->getQueryString(). '<br>';

            $sqlNew = new Query("SELECT * FROM imp_extern_stock WHERE gtin = :gtin AND external_reference = :externalReference and product_id = :productId");
            $sqlNew->bindArray(['gtin' => $stockData['gtin'] , 'externalReference' => $this->externalReference, 'productId' => $stockData[$this->productIdMapping]]);
            $sqlNew->execute();
            return $sqlNew->fetch();
        }

        /**
         * @param array $stockData
         * @param int $stockId
         * @param mixed $productId
         * @return void
         */
        private function insertStockAttributes(array $stockData, int $stockId, mixed $productId): void
        {
            foreach ($stockData as $attributeName => $value) {
                // Do not process GTIN or stock or AtikelNummeras attribute values
                if (in_array($attributeName, $this->ignoreAttributes)) continue;

                $sql = new Query("INSERT INTO imp_extern_attributes (stock_id, attribute_name, `value`, product_id) VALUES (:stockId, :attributeName, :value, :productId)");
                $sql->bindArray(['stockId' => $stockId, 'attributeName' => $attributeName, 'value' => $value, 'productId' => $productId]);
                $sql->execute();
                //echo $sql->getQueryString() . '<br>';
            }
        }

        /**
         * @param array $stockData
         * @return array
         */
        private function updateStock(array $stockData): array
        {
            $sql = new Query("UPDATE imp_extern_stock set stock = :stock WHERE gtin =:gtin and external_reference = :externalReference AND product_id = :productId");
            $sql->bindArray(['gtin' => $stockData['gtin'], 'stock' => $stockData['stock'], 'externalReference' => $this->externalReference, 'productId' => $stockData[$this->productIdMapping]]);
            $sql->execute();

            $sqlUpdate = new Query("SELECT * FROM imp_extern_stock WHERE gtin = :gtin AND external_reference = :externalReference AND product_id = :productId");
            $sqlUpdate->bindArray(['gtin' => $stockData['gtin'] , 'externalReference' => $this->externalReference, 'productId' => $stockData[$this->productIdMapping]]);
            $sqlUpdate->execute();

            return $sqlUpdate->fetch();
        }

        /**
         * @param array $stockData
         * @param int $stockId
         * @param mixed $productId
         * @return void
         */
        private function insertOrUpdateStockAttributes(array $stockData, int $stockId,mixed $productId): void
        {
            $processedAttributeIds = [];

            $existingAttributeIds = $this->getExistingAttributeIdsForThisStock($stockId);

            foreach ($stockData as $attributeName => $value) {
                // Do not process GTIN or stock or ArtikelNummer as attribute values
                if (in_array($attributeName, $this->ignoreAttributes)) continue;

                $attribute = $this->isExistingStockAttribute($attributeName, $stockId);

                // insert or update attribute key/value
                if ($attribute) {
                    $this->updateStockAttribute($attributeName, $value, $stockId, $productId);// toDo werkt niet
                    $processedAttributeIds[] = $attribute['id'];
                } else {

                    $this->insertStockAttributes([$attributeName => $value], $stockId, $productId);// ToDo werkt niet
                }
            }

            // Now clean up the d key/values that arnt delivered in the import ...
            foreach ($existingAttributeIds as $oldAttributeId) {
                if (!in_array($oldAttributeId, $processedAttributeIds)) {
                    $this->deleteAttribute($oldAttributeId);
                }
            }
        }

        /**
         * Returns a list with id's of the attributes for this stock record
         *
         * @param int $stockId
         * @return array
         */
        private function getExistingAttributeIdsForThisStock(int $stockId): array
        {
            $sqlcontentIds = new Query("SELECT id FROM imp_extern_attributes WHERE stock_id = :stockId");
            $sqlcontentIds->bindArray(['stockId' => $stockId]);
            $sqlcontentIds->execute();
            $result = $sqlcontentIds->fetchAll();

            $list = [];
            foreach($result as $row) {
                $list[] = $row['id'];
            }

            return $list;
        }

        /**
         * @param string $attributeName
         * @param int $stockId
         * @return array|false
         */
        private function isExistingStockAttribute(string $attributeName, int $stockId): bool|array
        {
            $sql = new Query("SELECT * FROM imp_extern_attributes WHERE stock_id = :stockId AND attribute_name = :attributeName");
            $sql->bindArray(['stockId' => $stockId, 'attributeName' => $attributeName]);
            $sql->execute();
            $result = $sql->fetch();

            if (!empty($result)) {
                return $result;
            } else {
                return false;
            }
        }

        /**
         * @param string $attributeName
         * @param mixed $value
         * @param int $stockId
         * @param mixed $productId
         * @return void
         */
        private function updateStockAttribute(string $attributeName, mixed $value, int $stockId, mixed $productId): void
        {
            $sql = new Query("UPDATE imp_extern_attributes set `value` = :value WHERE stock_id = :stockId AND attribute_name = :attributeName AND product_id = :productId");
            $sql->bindArray(['value' => $value, 'stockId' => $stockId, 'attributeName' => $attributeName, 'productId' => $productId]);
            $sql->execute();
            //echo $sql->getQueryString(). '<br>';;
        }

        /**
         * @param int $id
         * @return void
         */
        public function deleteAttribute(int $id): void
        {
            $delete = new Query("DELETE FROM imp_extern_attributes WHERE id = " . $id);
            $delete->execute();
        }

        /**
         * We slaan alle key/values die zijn aangeleverd op als attributen
         * Vooraf halen we alle attributen op die door een voorgaande import in de database staan voor het te verwerken product
         * Alles wat wordt aangeleverd wordt ge-update
         * Alles wat niet wordt aangeleverd wordt verwijderd
         * 1 om id's in de database te besparen in de database
         * 2 en soort life-cycle: wat niet wordt aangeleverd wordt verwijderd
         *
         * @param array $content
         * @param array $attributes
         */
        public function inserOrUpdatetContentAttributes(array $content, array $attributes): void
        {
            // Processed attributes id's
            $processedAttributeIds = [];

            $existingAttributeIds = $this->getExistingAttributeIdsForThisContent($content['id'], $attributes);

            // Process new delivered attribute key/value's
            foreach ($attributes as $attributeName => $value) {
                $attribute = $this->isExistingContentAttribute($attributeName, $content['id']);

                // insert or update attribute key/value
                if ($attribute) {
                    $this->updateContentAttribute($attributeName, $value, $content['id'], $content['product_id']);
                    $processedAttributeIds[] = $attribute['id'];
                } else {
                    $this->insertContentAttribute($attributeName, $value, $content['id'], $content['product_id']);
                }
            }

            // Now clean up the d key/values that arnt delivered in the import ...
            foreach ($existingAttributeIds as $oldAttributeId) {
                if (!in_array($oldAttributeId, $processedAttributeIds)) {
                    $this->deleteAttribute($oldAttributeId);
                }
            }
        }

        /**
         * @param int $contentId
         * @param $attributes
         * @return array
         */
        private function getExistingAttributeIdsForThisContent($contentId)
        {
            $sqlcontentIds = new Query("SELECT id FROM imp_extern_attributes WHERE content_id = :contentId");
            $sqlcontentIds->bindArray(['contentId' => $contentId]);
            $sqlcontentIds->execute();
            $result = $sqlcontentIds->fetchAll();

            $list = [];
            foreach($result as $row) {
                $list[] = $row['id'];
            }

            return $list;
        }

        /**
         * @param string $attributeName
         * @param int $contentId
         * @return array|false
         */
        private function isExistingContentAttribute(string $attributeName, int $contentId): bool|array
        {
            $sql = new Query("SELECT * FROM imp_extern_attributes WHERE content_id = :contentId AND attribute_name = :attributeName");
            $sql->bindArray(['contentId' => $contentId, 'attributeName' => $attributeName]);
            $sql->execute();
            $result = $sql->fetch();

            if (!empty($result)) {
                return $result;
            } else {
                return false;
            }
        }

        /**
         * @param string $attributeName
         * @param mixed $value
         * @param int $contentId
         * @param mixed $productId
         * @return void
         */
        private function updateContentAttribute(string $attributeName, mixed $value, int $contentId, mixed $productId): void
        {
            $sqlAttribute = new Query("UPDATE imp_extern_attributes set `value` = :value WHERE content_id = :contentId AND attribute_name = :attributeName AND product_id = :productId");
            $sqlAttribute->bindArray(['value' => $value, 'contentId' => $contentId, 'attributeName' => $attributeName, 'productId' => $productId]);
            $sqlAttribute->execute();
        }

        /**
         * @param string $attributeName
         * @param mixed $value
         * @param int $contentId
         * @param mixed $productId
         * @return void
         */
        private function insertContentAttribute(string $attributeName, mixed $value, int $contentId, mixed $productId): void
        {
            $sqlAttribute = new Query("INSERT INTO imp_extern_attributes (`value`, content_id, attribute_name, product_id) VALUES (:value, :contentId, :attributeName, :productId)");
            $sqlAttribute->bindArray(['value' => $value, 'contentId' => $contentId, 'attributeName' => $attributeName, 'productId' => $productId]);
            $sqlAttribute->execute();
        }

        /**
         * @param array $images
         * @param array $content
         *
         * @return void
         */
        public function insertOrUpdateImages(array $images,array $content): void
        {
            $processedImageIds = [];
            $existingImagesIds = $this->getExistingImageIdsForThisContent($content['id']);

            foreach ($images as $image) {
                $existingImage = $this->isExistingImage($image, $content['id']);

                if ($existingImage) {
                    // No need to update if existing image, but do save the id so we know what is processed
                    $processedImageIds[] = $existingImage['id'];
                } else {
                    $image = $this->insertImage($image, $content);
                    $processedImageIds[] = $image['id'];
                }
            }

            // Now clean up the key/values that arnt delivered in the import ...
            foreach ($existingImagesIds as $oldImageId) {
                if (!in_array($oldImageId, $processedImageIds)) {
                    $this->deleteImage($oldImageId);
                }
            }
        }

        /**
         * @param int $imageId
         *
         * @return void
         */
        private function deleteImage(int $imageId): void
        {
            $delete = new Query("DELETE FROM imp_extern_images WHERE id = " . $imageId);
            $delete->execute();
        }

        private function getExistingImageIdsForThisContent(int $contentId): array
        {
            $sql = new Query("SELECT id FROM imp_extern_images WHERE content_id = :contentId");
            $sql->bindArray(['contentId' => $contentId]);
            $sql->execute();
            $result = $sql->fetchAll();

            $list = [];
            foreach($result as $row) {
                $list[] = $row['id'];
            }

            return $list;
        }

        /**
         * @param object $image
         * @param array $content
         *
         * @return array
         */
        private function insertImage(object $image, array $content): array
        {
            $sqlImage = new Query("INSERT INTO imp_extern_images (content_id, product_id, url) VALUES (:contentId, :productId, :imageName)");
            $sqlImage->bindArray(['contentId' => $content['id'], 'imageName' => $image->Image, 'productId' => $content['product_id']]);
            $sqlImage->execute();

            $sql = new Query("SELECT * FROM imp_extern_images WHERE content_id = :contentId AND url = :imageName");
            $sql->bindArray(['contentId' => $content['id'], 'imageName' => $image->Image]);
            $sql->execute();

            return $sql->fetch();
        }

        /**
         * @param object $image
         * @param int $contentId
         *
         * @return array|false
         */
        private function isExistingImage(object $image, int $contentId): bool|array
        {
            $sql = new Query("SELECT id FROM imp_extern_images WHERE url = :imageName AND content_id = :contentId");
            $sql->bindArray(['imageName' => $image->Image, 'contentId' => $contentId]);
            $sql->execute();
            $result = $sql->fetch();

            if (!empty($result)) {
                return $result;
            } else {
                return false;
            }
        }
    }
