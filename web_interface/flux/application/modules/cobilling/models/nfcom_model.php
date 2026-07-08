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
    
    // ------------------------------------------------------------------
    // Listagem (view_nfcom_cobilling) - consumida por Cobilling::cobilling_list_json.
    // ------------------------------------------------------------------
    
    /**
     * Consulta paginada/contagem da view view_nfcom_cobilling.
     * Escopo anti-IDOR: revenda (type==1) so ve os proprios registros.
     * Filtros do search bar sao aplicados por db_model::build_search().
     *
     * @param bool $flag   true = SELECT paginado; false = COUNT total
     * @param int  $start
     * @param int  $limit
     * @return mixed CI DB result (num_rows para count; result_array para select)
     */
    public function get_cobilling_list($flag, $start = 0, $limit = 0) {
        $CI = get_instance();
        $CI->db_model->build_search('cobilling_list_search');
        $acc = $CI->session->userdata('accountinfo');
        $where = array();
        if (isset($acc['type']) && (int) $acc['type'] === 1) {
            $where['reseller_id'] = (int) $acc['id'];
        }
        if ($flag) {
            return $CI->db_model->select('*', 'view_nfcom_cobilling', $where, 'created_at', 'DESC', $limit, $start);
        }
        return $CI->db_model->countQuery('*', 'view_nfcom_cobilling', $where);
    }
    
    /**
     * Reprocessa um registro: reconverte o payload se necessario, reenvia ao
     * Emissor62 e persiste o resultado. Ponto unico usado pela tela de
     * listagem (Cobilling::cobilling_reprocess) e reutilizavel pela API.
     *
     * @param int $id
     * @return array ['ok'=>bool, 'http_code'=>int, 'data'=>array|null, 'error'=>string|null, 'id'=>int]
     */
    public function reprocessar_registro($id) {
        $id = (int) $id;
        $reg = $this->buscar($id);
        if ($reg === null) {
            return array('ok'=>false, 'http_code'=>404, 'data'=>null, 'error'=>'not_found', 'id'=>$id);
        }
        $CI = get_instance();
    
        $payload = (!empty($reg['payload_enviado'])) ? json_decode($reg['payload_enviado'], true) : null;
        if (!is_array($payload)) {
            if (empty($reg['xml_recebido'])) {
                return array('ok'=>false, 'http_code'=>422, 'data'=>null, 'error'=>'sem_payload_ou_xml', 'id'=>$id);
            }
            try {
                $payload = $CI->nfcom_mapper->convert($reg['xml_recebido'], $reg['chave_cofaturamento']);
            } catch (Exception $e) {
                $this->atualizar($id, array('status'=>1, 'motivo'=>$this->truncar($e->getMessage(), 255)));
                return array('ok'=>false, 'http_code'=>422, 'data'=>null, 'error'=>$e->getMessage(), 'id'=>$id);
            }
            $this->atualizar($id, array('payload_enviado' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        }
    
        $this->incrementar_tentativa($id);
    
        try {
            $r = $CI->api_emissor62->enviar($payload);
        } catch (Exception $e) {
            $this->atualizar($id, array('status'=>1, 'motivo'=>$this->truncar($e->getMessage(), 255)));
            return array('ok'=>false, 'http_code'=>502, 'data'=>null, 'error'=>$e->getMessage(), 'id'=>$id);
        }
    
        $res = $this->registrar_resposta($id, $r['response'], $r['http_code']);
        return array(
            'ok'        => (bool) $res['ok'],
            'http_code' => (int) $r['http_code'],
            'data'      => $res['data'],
            'error'     => null,
            'id'        => $id,
        );
    }
    
    /**
     * Hard delete restrito ao tenant. Revenda so pode remover seus registros;
     * admin/superadmin (reseller_id=0) remove qualquer um.
     * Nao remove o arquivo fisico em attachments/nfcom/ (ver Notas do plano).
     *
     * @param int $id
     * @param int $reseller_id
     * @return bool
     */
    public function excluir($id, $reseller_id) {
        $this->db->where('id', (int) $id);
        if ((int) $reseller_id !== 0) {
            $this->db->where('reseller_id', (int) $reseller_id);
        }
        $this->db->delete($this->table);
        return ($this->db->affected_rows() > 0);
    }
    
    public function get_nfcom_cobilling_list1($flag = false, $start = 0, $limit = 0, $reseller_id = 0)
    {
        $this->db->select('id, accountid, chave_cofaturamento, chave_nfcom, numero, http_code, status, tentativas, origem, created_at, motivo');
        $this->db->from('nfcom_cobilling');
    
        if ((int)$reseller_id > 0) {
            $this->db->where('reseller_id', (int)$reseller_id);
        }
    
        $accountid = $this->input->get_post('accountid', true);
        $status = $this->input->get_post('status', true);
        $chave = $this->input->get_post('chave', true);
    
        if ($accountid !== '' && $accountid !== null) {
            $this->db->where('accountid', (int)$accountid);
        }
    
        if ($status !== '' && $status !== null) {
            $this->db->where('status', (int)$status);
        }
    
        if ($chave !== '' && $chave !== null) {
            $this->db->group_start();
            $this->db->like('chave_cofaturamento', $chave);
            $this->db->or_like('chave_nfcom', $chave);
            $this->db->group_end();
        }
    
        $this->db->order_by('id', 'DESC');
    
        if ($flag === true) {
            $this->db->limit($limit, $start);
            return $this->db->get()->result_array();
        }
    
        return $this->db->count_all_results();
    }
    
    public function get_nfcom_cobilling_by_id($id, $reseller_id = 0)
    {
        $this->db->from('nfcom_cobilling');
        $this->db->where('id', (int)$id);

        if ((int)$reseller_id > 0) {
            $this->db->where('reseller_id', (int)$reseller_id);
        }

        return $this->db->get()->row_array();
    }

    /**
     * Retorna o codigo IBGE do municipio consultando a tabela municipios
     * (mantida pelo usuario) - usado por Cobilling::upload_save para preencher
     * <cMun> no bloco enderEmit do XML modificado.
     *
     * Convencao (accounts): city=municipio, province=UF - portanto o nome do
     * municipio vem de accounts.city.
     *
     * Estrategia defensiva de schema:
     *   - Tenta filtrar por UF (colunas 'uf' ou 'sigla_uf') se informada.
     *   - Se a coluna nao existir (schema variante), cai para busca somente pelo nome.
     *   - Case/accent insensitivity depende do collation da tabela.
     *
     * @param string      $nome Nome do municipio (accounts.city).
     * @param string|null $uf   Sigla UF (accounts.province) para desambiguar homonimos.
     * @return string|null Codigo IBGE (string) ou null se nao encontrado.
     */
    public function getCodigoIbgeMunicipio($nome, $uf = null) {
        $nome = trim((string) $nome);
        if ($nome === '') return null;

        // Tentativa 1: com filtro de UF (se informada e se a coluna existir).
        if ($uf !== null && trim((string) $uf) !== '') {
            $uf = strtoupper(trim((string) $uf));
            $sql = "SELECT codigo_ibge FROM municipios "
                 . "WHERE nome = ? AND (uf = ? OR sigla_uf = ?) LIMIT 1";
            $q = @$this->db->query($sql, array($nome, $uf, $uf));
            if ($q !== false && $q->num_rows() > 0) {
                $row = $q->row_array();
                if (!empty($row['codigo_ibge'])) return (string) $row['codigo_ibge'];
            }
        }

        // Tentativa 2: somente pelo nome.
        $q = @$this->db->query(
            "SELECT codigo_ibge FROM municipios WHERE nome = ? LIMIT 1",
            array($nome)
        );
        if ($q !== false && $q->num_rows() > 0) {
            $row = $q->row_array();
            if (!empty($row['codigo_ibge'])) return (string) $row['codigo_ibge'];
        }
        return null;
    }
}
