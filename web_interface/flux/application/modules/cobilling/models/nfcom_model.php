<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Model de persistencia da integracao Co-Billing NFCom (PX-108).
 *
 * Registra o fluxo completo (entrada, payload, resposta, http_code) na tabela
 * nfcom_cobilling para fins de auditoria e reprocessamento. Espelha o padrao
 * de api_model::save_api_log() do FluxSBC.
 */
class nfcom_model extends CI_Model {

    protected $table = 'nfcom_cobilling';

    public function __construct() {
        parent::__construct();
    }

    /**
     * Cria o registro inicial da integracao e retorna o id gerado.
     *
     * @param array $data
     * @return int
     */
    public function criar(array $data) {
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    /**
     * Atualiza um registro existente.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function atualizar($id, array $data) {
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table, $data);
    }

    /**
     * Busca um registro pelo id.
     *
     * @param int $id
     * @return array|null
     */
    public function buscar($id) {
        $this->db->where('id', (int) $id);
        $row = $this->db->get($this->table)->row_array();
        return $row ? $row : null;
    }

    /**
     * Incrementa o contador de tentativas (usado no reprocessamento).
     *
     * @param int $id
     * @return bool
     */
    public function incrementar_tentativa($id) {
        $this->db->set('tentativas', 'tentativas+1', FALSE);
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table);
    }

    /**
     * Extrai os campos de interesse da resposta JSON da API (com defaults nulos).
     *
     * @param string $response
     * @return array
     */
    public function extrair_dados_resposta($response) {
        $out = array('sucesso'=>null, 'guid'=>null, 'chave'=>null, 'numero'=>null, 'descricao'=>null, 'mensagem'=>null, 'danfeCom'=>null);
        $j = json_decode($response, true);
        if (!is_array($j)) return $out;
        if (isset($j['sucesso'])) $out['sucesso'] = (bool) $j['sucesso'];
        $d = (isset($j['data']) && is_array($j['data'])) ? $j['data'] : array();
        foreach (array('guid','chave','numero','descricao','mensagem','danfeCom') as $k) {
            if (isset($d[$k])) $out[$k] = $d[$k];
        }
        if ($out['mensagem'] === null && isset($j['mensagem'])) $out['mensagem'] = $j['mensagem'];
        return $out;
    }

    /**
     * Persiste a resposta da API no registro e calcula o status.
     * Ponto unico usado pelo endpoint de API e pela tela de upload.
     *
     * @param int    $id
     * @param string $response
     * @param int    $http_code
     * @return array ['ok'=>bool, 'data'=>array, 'http_code'=>int]
     */
    public function registrar_resposta($id, $response, $http_code) {
        $dados = $this->extrair_dados_resposta($response);
        $ok = ($http_code >= 200 && $http_code < 300 && !empty($dados['sucesso']));
        $this->atualizar($id, array(
            'response'    => $response,
            'http_code'   => $http_code,
            'sucesso'     => ($dados['sucesso'] === null ? null : ($dados['sucesso'] ? 1 : 0)),
            'guid'        => $dados['guid'],
            'chave_nfcom' => $dados['chave'],
            'numero'      => $dados['numero'],
            'situacao'    => $dados['descricao'],
            'motivo'      => $this->truncar($dados['mensagem'], 255),
            'danfe_com'   => $dados['danfeCom'],
            'status'      => ($ok ? 0 : 1),
        ));
        return array('ok'=>$ok, 'data'=>$dados, 'http_code'=>(int) $http_code);
    }

    /**
     * Retorna a conta se ela pertencer ao tenant (anti-IDOR), senao null.
     * Revenda: filtra por reseller_id. Admin/superadmin (reseller_id=0): nao filtra.
     * O retorno traz reseller_id da conta (usado para vincular o registro).
     *
     * @param int $accountid
     * @param int $reseller_id
     * @return array|null
     */
    public function get_conta_tenant($accountid, $reseller_id) {
        $this->db->where('id', (int) $accountid);
        $this->db->where('deleted', 0);
        if ((int) $reseller_id !== 0) {
            $this->db->where('reseller_id', (int) $reseller_id);
        }
        $row = $this->db->get('accounts')->row_array();
        return $row ? $row : null;
    }

    /**
     * Lista contas de cliente (type=0, ativas) para o seletor da tela,
     * filtradas por revenda quando aplicavel.
     *
     * @param int $reseller_id
     * @return array
     */
    public function listar_contas($reseller_id) {
        $this->db->select('id, first_name, last_name, number, company_name');
        $this->db->from('accounts');
        $this->db->where('deleted', 0);
        $this->db->where('status', 0);
        $this->db->where('type', 0);
        if ((int) $reseller_id !== 0) {
            $this->db->where('reseller_id', (int) $reseller_id);
        }
        $this->db->order_by('company_name, first_name');
        return $this->db->get()->result_array();
    }

    /** Trunca strings para caber em colunas varchar (mantem null). */
    private function truncar($s, $len) {
        if ($s === null) return null;
        $s = (string) $s;
        return (strlen($s) > $len) ? substr($s, 0, $len) : $s;
    }
}
