# Plano — Co-Billing NFCom (FluxSBC / PX-108)

## Contexto

Nova funcionalidade de **co-billing** do FluxSBC: expor um controller que recebe os dados de uma NFCom (Modelo 62) de um sistema externo de co-billing, converte para o layout JSON da API **Emissor62**, envia a nota, recebe a resposta e **persiste todo o fluxo em MySQL** para auditoria e reprocessamento (Refs: [PX-108](https://linear.app/flux-tecnologia/issue/PX-108)).

O diretório atual `flux-cobilling/application` é um **staging isolado** com 3 arquivos incompletos. O trabalho será feito aqui (sem tocar na app FluxSBC de produção); a integração/cópia para o worktree do FluxSBC é etapa posterior.

**Estado atual dos arquivos:**
- `controllers/Nfcom.php` — lê XML de `php://input`, converte, envia e faz `echo` da resposta. Usa `show_error` (fora do padrão) e **não grava nada em banco**.
- `libraries/ApiEmissor62.php` — POST cURL para o Emissor62, token via querystring como placeholder `SEU_TOKEN`. Lança `Exception` em HTTP ≥ 300 (sem devolver http_code para persistência).
- `libraries/NFComMapper.php` — converte `infNFCom` → array do payload. **Não inclui** `faturamento.cofaturamento.chave` (requisito central do escopo).

**Confirmação técnica:** a chave de cofaturamento do payload (`faturamento.cofaturamento.chave = 43260730288995000107620010000000101043134096`) é a `chNFCom` de `protNFCom` do XML recebido do parceiro. Também está no atributo `Id` do `infNFCom` (`NFCom` + chave).

**Credenciais reais** (em `bbedit/.../Scratchpad.txt`): URL `http://servluc01.ddns.com.br/ApiEmissor62/Nota/v1/Enviar`, método POST, `Content-Type: application/json`, token via querystring `?TOKEN=34c44c2138c0b5255291d61cdf97cd42538207539851893241321a2aaeecead7`.

## Decisões acordadas
- **Alvo:** staging isolado em `flux-cobilling/application`.
- **Escopo:** persistência MySQL + correções essenciais (chave de cofaturamento, token via config, validação básica, resposta JSON padronizada, reprocessamento).
- **Chave de cofaturamento:** extrair do XML por padrão **e** aceitar override por parâmetro externo.
- **Charset da tabela:** `utf8mb4`/`utf8mb4_unicode_ci` (padrão moderno do FluxSBC, ex.: tabela `api_logs`).
- **Formato de entrada:** XML como principal (exemplos são XML); chave override aceita via query string / header.

## Arquivos a criar / modificar

### 1. `application/sql/nfcom_cobilling.sql` (NOVO — CREATE TABLE)
Segue o padrão moderno do FluxSBC (referência: `api_logs`). Persiste entrada, payload, resposta, http_code e os campos da nota emitida, com colunas para auditoria/reprocessamento.

```sql
CREATE TABLE `nfcom_cobilling` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reseller_id` int DEFAULT NULL,
  `chave_cofaturamento` varchar(50) DEFAULT NULL,   -- chNFCom do parceiro (referência)
  `xml_recebido` mediumtext,                         -- corpo de entrada (XML/JSON)
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
  `status` tinyint NOT NULL DEFAULT '2',             -- 0=autorizada, 1=erro, 2=pendente/reprocessar
  `tentativas` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chave_cofaturamento` (`chave_cofaturamento`),
  KEY `chave_nfcom` (`chave_nfcom`),
  KEY `status` (`status`),
  KEY `http_code` (`http_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
```

### 2. `application/config/nfcom.php` (NOVO — configuração)
Tira o token/URL de dentro do código (hoje é `SEU_TOKEN` hardcoded). No FluxSBC real será carregado por `$this->config->load('nfcom')`.

```php
$config['nfcom_api_url']     = 'http://servluc01.ddns.com.br/ApiEmissor62/Nota/v1/Enviar';
$config['nfcom_api_token']   = '34c44c2138c0b5255291d61cdf97cd42538207539851893241321a2aaeecead7';
$config['nfcom_api_timeout'] = 30;
```

### 3. `application/models/Nfcom_model.php` (NOVO)
Estende `CI_Model` (padrão do projeto — sem `MY_Model`), Query Builder. Métodos:
- `criar(array $data): int` → `$this->db->insert('nfcom_cobilling', $data); return $this->db->insert_id();`
- `atualizar(int $id, array $data): bool` → `$this->db->where('id',$id)->update(...)`
- `buscar(int $id): ?array` → `row_array()`
- `incrementar_tentativa(int $id)` (para reprocessamento)

Espelha o precedente `api_model::save_api_log()` (registro de url/payload/response/http_code/status).

### 4. `application/controllers/Nfcom.php` (MODIFICAR)
Carrega `Nfcom_model`, `NFComMapper`, `ApiEmissor62` no construtor. Substitui `echo`/`show_error` por resposta JSON padronizada + persistência.

**`enviar()`** — fluxo:
1. Lê corpo (`php://input`); valida não-vazio. Lê chave override opcional (`$this->input->get('chave')` ou header `X-Cofaturamento-Chave`).
2. `criar()` registro inicial (`xml_recebido`, `chave_cofaturamento`, `status=2`) → obtém `$id`.
3. `NFComMapper->convert($xml, $chaveOverride)` → payload; grava `payload_enviado`.
4. `ApiEmissor62->enviar($payload)` → `['response','http_code']`; **sempre** persiste `response`/`http_code`.
5. `json_decode` da resposta; extrai `sucesso`, `data.guid/chave/numero/descricao/mensagem/danfeCom`; define `status` (0 se sucesso/HTTP 2xx, senão 1); `atualizar($id, ...)`.
6. Devolve JSON ao cliente com resultado + `id` do registro.
7. `try/catch`: em erro, `atualizar($id, status=1, motivo=mensagem)` e retorna JSON de erro com HTTP adequado.

**`reprocessar($id)`** — busca registro, reenvia `payload_enviado` (ou reconverte `xml_recebido`), incrementa `tentativas`, atualiza resultado/status, retorna JSON.

Resposta JSON via `header()` + `echo json_encode(...)` com HTTP code correto (no FluxSBC real, trocar por `$this->response()` do `API_Controller`). Autenticação: gancho simples opcional validando header `X-Api-Token` contra `nfcom_api_token` do config — documentado como ponto a alinhar com o padrão de token do FluxSBC na integração.

### 5. `application/libraries/ApiEmissor62.php` (MODIFICAR)
- URL/token/timeout do config (`get_instance()->config`), com fallback aos valores atuais.
- `enviar(array $payload): array` retorna `['response'=>string,'http_code'=>int]` em vez de só a string — deixa o controller **sempre** persistir, inclusive em erro. Erro de cURL (transporte) continua via `Exception`; HTTP ≥ 300 **não** lança (retorna http_code para gravar).
- Mantém `CURLOPT_TIMEOUT`, headers e `TOKEN` na querystring.

### 6. `application/libraries/NFComMapper.php` (MODIFICAR)
- Assinatura `convert($xmlString, $chaveOverride = null)`.
- Extrai a chave: se `$chaveOverride` informado, usa-o; senão lê `//n:protNFCom//n:chNFCom`; fallback para o atributo `Id` de `infNFCom` (remove prefixo `NFCom`).
- Injeta `['faturamento' => ['cofaturamento' => ['chave' => $chave]]]` no array retornado.
- Mapeamento de itens/impostos permanece como está (fora do escopo desta entrega). **Observação registrada:** o payload de exemplo tem 1 item enquanto o XML do parceiro tem 4 — a lógica fiscal de quais itens compõem a nota da Flux (item com `CNPJLD`/`indSemCST`) é responsabilidade do sistema de co-billing conforme o escopo, e deve ser validada numa etapa futura se necessário.

### 7. Rotas (documentar para integração)
No FluxSBC real, adicionar em `application/config/routes.php`:
```php
$route['nfcom/enviar']             = 'Nfcom/enviar';
$route['nfcom/reprocessar/(:num)'] = 'Nfcom/reprocessar/$1';
```
(No staging o roteamento padrão do CI já resolve `Nfcom/enviar`.)

## Verificação

1. **Lint:** `php -l` em cada arquivo `.php` novo/alterado.
2. **Teste do mapper (isolado, sem CI/MySQL):** script no scratchpad que define `BASEPATH`, inclui `NFComMapper.php`, carrega `Arquivos_Exemplo/Xml_Recebido_do_PARCEIRO_Example_formatted.xml`, roda `convert()` e verifica:
   - presença de `faturamento.cofaturamento.chave` == `43260730288995000107620010000000101043134096`;
   - estrutura geral batendo com `Payload_API_DiginotaNFe_Example.json` (Identificacao/Destinatario/Assinante/Itens/Autorizacoes/Informacoes).
   - Testar também o caminho de override (passar chave explícita e conferir precedência).
3. **Sintaxe do SQL:** validar o `CREATE TABLE` (dry-run em MySQL local, se disponível) — senão, revisão manual do DDL.
4. **Fim-a-fim (na integração):** dentro do FluxSBC, `POST` do XML de exemplo para `nfcom/enviar`, conferir registro em `nfcom_cobilling` (payload/response/http_code/status) e a resposta JSON; depois `nfcom/reprocessar/{id}` sobre um registro com `status=1`. Documentado como etapa da integração (o staging não tem runtime CI/DB completo).

## Notas
- Nada em produção é alterado; tudo fica em `flux-cobilling/application`.
- Token real fica em `config/nfcom.php` (não commitar em repositório público; no FluxSBC real, considerar ler de `/var/lib/flux/flux-config.conf` como o `database.php` faz).

---

# Parte 2 — Tela web de upload de XML vinculado a conta

## Contexto

Além do endpoint de API (integração máquina-a-máquina), é preciso uma **tela web** onde um usuário autenticado (admin/revenda) faça **upload manual de um XML NFCom**, **vincule-o a uma conta** da tabela `accounts` e, opcionalmente, **emita na hora**. Isso cobre o caso de operação manual/reprocessamento pela interface do FluxSBC.

**Decisões acordadas:**
- **Ação:** salva sempre o XML vinculado à conta; um checkbox "emitir agora" dispara a conversão + envio ao Emissor62.
- **Formato:** módulo HMVC fiel ao FluxSBC (`extend('master.php')`, sessão, dropdown real de `accounts`). Só renderiza dentro do FluxSBC.
- **Armazenamento:** arquivo físico (`attachments/nfcom/`) **e** conteúdo no banco (`xml_recebido`).

**Padrões do FluxSBC a reutilizar** (referências reais):
- Molde de upload + view: `application/modules/account_import/` (controller `customer_import_preview` usa `$_FILES` + `finfo` + `move_uploaded_file`; view usa `extend('master.php')` + `enctype="multipart/form-data"`). O projeto **não usa a lib `upload` do CI**.
- Template base: `application/views/master.php` — blocos `startblock('page-title')`, `startblock('content')`, `startblock('extra_head')`, `end_extend()`. Já traz Bootstrap/jQuery/`.selectpicker`/Font Awesome e exibe flashdata (`flux_notification`/`flux_errormsg`).
- Dropdown de contas: `db_model->build_concat_dropdown("id,first_name,last_name,number,company_name","accounts","",array("reseller_id"=>$rid,"type"=>0,"status"=>0,"deleted"=>0))` — injeta `reseller_id` da revenda logada automaticamente. Campo nomeado `accountid`, renderizado com `form_dropdown()`.
- Sessão web: guard `if ($this->session->userdata('user_login') == FALSE) redirect(base_url().'/login/login');`; `accountinfo` traz `id`/`type`/`reseller_id`. `type`: -1=superadmin, 0=cliente, 1=revenda.
- FK por convenção: `accountid int NOT NULL DEFAULT 0` + `KEY account (accountid)` (padrão de `invoices`/`dids`/`support_ticket`).

## Arquitetura / nomes (evitar conflito de rota HMVC)
- **Tela** = novo módulo HMVC **`cobilling`** (não `nfcom`, que capturaria as rotas do controller de API flat). URLs: `cobilling/upload` (form) e `cobilling/upload_save` (POST).
- **API** = mantém o controller flat `Nfcom` (`nfcom/enviar`, `nfcom/reprocessar`).
- Ambos reutilizam `libraries/NFComMapper.php`, `libraries/ApiEmissor62.php` e `models/Nfcom_model.php`.

## Arquivos a criar / modificar

### 1. `application/sql/nfcom_cobilling.sql` (MODIFICAR) + `application/sql/nfcom_cobilling_alter.sql` (NOVO)
Adicionar à tabela:
```sql
`accountid` int NOT NULL DEFAULT '0' COMMENT 'Accounts table id',
`xml_file`  varchar(255) DEFAULT NULL,            -- nome do arquivo fisico salvo
`origem`    varchar(20) DEFAULT 'api',            -- 'api' | 'upload'
...
KEY `account` (`accountid`),
```
O `alter.sql` traz o `ALTER TABLE nfcom_cobilling ADD COLUMN ...` para quem já criou a tabela na Parte 1.

### 2. `application/config/nfcom.php` (MODIFICAR)
Acrescentar caminho de upload e mimes aceitos:
```php
$config['nfcom_upload_path']   = ''; // vazio => controller usa getcwd()."/attachments/nfcom/"
$config['nfcom_allowed_mimes'] = array('application/xml','text/xml','application/x-xml','text/plain');
```

### 3. `application/models/Nfcom_model.php` (MODIFICAR — reuso/DRY)
Mover a lógica de resposta do controller de API para cá (ponto único, usado pela API e pela tela):
- `extrair_dados_resposta($response): array` (movido de `Nfcom::extrairDadosResposta`).
- `registrar_resposta($id, $response, $http_code): array` — extrai campos, calcula `status` (0/1), `atualizar()` e retorna `['ok'=>bool,'data'=>...]`.
- `conta_pertence($accountid, $reseller_id): bool` — valida no servidor que a conta pertence ao tenant (anti-IDOR): `where id, deleted=0` e, se `reseller_id != 0`, `where reseller_id` (admin/superadmin não filtra).

### 4. `application/controllers/Nfcom.php` (MODIFICAR — refatoração pequena)
`processarEnvio()` passa a chamar `Nfcom_model::registrar_resposta()`; remove `extrairDadosResposta` (agora no model). **Comportamento inalterado** — revalidar com os testes existentes.

### 5. `application/modules/cobilling/controllers/Cobilling.php` (NOVO — `extends MX_Controller`)
Construtor: `parent::__construct()`, `$this->load->config('nfcom')`, `$this->load->model('Nfcom_model','nfcom_model')`, guard de sessão.
- **`upload()`** (GET): deriva `reseller_id` de `accountinfo` (`type==1 ? id : 0`); monta `$data['account_dropdown']` com `build_concat_dropdown(... 'accountid' ...)`; `load->view('view_nfcom_upload', $data)`.
- **`upload_save()`** (POST):
  1. `$accountid = $this->input->post('accountid', TRUE)`; valida `conta_pertence($accountid, $reseller_id)` (senão flashdata erro + redirect).
  2. Valida `$_FILES['nfcom_xml']`: presente, `error==0`, extensão `.xml`, `size>0`, `finfo` mime ∈ `nfcom_allowed_mimes`.
  3. `$xml = file_get_contents(tmp_name)`; valida parse via `NFComMapper` (rejeita XML inválido); `extrairChave($xml)`.
  4. `move_uploaded_file()` para `attachments/nfcom/` com nome **sanitizado/único** `nfcom-{accountid}-{Ymd-His}-{rand}.xml` (nunca o nome original — evita path traversal).
  5. `nfcom_model->criar([...])` com `accountid`, `reseller_id`, `xml_recebido`, `xml_file`, `chave_cofaturamento`, `origem='upload'`, `status=2`.
  6. Se `$this->input->post('emitir_agora')`: `convert()` → `atualizar(payload_enviado)` → `ApiEmissor62->enviar()` → `registrar_resposta()`; flashdata conforme resultado.
  7. `redirect` de volta com `flux_notification`/`flux_errormsg`.

### 6. `application/modules/cobilling/views/view_nfcom_upload.php` (NOVO)
`extend('master.php')` + `startblock('content')`: `<form method="post" action="cobilling/upload_save" enctype="multipart/form-data">` com:
- dropdown de contas (`<?php echo $account_dropdown; ?>`, classe `.selectpicker`);
- `<input type="file" name="nfcom_xml" accept=".xml" required>`;
- checkbox `<input type="checkbox" name="emitir_agora" value="1">` "Emitir agora";
- botão submit. `<script>` para mostrar o nome do arquivo (padrão da view `account_import`). Fecha com `endblock()` + `end_extend()`.

### 7. Acesso (documentar)
Roteável em `base_url()."cobilling/upload"`. Para o menu: inserir linha em `roles_and_permission` (`module_url='cobilling/upload'`) ou linkar por botão a partir de `accounts/customer_list` (como o `account_import`). No staging não há como registrar menu — documentado para a integração.

## Segurança (ênfase — regras de revenda)
- **Validação de tenant no servidor** (`conta_pertence`): impede uma revenda vincular/emitir para conta de outra revenda mesmo forjando `accountid` no POST. O dropdown já vem filtrado, mas o POST é revalidado.
- **Upload seguro**: extensão + `finfo` mime, nome de destino gerado pelo servidor (sem usar o nome do cliente), pasta `attachments/` (não servida como script).
- **XSS**: `$this->input->post(..., TRUE)`; saída de mensagens via `gettext()`/flashdata do master.

## Verificação (Parte 2)
1. **Lint:** `php -l` nos arquivos novos/alterados.
2. **Lógica isolável (sem CI):** adaptar o `test_response.php` para exercitar a lógica de `extrair_dados_resposta` (agora no model) via réplica; confirmar que a refatoração do controller de API não mudou o parsing. Reexecutar `test_mapper.php`.
3. **Tela (fim-a-fim, na integração FluxSBC):** copiar o módulo para `application/modules/cobilling/`, rodar o `alter.sql`, logar na UI, abrir `cobilling/upload`, selecionar conta + subir o XML de exemplo:
   - sem "emitir agora": conferir arquivo em `attachments/nfcom/` e registro (`accountid`, `xml_file`, `origem='upload'`, `status=2`);
   - com "emitir agora": conferir `http_code`/`chave_nfcom`/`status` preenchidos;
   - testar IDOR: forjar `accountid` de outra revenda e confirmar rejeição.

## Notas (Parte 2)
- A tela depende do ambiente FluxSBC (accounts, sessão, `master.php`, assets) — por isso é entregue em formato HMVC e testada na integração, não no staging.
- Escopo desta parte é a **tela de upload**. Uma tela de **listagem/reprocessamento** dos registros (grid + botão reprocessar) fica como extensão futura.

---

# Parte 3 — Tela web de listagem/auditoria/reprocessamento

## Contexto

Fechar o ciclo: além da API e do upload, o operador (admin ou revenda) precisa **auditar** as notas gravadas, **ver detalhes** do fluxo (XML/payload/response), **reprocessar** falhas e **baixar** o XML original a partir da UI do FluxSBC. A tela consome uma **VIEW SQL** (`view_nfcom_cobilling`) que junta `nfcom_cobilling` com `accounts` para trazer `number`, `customer` e demais campos do cliente na mesma linha.

**Padrão adotado (mapeado em `sbc-fluxv6/.../modules/refill_coupon` e `.../modules/invoices`):**
- **Flexigrid server-side** via `assets/js/module_js/generate_grid.js` (`build_grid`, `post_request_for_search`, `clear_search_request`).
- Convenção `cobilling_list` / `_list_json` / `_list_search` / `_clearsearchfilter` no controller.
- Anti-IDOR: filtro `reseller_id` aplicado no MODEL (`if ($acc['type'] == 1) $where['reseller_id'] = $acc['id'];`).
- Sem tocar em `libraries/flux/common.php`: as células são montadas manualmente no `_list_json` (padrão do `invoices_model`) — badges de status/origem, truncagem de chaves e botões de ação ficam no controller do módulo.

## Arquivos criados / modificados

Caminhos relativos a `flux-cobilling/`.

### 1. `database/view_nfcom_cobilling.sql` (NOVO)
VIEW SQL `CREATE OR REPLACE ... SQL SECURITY INVOKER` — `LEFT JOIN accounts` + coluna derivada `status_label` (CASE). Sem `ORDER BY` na definição (o flexigrid ordena via `$_GET[sortname/sortorder]`).

### 2. `web_interface/flux/application/modules/cobilling/models/nfcom_model.php` (MODIFICADO)
Três métodos novos:
- `get_cobilling_list($flag, $start=0, $limit=0)` — consulta `view_nfcom_cobilling` com filtro de tenant e `build_search('cobilling_list_search')`.
- `reprocessar_registro($id)` — ponto único de reprocessamento (busca → reconverte payload se necessário → `incrementar_tentativa` → `api_emissor62->enviar()` → `registrar_resposta()`). Reutilizável pela API na sequência.
- `excluir($id, $reseller_id)` — hard delete restrito ao tenant. Não remove o arquivo físico nesta versão (TODO documentado).

### 3. `web_interface/flux/application/modules/cobilling/libraries/cobilling_form.php` (NOVO)
`Cobilling_form`:
- `build_cobilling_grid()` — layout do flexigrid (Data, Conta, Cliente, Nº NFCom, Chaves, Origem, Status, Situação, Motivo, HTTP, Tentativas, Ação).
- `build_grid_buttons_cobilling()` — botões acima da grade: **New Upload** (redireciona para `cobilling/upload`) e **Export** CSV.
- `get_cobilling_search_form()` — filtros por data (`from_date`/`to_date`), conta, cliente, nº NFCom, chave NFCom, chave cofaturamento, status (INPUT integer com hint `0/1/2`) e origem (INPUT string).

> Status e Origem são INPUT (não SELECT) porque dropdowns custom exigem callback em `libraries/flux/common.php` (`set_XXX_status`), e não alteramos esse arquivo no staging. Na integração ao FluxSBC real, podem virar dropdowns adicionando os dois callbacks em `common.php`.

### 4. `web_interface/flux/application/modules/cobilling/controllers/cobilling.php` (MODIFICADO)
Mantém `upload`/`upload_save`. **Adiciona**:
- `cobilling_list`, `cobilling_list_json`, `cobilling_list_search`, `cobilling_clearsearchfilter`.
- `cobilling_view($id)` — popup facebox.
- `cobilling_reprocess($id)` — usa `nfcom_model->reprocessar_registro()` + flashdata + redirect.
- `cobilling_download_xml($id)` — prefere arquivo físico em `attachments/nfcom/`; cai no `xml_recebido` do banco.
- `cobilling_list_delete($id)` — restrito a admin/superadmin (`type < 1`).
- `cobilling_export()` — CSV de metadados via `csv_helper::array_to_csv`.
- Helpers privados: `_registro_do_tenant` (anti-IDOR), `_montar_cell`, `_status_badge`, `_origem_badge`, `_fmt_chave`, `_fmt_data`, `_xml_pretty`, `_json_pretty`, `_action_buttons`, `_is_admin`.

### 5. `web_interface/flux/application/modules/cobilling/views/view_nfcom_list.php` (NOVO)
Esqueleto Flexigrid seguindo `refill_coupon`: `extend('master.php')`, `startblock('extra_head')` com `build_grid('cobilling_grid', ...)`, `startblock('content')` com search bar oculto (`#search_bar`) e `<table id="cobilling_grid">` dentro de `<form id="ListForm" action="cobilling/cobilling_list_delete/0/">`.

### 6. `web_interface/flux/application/modules/cobilling/views/view_nfcom_details.php` (NOVO)
Popup facebox com **abas Bootstrap** (Nav-tabs): Resumo, XML recebido, Payload enviado, Resposta. Formatação bonita (`DOMDocument::formatOutput` para XML e `JSON_PRETTY_PRINT` para JSON). Botões de ação diretos no popup (Baixar XML, Reprocessar).

## Rotas (auto-routing HMVC)

- `cobilling/cobilling_list` — tela
- `cobilling/cobilling_list_json` — AJAX flexigrid
- `cobilling/cobilling_list_search` / `cobilling_clearsearchfilter`
- `cobilling/cobilling_view/{id}` — popup
- `cobilling/cobilling_reprocess/{id}`
- `cobilling/cobilling_download_xml/{id}`
- `cobilling/cobilling_list_delete/{id}` (admin)
- `cobilling/cobilling_export`

Para o menu no FluxSBC real: adicionar linha em `roles_and_permission` com `module_url='cobilling/cobilling_list'` (fora do staging).

## Verificação (Parte 3)

1. **Lint:** `php -l` em `controllers/cobilling.php`, `models/nfcom_model.php`, `libraries/cobilling_form.php`, `views/view_nfcom_list.php`, `views/view_nfcom_details.php`.
2. **SQL:** executar `database/view_nfcom_cobilling.sql` em MySQL local; `EXPLAIN SELECT * FROM view_nfcom_cobilling LIMIT 1;` para validar plano.
3. **Fim-a-fim (na integração FluxSBC):**
   - Rodar `nfcom_cobilling.sql` (Parte 1) ou `nfcom_cobilling_alter.sql` (Parte 2) + `view_nfcom_cobilling.sql`.
   - Copiar o módulo para `application/modules/cobilling/` e o controller flat `Nfcom.php` para `application/controllers/`.
   - Logar como reseller e como admin em `cobilling/cobilling_list`.
   - Testar cada filtro (data, número, cliente, status, origem), ordenação e paginação (`rp` = 10/25/50/100).
   - Testar ações: `VIEW` (popup com 4 abas), `RESEND` (registro `status=1` que passa a `status=0`), `DOWNLOAD` (`origem=upload` → arquivo físico; `origem=api` → fallback), `DELETE` (só admin).
   - Export CSV: baixar, abrir com UTF-8 (BOM do helper), conferir cabeçalhos.
   - IDOR: reseller A tentando ver/reprocessar/baixar `id` do reseller B → rejeita silenciosamente ou 404.

## Notas (Parte 3)

- **Callbacks de status/origem como dropdown**: viram melhoria futura ao adicionar `set_nfcom_status` e `set_nfcom_origem` em `libraries/flux/common.php` (fora do staging).
- **Remoção do arquivo físico no `excluir()`**: TODO explícito. Requer garantir que nenhum outro registro referencia o mesmo `xml_file` (colisão altamente improvável pelo nome único gerado, mas seguro checar) — usar `@unlink($dir.$row['xml_file'])` só após a checagem.
- **Refatorar `application/controllers/Nfcom.php::reprocessar`** para consumir `nfcom_model->reprocessar_registro()` fica como follow-up (elimina duplicação da lógica `processarEnvio`).
- **SQL SECURITY**: a VIEW versionada usa `INVOKER` (respeita permissões do usuário runtime). O dump que veio da UI do MySQL tinha `DEFINER=root@localhost SQL SECURITY DEFINER` — descartado para produção.

---

# Parte 4 — Reescrita do emitente no XML durante o upload

## Contexto

O XML enviado no upload manual traz os dados do **emitente parceiro** (blocos `emit`, `enderEmit` e `assinante`). No fluxo de co-faturamento, antes de gravar/enviar precisamos substituir esses blocos pelos dados da **conta Flux selecionada** no dropdown, transformando o XML "referência do parceiro" numa NFCom emitida em nome do cliente Flux.

**Preservação**: o arquivo físico em `attachments/nfcom/` fica com o XML **original** (assinatura digital do parceiro intacta, auditoria mantida). O `xml_recebido` no banco fica com o XML **modificado** — que é o que efetivamente foi convertido em payload e enviado ao Emissor62.

## Mapeamento aplicado

**`<emit>`** — `CNPJ` ← `accounts.tax_number` (só dígitos) | `xNome` ← `first_name + ' ' + last_name` | `xFant` ← `company_name` (fallback `xNome`).

**`<enderEmit>`** — `xLgr`/`nro` ← split de `address_1` (regex; fallback `nro='S/N'`) | `xCpl` e `xBairro` ← `address_2` (não há coluna de bairro em `accounts`) | `cMun` ← `municipios.codigo_ibge WHERE nome = accounts.city (+ UF se coluna existir)` | `xMun` ← `accounts.city` | `CEP` ← `postal_code` (só dígitos) | `UF` ← `accounts.province` | `fone` ← `telephone_1` (só dígitos) | `email` ← `accounts.email`.

**`<assinante>`** — `iCodAssinante` ← `accounts.id` | `nContrato` ← `accounts.number`.

> **Semântica de `accounts`**: `city` = município, `province` = UF (confirmado em `accounts_model::update_account_from_doc` do sbc-fluxv6). Contra-intuitivo mas correto.

## Arquivos alterados

- **`libraries/flux/nfcom_mapper.php`** — novos métodos:
  - `substituirDadosEmitente($xmlString, array $conta, $codIbge = null): string` — reescreve os 14 nós via `dom_import_simplexml` + `nodeValue` (preserva atributos, namespace e demais filhos).
  - `_splitEndereco($address_1): array` — regex `/^(.*?)[,\s]+(\d+[A-Za-z]*)\s*$/u`.
  - `_setNode(SimpleXMLElement, xpath, value): bool` — helper de substituição atômica.

- **`modules/cobilling/models/nfcom_model.php`** — novo método:
  - `getCodigoIbgeMunicipio($nome, $uf = null): ?string` — consulta `municipios`. Defensivo: tenta primeiro com filtro de UF (colunas `uf` OR `sigla_uf`) para desambiguar homônimos e cai para busca só por nome se a coluna não existir.

- **`modules/cobilling/controllers/cobilling.php`** — `upload_save()`:
  - Nova validação dos campos mínimos da conta (`tax_number`, `address_1`, `postal_code`, `city`, `province`, `email`) — bloqueia com mensagem específica se algum estiver vazio.
  - Lookup do IBGE + chamada a `substituirDadosEmitente` **antes** de `criar()` e `_emitir()`.
  - Aviso não-bloqueante via flashdata quando o município não é encontrado — o `cMun` original do XML fica preservado.

## Cuidados

- **Assinatura digital** do parceiro fica inválida no XML modificado — aceitável porque o Emissor62 consome o JSON convertido, não o XML.  O arquivo físico em `attachments/nfcom/` mantém o XML assinado.
- **Case/accent insensitivity** do lookup do IBGE depende do collation da tabela `municipios`. Se for `utf8mb4_0900_ai_ci` (padrão moderno), "Novo Hamburgo" bate com "NOVO HAMBURGO" etc.
- **Schema variante da `municipios`**: o método assume colunas `nome` e `codigo_ibge`; UF é opcional (`uf` ou `sigla_uf`). Ajustar no model se o schema real for diferente (ex.: `cod_ibge` como no `sbc-fluxv6`).

## Verificação

1. `php -l` em `nfcom_mapper.php`, `nfcom_model.php`, `controllers/cobilling.php`.
2. Script isolado no scratchpad exercitando `substituirDadosEmitente` sobre o XML de exemplo (com conta fake completa), validando via XPath que os 14 campos foram reescritos.
3. Fim-a-fim (integração): upload → conferir na aba "XML recebido" do popup que os dados são da conta Flux; conferir que "Baixar XML" traz o original (com assinatura do parceiro); conferir emissão real no Emissor62 (opção "emitir agora").
