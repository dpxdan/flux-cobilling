ALTER TABLE `accounts`
  ADD COLUMN `tax_city_number` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL AFTER `id_external`;

UPDATE `system` SET `value` = 'https://api.cnpja.com' WHERE `name` = 'doc_cnpja_base_url' AND `value` = 'https://open.cnpja.com';
