<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library de formularios/grade da tela Co-Billing NFCom (PX-108).
 *
 * Segue o padrao do FluxSBC (refill_coupon_form, invoices_form):
 *   - build_cobilling_grid()          -> layout das colunas do flexigrid
 *   - build_grid_buttons_cobilling()  -> botoes acima da grade
 *   - get_cobilling_search_form()     -> campos do search bar
 *
 * A construcao das celulas (badges de status/origem, truncagem de chaves,
 * botoes de acao) e feita no controller em cobilling_list_json, iterando
 * manualmente sobre o result_array - padrao do modulo invoices. Isso
 * evita depender de callbacks globais em libraries/flux/common.php.
 */
class Cobilling_form {

    /** @var CI_Controller */
    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
    }

    /**
     * Layout do flexigrid. Cada linha e um array na ordem:
     * [label, width, sortname, "", "", "", "", sortable, align].
     * A coluna "Action" tem [5] como array de verbos (documental - a
     * montagem real e feita no _list_json).
     *
     * @return string JSON
     */
    public function build_cobilling_grid() {
        $acc = $this->CI->session->userdata('accountinfo');
        $is_admin = (isset($acc['type']) && (int) $acc['type'] < 1); // -1 admin, -2 superadmin

        $action_verbs = array(
            'VIEW'     => array('url' => 'cobilling/cobilling_view/',         'mode' => 'popup'),
            'RESEND'   => array('url' => 'cobilling/cobilling_reprocess/',    'mode' => 'single'),
            'DOWNLOAD' => array('url' => 'cobilling/cobilling_download_xml/', 'mode' => 'single'),
        );
        if ($is_admin) {
            $action_verbs['DELETE'] = array('url' => 'cobilling/cobilling_list_delete/', 'mode' => 'single');
        }

        return json_encode(array(
            array(gettext('Date'),                 '140', 'created_at',          '', '', '', '', 'true',  'center'),
            array(gettext('Account'),              '90',  'number',              '', '', '', '', 'true',  'center'),
            array(gettext('Customer'),             '180', 'customer',            '', '', '', '', 'true',  'left'),
            array(gettext('NFCom Number'),         '90',  'numero',              '', '', '', '', 'true',  'right'),
            array(gettext('NFCom Key'),            '300', 'chave_nfcom',         '', '', '', '', 'true',  'center'),
            array(gettext('Co-Billing Key'),       '200', 'chave_cofaturamento', '', '', '', '', 'true',  'center'),
            array(gettext('Origin'),               '80',  'origem',              '', '', '', '', 'true',  'center'),
            array(gettext('Status'),               '110', 'status',              '', '', '', '', 'true',  'center'),
            array(gettext('Situation'),            '110', 'situacao',            '', '', '', '', 'true',  'center'),
            array(gettext('Reason'),               '220', 'motivo',              '', '', '', '', 'false', 'left'),
            array(gettext('HTTP'),                 '60',  'http_code',           '', '', '', '', 'true',  'center'),
            array(gettext('Attempts'),             '60',  'tentativas',          '', '', '', '', 'true',  'center'),
            array(gettext('Action'),               '150', '',                    '', '', $action_verbs, '', 'false', 'center'),
        ));
    }

    /**
     * Botoes acima da grade: novo upload + export CSV.
     *
     * @return string JSON
     */
    public function build_grid_buttons_cobilling() {
        return json_encode(array(
            array(
                gettext('New Upload'),
                'btn btn-line-warning btn',
                'fa fa-plus-circle fa-lg',
                'button_action',
                '/cobilling/upload/',
                'single',
                '',
                'create'
            ),
            array(
                gettext('Export'),
                'btn btn-xing',
                'fa fa-upload fa-lg',
                'button_action',
                '/cobilling/cobilling_export/',
                'single',
                '',
                'export'
            )
        ));
    }

    /**
     * Search form. Segue o padrao dos modulos existentes: sufixos -string,
     * -integer e -date sao interpretados por db_model::build_search(). Status
     * e Origem ficam como INPUT (nao SELECT) porque dropdowns custom exigem
     * callback em libraries/flux/common.php, que nao alteramos aqui.
     * Placeholder do label ajuda o usuario (ex.: "0/1/2").
     *
     * @return array
     */
    public function get_cobilling_search_form() {
        $form['forms'] = array('', array('id' => 'cobilling_list_search'));

        $form[gettext('Search')] = array(
            array(
                gettext('From Date'), 'INPUT',
                array('name' => 'from_date[]', 'id' => 'cobilling_from_date', 'size' => '20', 'class' => 'text field'),
                '', '', '', 'from_date[from_date-date]'
            ),
            array(
                gettext('To Date'), 'INPUT',
                array('name' => 'to_date[]', 'id' => 'cobilling_to_date', 'size' => '20', 'class' => 'text field'),
                '', '', '', 'from_date[from_date-date]'
            ),
            array(
                gettext('Account'), 'INPUT',
                array('name' => 'number[number]', '', 'id' => 'number', 'size' => '15', 'class' => 'text field'),
                '', '', '1', 'number[number-string]', '', '', '', 'search_string_type', ''
            ),
            array(
                gettext('Customer'), 'INPUT',
                array('name' => 'customer[customer]', '', 'id' => 'customer', 'size' => '20', 'class' => 'text field'),
                '', '', '1', 'customer[customer-string]', '', '', '', 'search_string_type', ''
            ),
            array(
                gettext('NFCom Number'), 'INPUT',
                array('name' => 'numero[numero]', '', 'id' => 'numero', 'size' => '15', 'class' => 'text field'),
                '', '', '1', 'numero[numero-integer]', '', '', '', 'search_int_type', ''
            ),
            array(
                gettext('NFCom Key'), 'INPUT',
                array('name' => 'chave_nfcom[chave_nfcom]', '', 'id' => 'chave_nfcom', 'size' => '25', 'class' => 'text field'),
                '', '', '1', 'chave_nfcom[chave_nfcom-string]', '', '', '', 'search_string_type', ''
            ),
            array(
                gettext('Co-Billing Key'), 'INPUT',
                array('name' => 'chave_cofaturamento[chave_cofaturamento]', '', 'id' => 'chave_cofaturamento', 'size' => '25', 'class' => 'text field'),
                '', '', '1', 'chave_cofaturamento[chave_cofaturamento-string]', '', '', '', 'search_string_type', ''
            ),
            array(
                gettext('Status (0=Authorized / 1=Error / 2=Pending)'), 'INPUT',
                array('name' => 'status[status]', '', 'id' => 'status', 'size' => '5', 'class' => 'text field'),
                '', '', '1', 'status[status-integer]', '', '', '', 'search_int_type', ''
            ),
            array(
                gettext('Origin (api/upload)'), 'INPUT',
                array('name' => 'origem[origem]', '', 'id' => 'origem', 'size' => '10', 'class' => 'text field'),
                '', '', '1', 'origem[origem-string]', '', '', '', 'search_string_type', ''
            ),
            array('', 'HIDDEN', 'ajax_search',    '1', '', '', ''),
            array('', 'HIDDEN', 'advance_search', '1', '', '', ''),
        );

        $form['button_search'] = array(
            'name'    => 'action',
            'id'      => 'cobilling_search_btn',
            'content' => gettext('Search'),
            'value'   => 'save',
            'type'    => 'button',
            'class'   => 'btn btn-success float-right'
        );
        $form['button_reset'] = array(
            'name'    => 'action',
            'id'      => 'id_reset',
            'content' => gettext('Clear'),
            'value'   => 'cancel',
            'type'    => 'reset',
            'class'   => 'btn btn-secondary float-right mx-2'
        );
        return $form;
    }
}
