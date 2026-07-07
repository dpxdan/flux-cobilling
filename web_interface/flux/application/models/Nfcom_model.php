<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Model de persistencia da integracao Co-Billing NFCom (PX-108).
 *
 * Registra o fluxo completo (entrada, payload, resposta, http_code) na tabela
 * nfcom_cobilling para fins de auditoria e reprocessamento. Espelha o padrao
 * de api_model::save_api_log() do FluxSBC.
 */
class Nfcom_model extends CI_Model {

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
}
