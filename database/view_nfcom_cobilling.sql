-- View de suporte a listagem da integracao Co-Billing NFCom (PX-108).
-- Faz LEFT JOIN de nfcom_cobilling com accounts para expor number, customer e
-- dados basicos do cliente, alem de derivar status_label (0=Autorizada,
-- 1=Erro, 2=Pendente). Consumida por Cobilling::cobilling_list_json.
--
-- SQL SECURITY INVOKER: respeita as permissoes do usuario runtime do FluxSBC
-- (em producao a app usa um usuario menos privilegiado que root).
-- Seguro re-executar (CREATE OR REPLACE).

CREATE OR REPLACE
    SQL SECURITY INVOKER
    VIEW `view_nfcom_cobilling` AS
SELECT
    `n`.`id`                  AS `id`,
    `n`.`reseller_id`         AS `reseller_id`,
    `a`.`number`              AS `number`,
    CONCAT(`a`.`first_name`, ' ', `a`.`last_name`) AS `customer`,
    `a`.`address_1`           AS `address_1`,
    `a`.`postal_code`         AS `postal_code`,
    `a`.`province`            AS `province`,
    `a`.`city`                AS `city`,
    `a`.`email`               AS `email`,
    `a`.`tax_number`          AS `tax_number`,
    `n`.`accountid`           AS `accountid`,
    `n`.`chave_cofaturamento` AS `chave_cofaturamento`,
    `n`.`chave_nfcom`         AS `chave_nfcom`,
    `n`.`numero`              AS `numero`,
    (CASE `n`.`status`
        WHEN 0 THEN 'Autorizada'
        WHEN 1 THEN 'Erro'
        WHEN 2 THEN 'Pendente'
        ELSE 'Desconhecido'
    END)                      AS `status_label`,
    `n`.`status`              AS `status`,
    `n`.`sucesso`             AS `sucesso`,
    `n`.`http_code`           AS `http_code`,
    `n`.`situacao`            AS `situacao`,
    `n`.`motivo`              AS `motivo`,
    `n`.`guid`                AS `guid`,
    `n`.`danfe_com`           AS `danfe_com`,
    `n`.`origem`              AS `origem`,
    `n`.`tentativas`          AS `tentativas`,
    `n`.`xml_file`            AS `xml_file`,
    `n`.`payload_enviado`     AS `payload_enviado`,
    `n`.`response`            AS `response`,
    `n`.`xml_recebido`        AS `xml_recebido`,
    `n`.`created_at`          AS `created_at`,
    `n`.`updated_at`          AS `updated_at`
FROM `nfcom_cobilling` `n`
LEFT JOIN `accounts` `a` ON `a`.`id` = `n`.`accountid`;
