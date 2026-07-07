-- Migracao incremental (Parte 2): adiciona a uma tabela nfcom_cobilling ja
-- existente (criada na Parte 1) as colunas da tela de upload vinculado a conta.
-- Seguro re-executar apenas em bases sem essas colunas.

ALTER TABLE `nfcom_cobilling`
  ADD COLUMN `accountid` int NOT NULL DEFAULT '0' COMMENT 'Accounts table id' AFTER `reseller_id`,
  ADD COLUMN `xml_file` varchar(255) DEFAULT NULL AFTER `xml_recebido`,
  ADD COLUMN `origem` varchar(20) DEFAULT 'api' AFTER `danfe_com`,
  ADD KEY `account` (`accountid`);
