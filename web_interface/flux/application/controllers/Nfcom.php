<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Controller da integracao Co-Billing NFCom (PX-108).
 *
 * Recebe a NFCom de referencia (XML) de um sistema de co-billing, converte para
 * o layout da API Emissor62, envia a nota e persiste todo o fluxo em MySQL
 * (tabela nfcom_cobilling) para auditoria e reprocessamento.
 *
 * Endpoints:
 *   POST nfcom/enviar            -> emite a nota a partir do XML no corpo da requisicao
 *   POST nfcom/reprocessar/{id}  -> reenvia um registro previamente gravado
 *
 * A chave de cofaturamento e extraida do XML (protNFCom/chNFCom) e pode ser
 * sobrescrita por ?chave=... ou pelo header X-Cofaturamento-Chave.
 */
class Nfcom extends CI_Controller{

 public function __construct(){
  parent::__construct();
  $this->load->config('nfcom',FALSE,TRUE); // fail_gracefully: nao quebra se ausente
  $this->load->model('Nfcom_model','nfcom_model');
  $this->load->library('flux/NFComMapper');
  $this->load->library('flux/ApiEmissor62');
 }

 /**
  * Recebe o XML da NFCom, converte, envia ao Emissor62 e grava o resultado.
  */
 public function enviar(){
  if(!$this->autenticar()) return;

  $xml=file_get_contents('php://input');
  if(trim($xml)===''){ $this->responder(['status'=>false,'error'=>'XML não informado.'],400); return; }

  $chaveOverride=$this->chaveOverride();

  // Registro inicial (status 2 = pendente) garante rastreabilidade mesmo se algo falhar adiante.
  try{ $chaveRef=$this->NFComMapper->extrairChave($xml,$chaveOverride); }
  catch(Exception $e){ $chaveRef=($chaveOverride!==null?$chaveOverride:''); }
  $id=$this->nfcom_model->criar([
    'chave_cofaturamento'=>($chaveRef!==''?$chaveRef:null),
    'xml_recebido'=>$xml,
    'status'=>2,
  ]);

  try{
    $payload=$this->NFComMapper->convert($xml,$chaveOverride);
  }catch(Exception $e){
    $this->nfcom_model->atualizar($id,['status'=>1,'motivo'=>$this->truncar($e->getMessage(),255)]);
    $this->responder(['status'=>false,'id'=>$id,'error'=>$e->getMessage()],422); return;
  }
  $this->nfcom_model->atualizar($id,['payload_enviado'=>$this->json($payload)]);

  $this->processarEnvio($id,$payload,201);
 }

 /**
  * Reenvia um registro ja gravado (reprocessamento de falhas).
  */
 public function reprocessar($id){
  if(!$this->autenticar()) return;

  $registro=$this->nfcom_model->buscar($id);
  if($registro===null){ $this->responder(['status'=>false,'error'=>'Registro não encontrado.'],404); return; }

  // Reaproveita o payload ja montado; se ausente, reconverte a partir do XML original.
  $payload=(!empty($registro['payload_enviado']))?json_decode($registro['payload_enviado'],true):null;
  if(!is_array($payload)){
    if(empty($registro['xml_recebido'])){ $this->responder(['status'=>false,'id'=>(int)$id,'error'=>'Sem payload/XML para reprocessar.'],422); return; }
    try{ $payload=$this->NFComMapper->convert($registro['xml_recebido'],$registro['chave_cofaturamento']); }
    catch(Exception $e){
      $this->nfcom_model->atualizar($id,['status'=>1,'motivo'=>$this->truncar($e->getMessage(),255)]);
      $this->responder(['status'=>false,'id'=>(int)$id,'error'=>$e->getMessage()],422); return;
    }
    $this->nfcom_model->atualizar($id,['payload_enviado'=>$this->json($payload)]);
  }

  $this->nfcom_model->incrementar_tentativa($id);
  $this->processarEnvio((int)$id,$payload,200);
 }

 // --- helpers ---

 /**
  * Envia o payload, persiste resposta/http_code e devolve o resultado ao cliente.
  */
 private function processarEnvio($id,array $payload,$httpSucesso){
  try{
    $r=$this->apiemissor62->enviar($payload);
  }catch(Exception $e){
    // Falha de transporte (cURL): grava e retorna 502.
    $this->nfcom_model->atualizar($id,['status'=>1,'motivo'=>$this->truncar($e->getMessage(),255)]);
    $this->responder(['status'=>false,'id'=>$id,'error'=>$e->getMessage()],502); return;
  }

  $dados=$this->extrairDadosResposta($r['response']);
  $ok=($r['http_code']>=200 && $r['http_code']<300 && !empty($dados['sucesso']));

  $this->nfcom_model->atualizar($id,[
    'response'=>$r['response'],
    'http_code'=>$r['http_code'],
    'sucesso'=>($dados['sucesso']===null?null:($dados['sucesso']?1:0)),
    'guid'=>$dados['guid'],
    'chave_nfcom'=>$dados['chave'],
    'numero'=>$dados['numero'],
    'situacao'=>$dados['descricao'],
    'motivo'=>$this->truncar($dados['mensagem'],255),
    'danfe_com'=>$dados['danfeCom'],
    'status'=>($ok?0:1),
  ]);

  $http=$ok?$httpSucesso:($r['http_code']>=400?$r['http_code']:502);
  $this->responder([
    'status'=>$ok,
    'id'=>$id,
    'http_code'=>$r['http_code'],
    'data'=>$dados,
    'response'=>$this->jsonOuTexto($r['response']),
  ],$http);
 }

 /**
  * Extrai os campos de interesse da resposta JSON da API (com defaults nulos).
  */
 private function extrairDadosResposta($response){
  $out=['sucesso'=>null,'guid'=>null,'chave'=>null,'numero'=>null,'descricao'=>null,'mensagem'=>null,'danfeCom'=>null];
  $j=json_decode($response,true);
  if(!is_array($j)) return $out;
  if(isset($j['sucesso'])) $out['sucesso']=(bool)$j['sucesso'];
  $d=(isset($j['data']) && is_array($j['data']))?$j['data']:[];
  foreach(['guid','chave','numero','descricao','mensagem','danfeCom'] as $k){
    if(isset($d[$k])) $out[$k]=$d[$k];
  }
  // A mensagem de topo tambem e util quando nao ha data.mensagem.
  if($out['mensagem']===null && isset($j['mensagem'])) $out['mensagem']=$j['mensagem'];
  return $out;
 }

 /** Chave de cofaturamento externa: query string ?chave= ou header X-Cofaturamento-Chave. */
 private function chaveOverride(){
  $q=$this->input->get('chave');
  if($q!==null && $q!==FALSE && trim($q)!=='') return trim($q);
  $h=$this->input->get_request_header('X-Cofaturamento-Chave',TRUE);
  if(!empty($h) && trim($h)!=='') return trim($h);
  return null;
 }

 /**
  * Autenticacao opcional por token do parceiro (header X-Api-Token).
  * So e exigida quando nfcom_partner_token estiver definido no config.
  */
 private function autenticar(){
  $esperado=$this->config->item('nfcom_partner_token');
  if(empty($esperado)) return true; // sem token configurado: endpoint aberto (staging/integracao)
  $recebido=$this->input->get_request_header('X-Api-Token',TRUE);
  if(!empty($recebido) && hash_equals((string)$esperado,(string)$recebido)) return true;
  $this->responder(['status'=>false,'error'=>'Token inválido.'],401);
  return false;
 }

 /** Resposta JSON padronizada. No FluxSBC real, substituir por $this->response() do API_Controller. */
 private function responder($corpo,$http=200){
  $this->output->set_status_header($http);
  $this->output->set_content_type('application/json','utf-8');
  $this->output->set_output($this->json($corpo));
 }

 /** Decodifica a resposta como JSON quando possivel; senao devolve o texto cru. */
 private function jsonOuTexto($response){
  $j=json_decode($response,true);
  return (json_last_error()===JSON_ERROR_NONE)?$j:$response;
 }

 private function json($v){
  return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 }

 private function truncar($s,$len){
  if($s===null) return null;
  $s=(string)$s;
  return (strlen($s)>$len)?substr($s,0,$len):$s;
 }
}
