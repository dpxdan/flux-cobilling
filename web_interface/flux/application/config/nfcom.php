<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Configuracao da integracao Co-Billing NFCom (API Emissor62) - PX-108.
 *
 * No FluxSBC real, carregar com $this->config->load('nfcom').
 * Em producao, mover o token para /var/lib/flux/flux-config.conf, seguindo o
 * mesmo padrao usado em application/config/database.php.
 */

$config['nfcom_api_url']     = 'http://servluc01.ddns.com.br/ApiEmissor62/Nota/v1/Enviar';
$config['nfcom_api_token']   = '34c44c2138c0b5255291d61cdf97cd42538207539851893241321a2aaeecead7';
$config['nfcom_api_timeout'] = 30;

/**
 * Token de autenticacao do parceiro co-billing para acessar ESTE endpoint
 * (header X-Api-Token). Vazio = endpoint aberto (staging/integracao inicial).
 * Na integracao com o FluxSBC, alinhar ao padrao de token do API_Controller.
 */
$config['nfcom_partner_token'] = '';
