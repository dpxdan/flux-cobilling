<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
//
// Copyright (C) 2023 Flux Telecom
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
class api_emissor62 {


   private $url;
   private $token;
   private $timeout;
   
   /**
    * Cliente HTTP da API Emissor62 (envio de NFCom) - PX-108.
    *
    * URL/token/timeout vem de application/config/nfcom.php (com fallback embutido),
    * ou de parametros passados na instanciacao da library. O metodo enviar() nao
    * lanca excecao para HTTP >= 300: devolve o http_code para o controller decidir
    * e persistir o resultado. Apenas falha de transporte (cURL) gera Exception.
    */

	function __construct($params=array()){
	    // Fallback (usado quando o config do CI nao esta carregado, ex.: testes isolados).
		$url='http://servluc01.ddns.com.br/ApiEmissor62/Nota/v1/Enviar';
		$token='34c44c2138c0b5255291d61cdf97cd42538207539851893241321a2aaeecead7';
		$timeout=30;
		$this->CI = & get_instance ();
		$this->CI->load->model ( 'db_model' );
		$this->CI->load->library ('email');
		$this->CI->load->library ( 'session' );	
		$this->CI->load->library("flux_log");
		// Prioridade intermediaria: config application/config/nfcom.php.
		if(function_exists('get_instance') && ($ci=get_instance())!==null){
		  $ci->config->load('nfcom',FALSE,TRUE); // fail_gracefully: nao quebra se ausente
		  if(($v=$ci->config->item('nfcom_api_url'))!=='' && $v!==FALSE)     $url=$v;
		  if(($v=$ci->config->item('nfcom_api_token'))!=='' && $v!==FALSE)   $token=$v;
		  if(($v=$ci->config->item('nfcom_api_timeout'))!=='' && $v!==FALSE) $timeout=(int)$v;
		}
		// Prioridade maxima: parametros explicitos ao carregar a library.
		if(!empty($params['url']))     $url=$params['url'];
		if(!empty($params['token']))   $token=$params['token'];
		if(!empty($params['timeout'])) $timeout=(int)$params['timeout'];
		$this->url=$url; $this->token=$token; $this->timeout=$timeout;
	}

 /**
  * Envia o payload a API Emissor62.
  *
  * @param array $payload
  * @return array ['response' => string, 'http_code' => int]
  * @throws Exception em falha de transporte (cURL).
  */
 public function enviar(array $payload){
  $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $ch=curl_init();
  curl_setopt_array($ch,[
    CURLOPT_URL=>$this->url.'?TOKEN='.$this->token,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$json,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>$this->timeout,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Content-Length: '.strlen($json)]
  ]);
  $r=curl_exec($ch);
  if($r===false){$err=curl_error($ch); curl_close($ch); throw new Exception('Erro de comunicação com o Emissor62: '.$err);}
  $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  $logData = [
      'json'       => $json,
      'response'   => $r,
      'http_code'  => $c,
      ];
  
  $this->CI->flux_log->write_log('enviar', json_encode($logData));
  return ['response'=>$r,'http_code'=>$c];
 }
}
