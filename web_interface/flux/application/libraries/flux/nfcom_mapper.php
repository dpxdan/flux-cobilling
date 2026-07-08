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

 // ------------------------------------------------------------------
 // Substituicao dos dados do emitente com os dados da conta Flux.
 // Consumido por Cobilling::upload_save no fluxo de co-faturamento.
 //
 // Semantica das colunas de accounts (nao inverter):
 //   accounts.city     = municipio (nome)
 //   accounts.province = UF (sigla)
 // Confirmado em sbc-fluxv6/.../accounts_model::update_account_from_doc.
 // ------------------------------------------------------------------

 /**
  * Reescreve os blocos emit/enderEmit/assinante do XML com os dados da conta
  * Flux selecionada no upload. O XML original (com assinatura valida do
  * parceiro) fica preservado no arquivo fisico em attachments/nfcom/.
  *
  * @param string      $xmlString XML original recebido do parceiro.
  * @param array       $conta     Registro de accounts (todas as colunas usadas abaixo).
  * @param string|null $codIbge   Codigo IBGE do municipio (via nfcom_model::getCodigoIbgeMunicipio).
  *                               Se null, o cMun original nao e sobrescrito.
  * @return string XML modificado (asXML). Assinatura fica invalida - aceitavel
  *                pois o Emissor62 consome o JSON convertido, nao o XML.
  * @throws Exception se o XML nao contiver os blocos esperados.
  */
 public function substituirDadosEmitente($xmlString, array $conta, $codIbge = null) {
  $xml = $this->carregar($xmlString);

  // Nome e nome fantasia
  $xNome = trim(($conta['first_name'] ?? '') . ' ' . ($conta['last_name'] ?? ''));
  $xFant = !empty($conta['company_name']) ? $conta['company_name'] : $xNome;

  // Endereco: split de address_1 em logradouro + numero
  $endereco = $this->_splitEndereco((string) ($conta['address_1'] ?? ''));

  // Sanitizacoes
  $cnpj    = preg_replace('/\D+/', '', (string) ($conta['tax_number']   ?? ''));
  $ie      = preg_replace('/\D+/', '', (string) ($conta['tax_city_number']   ?? ''));
  $cep     = preg_replace('/\D+/', '', (string) ($conta['postal_code']  ?? ''));
  $fone    = preg_replace('/\D+/', '', (string) ($conta['telephone_1']  ?? ''));

  // <emit>
  $this->_setNode($xml, '//n:emit/n:CNPJ',  $cnpj);
  $this->_setNode($xml, '//n:emit/n:IE',  $ie);
  $this->_setNode($xml, '//n:emit/n:xNome', $xNome);
  $this->_setNode($xml, '//n:emit/n:xFant', $xFant);

  // <enderEmit>
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:xLgr',    $endereco['xLgr']);
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:nro',     $endereco['nro']);
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:xCpl',    (string) ($conta['address_2'] ?? ''));
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:xBairro', (string) ($conta['address_2'] ?? ''));
  if ($codIbge !== null && $codIbge !== '') {
   $this->_setNode($xml, '//n:emit/n:enderEmit/n:cMun', (string) $codIbge);
  }
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:xMun',  (string) ($conta['city']     ?? ''));
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:CEP',   $cep);
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:UF',    (string) ($conta['province'] ?? ''));
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:fone',  $fone);
  $this->_setNode($xml, '//n:emit/n:enderEmit/n:email', (string) ($conta['email']    ?? ''));

  // <assinante>
  $this->_setNode($xml, '//n:assinante/n:iCodAssinante', (string) (int) ($conta['id'] ?? 0));
  $this->_setNode($xml, '//n:assinante/n:nContrato',     (string) ($conta['number'] ?? ''));

  $out = $xml->asXML();
  if ($out === false) throw new Exception('Falha ao serializar XML modificado');
  return $out;
 }

 /**
  * Substitui o texto de um unico elemento localizado por XPath (com namespace n).
  * Preserva atributos e nao adiciona novos filhos alem do textNode.
  *
  * @return bool true se substituido; false se o nodo nao existir.
  */
 private function _setNode(SimpleXMLElement $xml, $xpath, $value) {
  $nodes = $xml->xpath($xpath);
  if (empty($nodes)) return false;
  $dom = dom_import_simplexml($nodes[0]);
  while ($dom->hasChildNodes()) {
   $dom->removeChild($dom->firstChild);
  }
  $dom->appendChild($dom->ownerDocument->createTextNode((string) $value));
  return true;
 }

 /**
  * Separa "logradouro" e "numero" de accounts.address_1.
  * Aceita "Rua Foo, 123", "Rua Foo 123", "Av. Bar 45B" etc.
  * Fallback: nro='S/N' quando nao encontra numero final.
  *
  * @param string $address_1
  * @return array{xLgr:string,nro:string}
  */
 private function _splitEndereco($address_1) {
  $s = trim((string) $address_1);
  if ($s === '') return array('xLgr' => '', 'nro' => 'S/N');
  if (preg_match('/^(.*?)[,\s]+(\d+[A-Za-z]*)\s*$/u', $s, $m)) {
   $xLgr = trim($m[1]);
   $nro  = $m[2];
   if ($xLgr !== '' && $nro !== '') return array('xLgr' => $xLgr, 'nro' => $nro);
  }
  return array('xLgr' => $s, 'nro' => 'S/N');
 }
}
