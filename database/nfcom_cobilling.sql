-- Tabela de auditoria e reprocessamento da integracao Co-Billing NFCom (PX-108).
-- Padrao moderno FluxSBC (referencia: api_logs): InnoDB / utf8mb4 / ROW_FORMAT=DYNAMIC.
-- accountid/xml_file/origem sustentam a tela de upload (vinculo com accounts).

CREATE TABLE `nfcom_cobilling` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reseller_id` int DEFAULT NULL,
  `accountid` int NOT NULL DEFAULT '0' COMMENT 'Accounts table id',
  `chave_cofaturamento` varchar(50) DEFAULT NULL,   -- chNFCom do parceiro (referencia)
  `xml_recebido` mediumtext,                         -- corpo de entrada (XML/JSON)
  `xml_file` varchar(255) DEFAULT NULL,              -- nome do arquivo fisico (upload pela tela)
  `payload_enviado` mediumtext,                      -- JSON montado p/ Emissor62
  `response` mediumtext,                             -- resposta bruta da API
  `http_code` smallint DEFAULT NULL,
  `sucesso` tinyint(1) DEFAULT NULL,                 -- data.sucesso
  `guid` varchar(50) DEFAULT NULL,                   -- data.guid
  `chave_nfcom` varchar(50) DEFAULT NULL,            -- data.chave (nota emitida)
  `numero` int DEFAULT NULL,                         -- data.numero
  `situacao` varchar(30) DEFAULT NULL,               -- data.descricao
  `motivo` varchar(255) DEFAULT NULL,                -- data.mensagem (ex.: "100 - Autorizado...")
  `danfe_com` varchar(255) DEFAULT NULL,             -- data.danfeCom
  `origem` varchar(20) DEFAULT 'api',                -- 'api' | 'upload'
  `status` tinyint NOT NULL DEFAULT '2',             -- 0=autorizada, 1=erro, 2=pendente/reprocessar
  `tentativas` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `account` (`accountid`),
  KEY `chave_cofaturamento` (`chave_cofaturamento`),
  KEY `chave_nfcom` (`chave_nfcom`),
  KEY `status` (`status`),
  KEY `http_code` (`http_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
