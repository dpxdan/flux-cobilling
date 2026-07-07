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
