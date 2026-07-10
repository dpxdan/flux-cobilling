<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Modulo web Co-Billing NFCom (PX-108).
 *
 * Modulo HMVC. Cobre:
 *   - Upload manual de XML NFCom vinculado a uma conta (upload/upload_save)
 *   - Listagem/auditoria dos registros gravados em nfcom_cobilling
 *     (cobilling_list, cobilling_list_json, cobilling_list_search,
 *      cobilling_clearsearchfilter)
 *   - Detalhes, reprocessamento, download de XML, exclusao e export CSV.
 *
 * Reutiliza NFComMapper, ApiEmissor62 e Nfcom_model (compartilhados com o
 * endpoint de API em application/controllers/Nfcom.php). A grade segue o
 * padrao Flexigrid server-side do FluxSBC.
 *
 * Rotas (roteamento automatico HMVC):
 *   GET  cobilling/upload                         -> formulario de upload
 *   POST cobilling/upload_save                    -> processa o upload
 *   GET  cobilling/cobilling_list                 -> tela de listagem
 *   GET  cobilling/cobilling_list_json            -> endpoint AJAX do flexigrid
 *   POST cobilling/cobilling_list_search          -> persiste filtros do search
 *   GET  cobilling/cobilling_clearsearchfilter    -> limpa filtros do search
 *   GET  cobilling/cobilling_view/{id}            -> popup de detalhes
 *   GET  cobilling/cobilling_reprocess/{id}       -> reenvia ao Emissor62
 *   GET  cobilling/cobilling_download_xml/{id}    -> baixa o XML original
 *   GET  cobilling/cobilling_list_delete/{id}     -> exclui registro (admin)
 *   GET  cobilling/cobilling_export               -> CSV de metadados
 */
class cobilling extends MX_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->config('nfcom_config', FALSE, TRUE);
        $this->load->model('nfcom_model');
        $this->load->library('flux/nfcom_mapper');
        $this->load->library('flux/api_emissor62');
        $this->load->library('flux_log');
        // Dependencias da tela de listagem (flexigrid server-side).
        $this->load->library('cobilling_form');
        $this->load->library('flux/form');
        $this->load->model('db_model');
        // Tela web: exige sessao logada (diferente do token de API).
        if ($this->session->userdata('user_login') == FALSE) {
            redirect(base_url() . 'login/login');
        }
    }

    /** Formulario de upload. */
    public function upload() {
        $data['page_title']       = gettext('Importar Arquivo NFCom');
        $data['account_dropdown'] = $this->_dropdown_contas();
        $this->load->view('view_nfcom_upload', $data);
    }

    /** Recebe o upload, salva o arquivo + registro e (opcional) emite. */
    public function upload_save() {
        $reseller_id = $this->_reseller_id();
        $accountid   = (int) $this->input->post('accountid', TRUE);

        // 1) Conta valida e pertencente ao tenant (revalida o POST, nao confia no dropdown).
        $conta = ($accountid > 0) ? $this->nfcom_model->get_conta_tenant($accountid, $reseller_id) : null;
        if ($conta === null) {
            return $this->_falha(gettext('Conta inválida ou não pertence a você.'));
        }

        // 2) Arquivo presente e sem erro de upload.
        if (empty($_FILES['nfcom_xml']['name']) || $_FILES['nfcom_xml']['error'] != 0 || $_FILES['nfcom_xml']['size'] <= 0) {
            return $this->_falha(gettext('Selecione um arquivo XML ou XLSX válido.'));
        }

        // 3) Extensao + MIME real (finfo).
        $ext  = strtolower(pathinfo($_FILES['nfcom_xml']['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['nfcom_xml']['tmp_name']);
        finfo_close($finfo);
        $mimes_ok = $this->config->item('nfcom_allowed_mimes');
        if (!is_array($mimes_ok)) $mimes_ok = array('application/xml', 'text/xml', 'application/x-xml', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        
        $allowed_exts = array('xml', 'xlsx');
        if (!in_array($ext, $allowed_exts) || !in_array($mime, $mimes_ok)) {
            return $this->_falha(gettext('Formato inválido: envie um arquivo .xml ou .xlsx.'));
        }

        // Se for XLSX, delega para o novo metodo adequado
        if ($ext === 'xlsx') {
            return $this->_cobilling_importar_xlsx1($accountid, $reseller_id, $conta);
        }

        // --- Fluxo Original XML ---
        $xml = file_get_contents($_FILES['nfcom_xml']['tmp_name']);

        try {
            $chave = $this->nfcom_mapper->extrairChave($xml);
        } catch (Exception $e) {
            return $this->_falha(gettext('XML inválido: ') . $e->getMessage());
        }

        // 5) Valida campos minimos da conta
        $obrig = array(
            'tax_number'  => gettext('CNPJ'),
            'tax_city_number'  => gettext('IE'),
            'address_1'   => gettext('Endereço'),
            'postal_code' => gettext('CEP'),
            'city'        => gettext('Município'),
            'province'    => gettext('UF'),
            'email'       => gettext('E-mail'),
        );
        foreach ($obrig as $col => $label) {
            if (empty($conta[$col])) {
                return $this->_falha(sprintf(gettext('A conta selecionada não tem "%s" cadastrado.'), $label));
            }
        }

        $cod_ibge = $this->nfcom_model->getCodigoIbgeMunicipio($conta['city'], $conta['province']);

        $dir = $this->config->item('nfcom_upload_path');
        if (empty($dir)) $dir = getcwd() . '/attachments/nfcom/';
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, TRUE);
        $nome = 'nfcom-' . $accountid . '-' . date('Ymd-His') . '-' . mt_rand(1000, 9999) . '.' . $ext;
        if (!@move_uploaded_file($_FILES['nfcom_xml']['tmp_name'], $dir . $nome)) {
            return $this->_falha(gettext('Falha ao salvar o arquivo. Tente novamente.'));
        }

        $xml_modificado = $xml;
        try {
            $xml_modificado = $this->nfcom_mapper->substituirDadosEmitente($xml, $conta, $cod_ibge);
        } catch (Exception $e) {
            $this->flux_log->write_log('error', json_encode($e->getMessage()));
            return $this->_falha(gettext('Falha ao reescrever o emitente do XML: ') . $e->getMessage());
        }

        $id = $this->nfcom_model->criar(array(
            'reseller_id'         => (isset($conta['reseller_id']) && $conta['reseller_id'] !== NULL) ? (int) $conta['reseller_id'] : NULL,
            'accountid'           => $accountid,
            'chave_cofaturamento' => ($chave !== '' ? $chave : NULL),
            'xml_recebido'        => $xml_modificado,
            'xml_file'            => $nome,
            'origem'              => 'upload',
            'status'              => 2,
        ));

        if ($cod_ibge === null) {
            $this->session->set_flashdata('flux_notification',
                sprintf(gettext('Município "%s" não encontrado na tabela municipios — cMun original preservado no XML.'), $conta['city'])
            );
        }

        if ($this->input->post('emitir_agora')) {
            $this->_emitir($id, $xml_modificado);
        } else if ($cod_ibge !== null) {
            $this->session->set_flashdata('flux_errormsg', sprintf(gettext('XML importado e vinculado à conta (registro #%d).'), $id));
        }

        redirect(base_url() . 'cobilling/cobilling_list/');
    }

    /**
     * Adequacao do metodo _cobilling_importar_xlsx1 para usar a library flux_excel.
     * Mapeia o template do usuario para o payload da NFCom.
     */
    private function _cobilling_importar_xlsx1($accountid, $reseller_id, array $conta)
    {
        $uploadPath = $this->config->item('nfcom_upload_path');
        if (empty($uploadPath)) $uploadPath = getcwd() . '/attachments/nfcom/';
        $uploadPath = rtrim($uploadPath, '/') . '/';

        if (!is_dir($uploadPath) && !mkdir($uploadPath, 0775, TRUE)) {
            $this->flux_log->write_log('error', json_encode('Não foi possível criar o diretório de upload: ' . $uploadPath));
            return $this->_falha(gettext('Falha na infraestrutura de upload.'));
        }

        $config = array(
            'upload_path'   => $uploadPath,
            'allowed_types' => 'xlsx',
            'max_size'      => 20480,
            'encrypt_name'  => TRUE,
        );

        $this->load->library('upload');
        $this->upload->initialize($config);

        if (!$this->upload->do_upload('nfcom_xml')) {
            $erro = $this->upload->display_errors('', '');
            $this->flux_log->write_log('error', json_encode('Erro no upload de XLSX: ' . $erro));
            return $this->_falha(gettext('Upload XLSX inválido.'));
        }

        $fileData = $this->upload->data();
        $fullPath = $fileData['full_path'];

        try {
            // Usamos a nossa library flux_excel (minimalista, sem depender de autoload externo pesado)
            $this->load->library('flux/flux_excel');
            $allSheets = $this->flux_excel->parse_xlsx($fullPath);
            
            // Gera o payload mapeado conforme o template do usuario
            $payload = $this->flux_excel->convert_to_payload($allSheets, $conta);
            $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
        } catch (Exception $e) {
            $this->flux_log->write_log('error', json_encode('Erro ao processar XLSX: ' . $e->getMessage()));
            return $this->_falha(gettext('Falha ao processar o arquivo XLSX: ') . $e->getMessage());
        }

        // Cria registro em nfcom_cobilling
        $id = $this->nfcom_model->criar(array(
            'reseller_id'         => isset($conta['reseller_id']) && $conta['reseller_id'] !== NULL ? (int) $conta['reseller_id'] : NULL,
            'accountid'           => (int) $accountid,
            'xml_recebido'        => NULL, 
            'xml_file'            => $fileData['file_name'],
            'origem'              => 'xlsx',
            'status'              => 2,
            'payload_enviado'     => $json_payload, 
        ));

        // Emissao opcional
        if ($this->input->post('emitir_agora')) {
            $this->_emitir_payload($id, $payload);
        } else {
            $this->session->set_flashdata('flux_errormsg', sprintf(gettext('Planilha XLSX importada e registrada (registro #%d).'), $id));
        }

        redirect(base_url() . 'cobilling/cobilling_list/');
    }

    /** Helper para emitir quando ja temos o payload pronto (XLSX). */
    private function _emitir_payload($id, $payload) {
        try {
            $r   = $this->api_emissor62->enviar($payload);
            $res = $this->nfcom_model->registrar_resposta($id, $r['response'], $r['http_code']);
            if ($res['ok']) {
                $this->session->set_flashdata('flux_errormsg', sprintf(gettext('NFCom emitida com sucesso via XLSX (registro #%d).'), $id));
            } else {
                $this->session->set_flashdata('flux_notification', sprintf(gettext('Falha na emissão via XLSX (registro #%d, HTTP %d).'), $id, $r['http_code']));
            }
        } catch (Exception $e) {
            $this->nfcom_model->atualizar($id, array('status' => 1, 'motivo' => substr($e->getMessage(), 0, 255)));
            $this->session->set_flashdata('flux_notification', gettext('Erro de comunicação: ') . $e->getMessage());
        }
    }

    // --- helpers ---

    /** Converte o XML e envia ao Emissor62, persistindo o resultado no registro $id. */
    private function _emitir($id, $xml) {
        
        try {
            $payload = $this->nfcom_mapper->convert($xml);
            $this->nfcom_model->atualizar($id, array('payload_enviado' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            $r   = $this->api_emissor62->enviar($payload);
            $res = $this->nfcom_model->registrar_resposta($id, $r['response'], $r['http_code']);
            if ($res['ok']) {
                $this->session->set_flashdata('flux_errormsg', sprintf(gettext('NFCom emitida com sucesso (registro #%d).'), $id));
            } else {
                $this->session->set_flashdata('flux_notification', sprintf(gettext('Falha na emissão (registro #%d, HTTP %d). Reprocesse depois.'), $id, $r['http_code']));
            }
        } catch (Exception $e) {
            $this->nfcom_model->atualizar($id, array('status' => 1, 'motivo' => substr($e->getMessage(), 0, 255)));
            $this->session->set_flashdata('flux_notification', gettext('Erro de comunicação com o Emissor62: ') . $e->getMessage());
        }
    }

    /** reseller_id do tenant: revenda (type==1) usa o proprio id; admin/superadmin usa 0 (ve todas). */
    private function _reseller_id() {
        $acc = $this->session->userdata('accountinfo');
        return (isset($acc['type']) && $acc['type'] == 1) ? (int) $acc['id'] : 0;
    }

    /** Monta o <select name="accountid"> de contas de cliente filtradas por revenda. */
    private function _dropdown_contas() {
        $contas = $this->nfcom_model->listar_contas($this->_reseller_id());
        $lista  = array('' => gettext('Selecione a conta...'));
        foreach ($contas as $c) {
            $nome = (trim($c['company_name']) !== '') ? $c['company_name'] : trim($c['first_name'] . ' ' . $c['last_name']);
            $lista[$c['id']] = $nome . ' (' . $c['number'] . ')';
        }
        $extra = 'id="accountid" class="selectpicker form-control" data-live-search="true"';
        return form_dropdown('accountid', $lista, '', $extra);
    }

    /** Grava mensagem de erro e volta para o formulario. */
    private function _falha($msg) {
        $this->session->set_flashdata('flux_notification', $msg);
        redirect(base_url() . 'cobilling/upload');
    }

    // ==================================================================
    // Tela de listagem (view_nfcom_cobilling) - PADRAO FLEXIGRID
    // ==================================================================

    /** Renderiza a tela de listagem. */
    public function cobilling_list() {
        $data['page_title']   = gettext('NFCom Co-Billing');
        $data['search_flag']  = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields']  = $this->cobilling_form->build_cobilling_grid();
        $data['grid_buttons'] = $this->cobilling_form->build_grid_buttons_cobilling();
        $data['form_search']  = $this->form->build_serach_form($this->cobilling_form->get_cobilling_search_form());
        $this->load->view('view_nfcom_list', $data);
    }

    /**
     * Endpoint AJAX consumido pelo flexigrid. Retorna JSON no formato
     * {page, total, rows: [{id, cell: [...]}, ...]}.
     * As celulas sao montadas manualmente (padrao invoices_model), com
     * badges de status/origem e botoes de acao contextuais.
     */
    public function cobilling_list_json() {
        $count_all   = $this->nfcom_model->get_cobilling_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data   = $paging_data['json_paging'];
        $json_data['rows'] = array();

        $result = $this->nfcom_model->get_cobilling_list(
            true,
            $paging_data['paging']['start'],
            $paging_data['paging']['page_no']
        );

        if ($result && $result->num_rows() > 0) {
            $is_admin = $this->_is_admin();
            foreach ($result->result_array() as $row) {
                $json_data['rows'][] = array(
                    'id'   => $row['id'],
                    'cell' => $this->_montar_cell($row, $is_admin),
                );
            }
        }
        echo json_encode($json_data);
    }

    /** Persiste filtros do search bar em sessao (usados por build_search do model). */
    public function cobilling_list_search() {
        $ajax_search = $this->input->post('ajax_search');
        if ($ajax_search == 1) {
            unset($_POST['ajax_search']);
            unset($_POST['action']);
            $this->session->set_userdata('cobilling_list_search', $_POST);
        }
    }

    /** Limpa filtros do search bar. */
    public function cobilling_clearsearchfilter() {
        $this->session->set_userdata('cobilling_list_search', '');
    }

    /**
     * Popup facebox de detalhes de um registro. Valida tenant (anti-IDOR).
     * Formata XML/JSON para leitura humana.
     */
    public function cobilling_view($id) {
        $reg = $this->_registro_do_tenant((int) $id);
        if ($reg === null) {
            echo '<div class="alert alert-danger">' . gettext('Registro não encontrado ou sem permissão.') . '</div>';
            return;
        }
        $data['reg']             = $reg;
        $data['xml_pretty']      = $this->_xml_pretty($reg['xml_recebido']);
        $data['payload_pretty']  = $this->_json_pretty($reg['payload_enviado']);
        $data['response_pretty'] = $this->_json_pretty($reg['response']);
        $data['status_badge']    = $this->_status_badge($reg['status']);
        $data['origem_badge']    = $this->_origem_badge($reg['origem']);
        $this->load->view('view_nfcom_details', $data);
    }

    /**
     * Reprocessa um registro (delega ao model). Redireciona para a listagem
     * com flashdata refletindo o resultado.
     */
    public function cobilling_reprocess($id) {
        $reg = $this->_registro_do_tenant((int) $id);
        if ($reg === null) {
            $this->session->set_flashdata('flux_notification', gettext('Registro não encontrado ou sem permissão.'));
            redirect(base_url() . 'cobilling/cobilling_list/');
        }
        $r = $this->nfcom_model->reprocessar_registro((int) $id);
        if (!empty($r['ok'])) {
            $this->session->set_flashdata('flux_errormsg', sprintf(gettext('NFCom reprocessada com sucesso (registro #%d).'), (int) $id));
        } else {
            $msg = !empty($r['error']) ? $r['error'] : sprintf('HTTP %d', (int) $r['http_code']);
            $this->session->set_flashdata('flux_notification', sprintf(gettext('Falha ao reprocessar #%d: %s'), (int) $id, $msg));
        }
        redirect(base_url() . 'cobilling/cobilling_list/');
    }

    /**
     * Baixa o XML original: prioriza o arquivo fisico em attachments/nfcom/
     * (quando origem=upload); cai no xml_recebido gravado no banco.
     */
    public function cobilling_download_xml($id) {
        $reg = $this->_registro_do_tenant((int) $id);
        if ($reg === null) {
            show_error(gettext('Registro não encontrado ou sem permissão.'), 404);
            return;
        }

        $ext  = !empty($reg['xml_file']) ? strtolower(pathinfo($reg['xml_file'], PATHINFO_EXTENSION)) : 'xml';
        $nome = 'nfcom-' . (int) $id . '.' . $ext;
        $dir  = $this->config->item('nfcom_upload_path');
        if (empty($dir)) $dir = getcwd() . '/attachments/nfcom/';
        $dir  = rtrim($dir, '/') . '/';
        $path = !empty($reg['xml_file']) ? ($dir . $reg['xml_file']) : '';

        $mime = ($ext === 'xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/xml';

        header('Content-Type: ' . $mime . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        if ($path !== '' && is_file($path) && is_readable($path)) {
            header('Content-Length: ' . filesize($path));
            readfile($path);
        } else {
            $body = (string) $reg['xml_recebido'];
            header('Content-Length: ' . strlen($body));
            echo $body;
        }
    }

    /** Exclui um registro. Restrito a admin/superadmin (type < 1). */
    public function cobilling_list_delete($id = 0) {
        if (!$this->_is_admin()) {
            $this->session->set_flashdata('flux_notification', gettext('Ação restrita a administradores.'));
            redirect(base_url() . 'cobilling/cobilling_list/');
        }
        $id = (int) $id;
        if ($id <= 0) {
            redirect(base_url() . 'cobilling/cobilling_list/');
        }
        $ok = $this->nfcom_model->excluir($id, 0); // admin: sem filtro de reseller
        if ($ok) {
            $this->session->set_flashdata('flux_errormsg', sprintf(gettext('Registro #%d excluído.'), $id));
        } else {
            $this->session->set_flashdata('flux_notification', sprintf(gettext('Não foi possível excluir o registro #%d.'), $id));
        }
        redirect(base_url() . 'cobilling/cobilling_list/');
    }

    /**
     * Export CSV: exporta apenas metadados (sem payload/response brutos),
     * respeitando o mesmo escopo de tenant e os filtros do search bar.
     */
    public function cobilling_export() {
        $this->load->helper('csv');
        $result = $this->nfcom_model->get_cobilling_list(true, 0, 0);

        $rows   = array();
        $rows[] = array(
            'ID', 'Data', 'Conta', 'Cliente', 'Nº NFCom', 'Chave NFCom',
            'Chave Cofaturamento', 'Origem', 'Status', 'Situação', 'HTTP',
            'Motivo', 'Tentativas'
        );

        if ($result && $result->num_rows() > 0) {
            foreach ($result->result_array() as $r) {
                $rows[] = array(
                    (int) $r['id'],
                    $r['created_at'],
                    isset($r['number']) ? $r['number'] : '',
                    isset($r['customer']) ? $r['customer'] : '',
                    isset($r['numero']) ? $r['numero'] : '',
                    isset($r['chave_nfcom']) ? $r['chave_nfcom'] : '',
                    isset($r['chave_cofaturamento']) ? $r['chave_cofaturamento'] : '',
                    isset($r['origem']) ? $r['origem'] : '',
                    isset($r['status_label']) ? $r['status_label'] : '',
                    isset($r['situacao']) ? $r['situacao'] : '',
                    isset($r['http_code']) ? $r['http_code'] : '',
                    isset($r['motivo']) ? $r['motivo'] : '',
                    isset($r['tentativas']) ? (int) $r['tentativas'] : 0,
                );
            }
        }
        array_to_csv($rows, 'nfcom_cobilling_' . date('Ymd_His') . '.csv');
    }

    // --- private views helpers ---

    /** Monta as celulas (cell) de uma linha do flexigrid. */
    private function _montar_cell(array $row, $is_admin) {
        return array(
            (int) $row['id'],
            $row['created_at'],
            htmlspecialchars($row['number'] . ' - ' . $row['customer'], ENT_QUOTES, 'UTF-8'),
            $row['numero'],
            $row['chave_nfcom'],
            $row['chave_cofaturamento'],
            $this->_origem_badge($row['origem']),
            $this->_status_badge($row['status']),
            $row['http_code'],
            $this->_action_buttons($row['id'], $row, $is_admin),
        );
    }

    /** Badge de status (sucesso/falha/pendente). */
    private function _status_badge($status) {
        $mapa = array(
            0 => array('badge-success', gettext('Sucesso')),
            1 => array('badge-danger',  gettext('Falha')),
            2 => array('badge-warning', gettext('Pendente')),
        );
        $cls = isset($mapa[$status]) ? $mapa[$status][0] : 'badge-secondary';
        $lbl = isset($mapa[$status]) ? $mapa[$status][1] : gettext('Desconhecido');
        return '<span class="badge ' . $cls . '">' . htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /** Badge de origem (api/upload). */
    private function _origem_badge($origem) {
        $origem = strtolower((string) $origem);
        if ($origem === 'api') return '<span class="badge badge-info">API</span>';
        if ($origem === 'upload') return '<span class="badge badge-primary">Upload</span>';
        if ($origem === 'xlsx') return '<span class="badge badge-success">XLSX</span>';
        return '<span class="badge badge-secondary">-</span>';
    }

    /** Botoes de acao por linha (VIEW/RESEND/DOWNLOAD/DELETE). */
    private function _action_buttons($id, array $row, $is_admin) {
        $base = base_url();
        $view = '<a href="' . $base . 'cobilling/cobilling_view/' . $id . '" '. 'class="btn btn-royelblue btn-sm facebox" rel="facebox" '. 'title="' . gettext('Detalhes') . '"><i class="fa fa-search fa-fw"></i></a>';
        $resend = '<a href="' . $base . 'cobilling/cobilling_reprocess/' . $id . '" ' . 'class="btn btn-royelblue btn-sm" ' . 'onclick="return confirm(\'' . gettext('Reprocessar esta NFCom?') . '\');" ' . 'title="' . gettext('Reprocessar') . '"><i class="fa fa-repeat fa-fw"></i></a>';
        $dl = '<a href="' . $base . 'cobilling/cobilling_download_xml/' . $id . '" ' . 'class="btn btn-royelblue btn-sm" ' . 'title="' . gettext('Baixar Arquivo') . '"><i class="fa fa-cloud-download fa-fw"></i></a>';
        $del = '';
        if ($is_admin) {
            $del = '<a href="' . $base . 'cobilling/cobilling_list_delete/' . $id . '" ' . 'class="btn btn-royelblue btn-sm" ' . 'onclick="return confirm(\'' . gettext('Excluir este registro?') . '\');" ' . 'title="' . gettext('Excluir') . '"><i class="fa fa-trash fa-fw"></i></a>';
        }
        return $view . '&nbsp;' . $resend . '&nbsp;' . $dl . ($del !== '' ? '&nbsp;' . $del : '');
    }

    /** Formata XML para exibicao (identado). Retorna a string original em falha. */
    private function _xml_pretty($xml) {
        $xml = (string) $xml;
        if ($xml === '') return '';
        if (strpos($xml, '<!-- XLSX UPLOADED:') === 0) return $xml;
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (@$dom->loadXML($xml)) {
            $out = $dom->saveXML();
            libxml_use_internal_errors($prev);
            return $out !== false ? $out : $xml;
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $xml;
    }

    /** Formata JSON para exibicao (identado). Retorna a string original em falha. */
    private function _json_pretty($json) {
        $json = (string) $json;
        if ($json === '') return '';
        $j = json_decode($json);
        if ($j === null) return $json;
        return json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Retorna o registro se pertencer ao tenant, senao null. */
    private function _registro_do_tenant($id) {
        $reseller_id = $this->_reseller_id();
        $reg = $this->nfcom_model->buscar($id);
        if ($reg === null) return null;
        if ($reseller_id !== 0 && (int) $reg['reseller_id'] !== $reseller_id) return null;
        return $reg;
    }

    /** true se for admin/superadmin (type < 1). */
    private function _is_admin() {
        $acc = $this->session->userdata('accountinfo');
        return (isset($acc['type']) && (int) $acc['type'] < 1);
    }
}
