<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
//
// Copyright (C) 2025 Flux Telecom
// Daniel Paixao <daniel@flux.net.br>
// Flux SBC Version 4.2 and above
// License https://www.gnu.org/licenses/agpl-3.0.html
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.
// ##############################################################################
if (! defined ( 'BASEPATH' ))
	exit ( 'No direct script access allowed' );
class nfcom_mapper {

protected $CI;
protected $cfg;
public function __construct() {
    $this->CI =& get_instance();
    $this->CI->load->library('flux_log');
}
 /** Namespace padrao da NFCom (portal fiscal). */
 const NS_NFCOM='http://www.portalfiscal.inf.br/nfcom';

 /**
  * Converte o XML infNFCom recebido do parceiro no payload esperado pela API Emissor62.
  *
  * @param string      $xmlString     XML da NFCom de referencia.
  * @param string|null $chaveOverride Chave de cofaturamento informada externamente (tem precedencia).
  * @return array
  */
 public function convert($xmlString,$chaveOverride=null){
  $xml=$this->carregar($xmlString);
  $inf=$xml->xpath('//n:infNFCom');
  if(empty($inf)) throw new Exception('infNFCom não encontrada');
  $inf=$inf[0];
  $itens=[];
  foreach($inf->det as $det){
    $item=['Produto'=>[
      'Codigo'=>(string)$det->prod->cProd,'Nome'=>(string)$det->prod->xProd,
      'Classificacao'=>(int)$det->prod->cClass,'CFOP'=>(int)$det->prod->CFOP,
      'UnidadeMedida'=>(int)$det->prod->uMed,'Quantidade'=>(float)$det->prod->qFaturada,
      'ValorItem'=>(float)$det->prod->vItem,'ValorDesconto'=>0,'IndDevolucao'=>0],
      'Imposto'=>['IndSemCST'=>isset($det->imposto->ICMS00)?0:1,
      'PIS'=>['CST'=>(string)$det->imposto->PIS->CST,'Aliquota'=>(float)$det->imposto->PIS->pPIS],
      'COFINS'=>['CST'=>(string)$det->imposto->COFINS->CST,'Aliquota'=>(float)$det->imposto->COFINS->pCOFINS]]];
    if(isset($det->imposto->ICMS00)) $item['Imposto']['ICMS']=['CST'=>(string)$det->imposto->ICMS00->CST,'Aliquota'=>(float)$det->imposto->ICMS00->pICMS,'AliquotaFCP'=>0,'AliquotaRed'=>0,'AliquotaUFDest'=>0,'ValorDeson'=>0,'CodBeneficio'=>''];
    $itens[]=$item;
  }
  $auts=[]; foreach($inf->autXML as $a){$auts[]=['Doc'=>(string)$a->CNPJ];}
  $payload=['Identificacao'=>['Ambiente'=>(int)$inf->ide->tpAmb,'Serie'=>(int)$inf->ide->serie,'Numero'=>(int)$inf->ide->nNF,'DHEmissao'=>(string)$inf->ide->dhEmi,'Emissao'=>(int)$inf->ide->tpEmis,'SiteAutorizador'=>(int)$inf->ide->nSiteAutoriz,'Finalidade'=>(int)$inf->ide->finNFCom,'Faturamento'=>(int)$inf->ide->tpFat,'CessaoMeiosRede'=>0],
'Destinatario'=>['Nome'=>(string)$inf->dest->xNome,'Doc'=>isset($inf->dest->CPF)?(string)$inf->dest->CPF:(string)$inf->dest->CNPJ,'DocOutro'=>'','IndIEDest'=>(int)$inf->dest->indIEDest,'IM'=>'','Endereco'=>['Logradouro'=>(string)$inf->dest->enderDest->xLgr,'Numero'=>(string)$inf->dest->enderDest->nro,'Complemento'=>(string)$inf->dest->enderDest->xCpl,'Bairro'=>(string)$inf->dest->enderDest->xBairro,'CodMunicipio'=>(string)$inf->dest->enderDest->cMun,'NomeMunicipio'=>(string)$inf->dest->enderDest->xMun,'CEP'=>(string)$inf->dest->enderDest->CEP,'UF'=>(string)$inf->dest->enderDest->UF,'CodPais'=>(string)$inf->dest->enderDest->cPais,'NomePais'=>(string)$inf->dest->enderDest->xPais,'Fone'=>(string)$inf->dest->enderDest->fone]],
'Assinante'=>['Codigo'=>(string)$inf->assinante->iCodAssinante,'Tipo'=>(int)$inf->assinante->tpAssinante,'Servico'=>(int)$inf->assinante->tpServUtil,'Contrato'=>(string)$inf->assinante->nContrato,'ContratoIni'=>(string)$inf->assinante->dContratoIni,'TerminalPrincipal'=>['Numero'=>(string)$inf->assinante->NroTermPrinc,'UF'=>(string)$inf->assinante->cUFPrinc]],'Itens'=>$itens,'Autorizacoes'=>$auts,'Informacoes'=>(string)$inf->infAdic->infCpl];
  // Chave de cofaturamento: override tem precedencia; senao extrai do XML de referencia.
  $chave=$this->resolverChave($xml,$inf,$chaveOverride);
  if($chave!=='') $payload['faturamento']=['cofaturamento'=>['chave'=>$chave]];
  return $payload;
 }

 /**
  * Resolve a chave de cofaturamento sem montar o payload completo
  * (util para registrar/validar a chave antes do envio).
  *
  * @param string      $xmlString
  * @param string|null $chaveOverride
  * @return string  Chave resolvida, ou '' se nao encontrada.
  */
 public function extrairChave($xmlString,$chaveOverride=null){
 
 $this->CI->flux_log->write_log('extrairChave', 'start');
 
  if($chaveOverride!==null && trim($chaveOverride)!=='') return trim($chaveOverride);
  $xml=$this->carregar($xmlString);
  $inf=$xml->xpath('//n:infNFCom');
  return $this->resolverChave($xml,!empty($inf)?$inf[0]:null,null);
 }

 /** Carrega o XML e registra o namespace da NFCom. */
 private function carregar($xmlString){
  libxml_use_internal_errors(true);
  $xml=simplexml_load_string($xmlString);
  if(!$xml) throw new Exception('XML inválido');
  $xml->registerXPathNamespace('n',self::NS_NFCOM);
  return $xml;
 }

 /**
  * Precedencia: override informado > protNFCom/chNFCom > atributo Id do infNFCom.
  */
 private function resolverChave($xml,$inf,$chaveOverride){
  if($chaveOverride!==null && trim($chaveOverride)!=='') return trim($chaveOverride);
  $ch=$xml->xpath('//n:protNFCom//n:chNFCom');
  if(!empty($ch)){$v=trim((string)$ch[0]); if($v!=='') return $v;}
  if($inf!==null && preg_match('/(\d{44})/',(string)$inf['Id'],$m)) return $m[1];
  return '';
 }
}
