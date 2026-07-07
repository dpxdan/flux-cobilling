<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tela web de upload de XML NFCom vinculado a uma conta (co-billing) - PX-108.
 *
 * Modulo HMVC. Permite ao usuario autenticado (admin/revenda) subir um XML de
 * NFCom, vincula-lo a uma conta da tabela accounts e, opcionalmente, emitir na
 * hora no Emissor62. Reutiliza NFComMapper, ApiEmissor62 e Nfcom_model
 * (compartilhados com o endpoint de API em application/controllers/Nfcom.php).
 *
 * Rotas (roteamento automatico HMVC):
 *   GET  cobilling/upload        -> formulario
 *   POST cobilling/upload_save   -> processa o upload
 */
class cobilling extends MX_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->config('nfcom_config', FALSE, TRUE);
        $this->load->model('nfcom_model');
        $this->load->library('flux/NFComMapper');
        $this->load->library('flux_log');
        // Tela web: exige sessao logada (diferente do token de API).
        if ($this->session->userdata('user_login') == FALSE) {
            redirect(base_url() . 'login/login');
        }
    }

    /** Formulario de upload. */
    public function upload() {
        $data['page_title']       = gettext('Importar XML NFCom (Co-Billing)');
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
            return $this->_falha(gettext('Selecione um arquivo XML válido.'));
        }

        // 3) Extensao + MIME real (finfo).
        $ext  = strtolower(pathinfo($_FILES['nfcom_xml']['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['nfcom_xml']['tmp_name']);
        finfo_close($finfo);
        $mimes_ok = $this->config->item('nfcom_allowed_mimes');
        if (!is_array($mimes_ok)) $mimes_ok = array('application/xml', 'text/xml', 'text/plain');
        if ($ext !== 'xml' || !in_array($mime, $mimes_ok)) {
            return $this->_falha(gettext('Formato inválido: envie um arquivo .xml.'));
        }

        // 4) Le o conteudo e valida o XML (parse) + extrai a chave de cofaturamento.
        $xml = file_get_contents($_FILES['nfcom_xml']['tmp_name']);
        
        $this->flux_log->write_log('upload_save', json_encode($xml));
        try {
            $chave = $this->NFComMapper->extrairChave($xml);
        } catch (Exception $e) {
            return $this->_falha(gettext('XML inválido: ') . $e->getMessage());
        }

        // 5) Move o arquivo para o destino, com nome gerado pelo servidor (evita path traversal).
        $dir = $this->config->item('nfcom_upload_path');
        if (empty($dir)) $dir = getcwd() . '/attachments/nfcom/';
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, TRUE);
        $nome = 'nfcom-' . $accountid . '-' . date('Ymd-His') . '-' . mt_rand(1000, 9999) . '.xml';
        if (!@move_uploaded_file($_FILES['nfcom_xml']['tmp_name'], $dir . $nome)) {
            return $this->_falha(gettext('Falha ao salvar o arquivo. Tente novamente.'));
        }

        // 6) Registro inicial vinculado a conta (status 2 = pendente).
        $id = $this->nfcom_model->criar(array(
            'reseller_id'         => (isset($conta['reseller_id']) && $conta['reseller_id'] !== NULL) ? (int) $conta['reseller_id'] : NULL,
            'accountid'           => $accountid,
            'chave_cofaturamento' => ($chave !== '' ? $chave : NULL),
            'xml_recebido'        => $xml,
            'xml_file'            => $nome,
            'origem'              => 'upload',
            'status'              => 2,
        ));

        // 7) Emissao opcional (checkbox "emitir agora").
        if ($this->input->post('emitir_agora')) {
            $this->_emitir($id, $xml);
        } else {
            $this->session->set_flashdata('flux_notification', sprintf(gettext('XML importado e vinculado à conta (registro #%d).'), $id));
        }

        redirect(base_url() . 'cobilling/upload');
    }

    // --- helpers ---

    /** Converte o XML e envia ao Emissor62, persistindo o resultado no registro $id. */
    private function _emitir($id, $xml) {
        $this->load->library('flux/ApiEmissor62');
        try {
            $payload = $this->NFComMapper->convert($xml);
            $this->nfcom_model->atualizar($id, array('payload_enviado' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            $r   = $this->apiemissor62->enviar($payload);
            $res = $this->nfcom_model->registrar_resposta($id, $r['response'], $r['http_code']);
            if ($res['ok']) {
                $this->session->set_flashdata('flux_notification', sprintf(gettext('NFCom emitida com sucesso (registro #%d).'), $id));
            } else {
                $this->session->set_flashdata('flux_errormsg', sprintf(gettext('Falha na emissão (registro #%d, HTTP %d). Reprocesse depois.'), $id, $r['http_code']));
            }
        } catch (Exception $e) {
            $this->nfcom_model->atualizar($id, array('status' => 1, 'motivo' => substr($e->getMessage(), 0, 255)));
            $this->session->set_flashdata('flux_errormsg', gettext('Erro de comunicação com o Emissor62: ') . $e->getMessage());
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
        $this->session->set_flashdata('flux_errormsg', $msg);
        redirect(base_url() . 'cobilling/upload');
    }
}
