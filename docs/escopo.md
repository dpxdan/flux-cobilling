# Integração de Co-Billing com FluxSBC para emissão de NFCom

## Objetivo

Implementar um módulo de integração no FluxSBC para permitir a emissão de NFCom (Modelo 62) a partir de informações fornecidas por sistemas externos de Co-Billing.

O sistema do cliente será responsável por gerar e fornecer todos os dados da nota fiscal, bem como a chave da NFCom de referência utilizada no processo de cofaturamento. O FluxSBC será responsável por validar os dados recebidos, montar o payload esperado pela API do Emissor62, realizar a comunicação com a API e registrar todo o processo para fins de auditoria e reprocessamento.

## Escopo

* Disponibilizar um endpoint para integração com sistemas de Co-Billing.
* Receber os dados completos da NFCom em formato estruturado (XML ou JSON, conforme definido na integração).
* Receber a chave da NFCom utilizada como referência de cofaturamento.
* Validar os campos obrigatórios da requisição.
* Converter os dados recebidos para o layout JSON exigido pela API do Emissor62.
* Enviar a requisição para emissão da NFCom.
* Retornar ao sistema cliente o resultado da operação.
* Registrar logs completos da integração.

## Responsabilidades

### Sistema do Cliente (Co-Billing)

Responsável por fornecer:

* Dados completos da nota fiscal.
* Dados do destinatário.
* Dados do assinante.
* Produtos e serviços faturados.
* Valores e tributos.
* Informações complementares.
* Autorizações.
* Chave da NFCom utilizada para o cofaturamento.
* Demais informações necessárias para emissão da NFCom.

### FluxSBC

Responsável por:

* Receber a requisição.
* Validar o payload.
* Realizar o mapeamento para o formato esperado pela API.
* Executar a comunicação com o Emissor62.
* Tratar erros de integração.
* Registrar logs e auditoria.
* Retornar o resultado da emissão ao sistema solicitante.

## Fluxo da Integração

```text
Sistema de Co-Billing
        │
        │ Dados completos da NFCom
        │ + Chave da NFCom de referência
        ▼
FluxSBC
        │
        ├── Validação
        ├── Conversão para JSON da API
        ├── Chamada ao Emissor62
        ├── Registro de logs
        ▼
API Emissor62
        │
        ▼
Resposta da API
        │
        ▼
FluxSBC
        │
        ▼
Sistema de Co-Billing
```

## Requisitos Funcionais

* Expor endpoint REST para integração.
* Validar todos os campos obrigatórios.
* Suportar múltiplos itens na nota.
* Informar corretamente a chave de cofaturamento no payload enviado ao Emissor62.
* Registrar XML/JSON recebido, payload enviado, resposta da API e código HTTP.
* Permitir reprocessamento em caso de falhas.

## Critérios de Aceitação

* O sistema cliente consegue enviar todos os dados da NFCom para o FluxSBC.
* O FluxSBC converte corretamente o payload para o formato exigido pela API.
* A emissão ocorre com sucesso no Emissor62.
* O resultado da emissão é retornado ao sistema cliente.
* Todo o fluxo permanece registrado para auditoria e reprocessamento.


## Issue relacionada

Refs: [PX-108](https://linear.app/flux-tecnologia/issue/PX-108)
