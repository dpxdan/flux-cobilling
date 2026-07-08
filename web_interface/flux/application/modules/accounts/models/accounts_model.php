<?php

// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
//
// Copyright (C) 2021 Flux Telecom
// Daniel Paixao <daniel@flux.net.br>
// FluxSBC Version 4.2 and above
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
class Accounts_model extends CI_Model {

	function __construct() {
		parent::__construct();
		$this->load->library("flux_log");

		// Carrega configuração do módulo documents a partir do system_config (MySQL)
		$sc = isset(Common_model::$global_config['system_config'])
			? Common_model::$global_config['system_config']
			: array();

		$this->documents_cfg = array(
			'mysql_cache_days' => isset($sc['doc_cache_days'])      ? (int)    $sc['doc_cache_days']      : 0,
			'cpf_provider'     => isset($sc['doc_cpf_provider'])    ? (string) $sc['doc_cpf_provider']    : 'apicpf',
			'apicpf_api_key'   => isset($sc['doc_apicpf_key'])      ? (string) $sc['doc_apicpf_key']      : '',
			'apicpf_base_url'  => isset($sc['doc_apicpf_base_url']) ? (string) $sc['doc_apicpf_base_url'] : 'https://apicpf.com',
			'apicpf_path'      => isset($sc['doc_apicpf_path'])     ? (string) $sc['doc_apicpf_path']     : '/api/consulta',
			'apicpf_timeout'   => isset($sc['doc_apicpf_timeout'])  ? (int)    $sc['doc_apicpf_timeout']  : 30,
			'cnpja_token'      => isset($sc['doc_cnpja_token'])     ? (string) $sc['doc_cnpja_token']     : '',
			'cnpja_base_url'   => isset($sc['doc_cnpja_base_url'])  ? (string) $sc['doc_cnpja_base_url']  : 'https://open.cnpja.com',
			'cnpja_cnpj_path'  => isset($sc['doc_cnpja_path'])      ? (string) $sc['doc_cnpja_path']      : '/office/{doc}?simples=true&registrations=BR',
			'open_base_url'    => isset($sc['doc_open_base_url'])   ? (string) $sc['doc_open_base_url']   : 'https://publica.cnpj.ws',
			'open_path'        => isset($sc['doc_open_path'])       ? (string) $sc['doc_open_path']       : '/cnpj/{doc}',
		);
	}

	function add_account($accountinfo) {
		$account_data               = $this->session->userdata("accountinfo");
		$accountinfo['reseller_id'] = ($account_data['type'] == 1)?$account_data['id']:($accountinfo['reseller_id'] > 0?$accountinfo['reseller_id']:0);
		unset($accountinfo['action']);
		$accountinfo['permission_id']  = ($accountinfo['type'] == 1 || $accountinfo['type'] == 2)?(isset($accountinfo['permission_id'])?$accountinfo['permission_id']:$account_data['permission_id']):0;
		$accountinfo['is_distributor'] = $account_data['type'] == 1?$account_data['is_distributor']:(isset($accountinfo['is_distributor'])?$accountinfo['is_distributor']:1);
		$this->load->library("flux/signup_lib");
		$this->signup_lib->create_account($accountinfo);
		return $last_id;
	}

	function reseller_rates_batch_update($update_array) {
		unset($update_array['action']);
		$update_array['type'] = 1;
		$date                 = gmdate("Y-m-d h:i:s");
		$this->db_model->build_search('reseller_list_search');
		if ($update_array['type'] == 1) {
			$this->db_model->build_batch_update_array($update_array);
			$login_type    = $this->session->userdata('logintype');
			$reseller_info = $this->session->userdata['accountinfo'];
			if ($reseller_info['type'] == 1) {
				$this->db->where('reseller_id', $reseller_info['id']);
			} else {
				$this->db->where('reseller_id', '0');
			}
			$this->db->where('type', '1');
			$this->db->update("accounts");
			$this->db_model->build_search('reseller_list_search');
			if (isset($update_array['balance']['balance']) && $update_array['balance']['balance'] != '') {
				$search_flag  = $this->db_model->build_search('reseller_list_search');
				$account_data = $this->session->userdata("accountinfo");
				if ($account_data['type'] == 1) {
					$where = array(
						'type'        => 1,
						"balance"     => $update_array['balance']['balance'],
						"reseller_id" => $account_data['id'],
						'deleted'     => '0',
						'status'      => '0',
					);
				} else {
					$where = array(
						'type'    => 1,
						"balance" => $update_array['balance']['balance'],
						'deleted' => '0',
						'status'  => '0',
					);
				}

				$this->db_model->build_search('reseller_list_search');
				$query_pricelist = $this->db_model->getSelect("id,reseller_id,balance", "accounts", $where);
				if ($query_pricelist->num_rows() > 0) {
					$description = '';
					if ($update_array['balance']['operator'] == '2') {
						$description .= "Reseller update set balance by admin";
					}
					if ($update_array['balance']['operator'] == '3') {
						$description .= "Reseller update increase balance by admin";
					}
					if ($update_array['balance']['operator'] == '4') {
						$description .= "Reseller update decrease balance by admin";
					}
					foreach ($query_pricelist->result_array() as $key => $reseller_payment) {
						if (!empty($reseller_payment['reseller_id']) && $reseller_payment['reseller_id'] != '') {
							$payment_by = $reseller_payment['reseller_id'];
						} else {
							$payment_by = '-1';
						}
						$insert_arr = array(
							"accountid"      => $reseller_payment['id'],
							"amount"         => $update_array['balance']['balance'],
							'tax'            => 0,
							'payment_method' => "SYSTEM",
							"date"           => $date,
							'reseller_id'    => $reseller_payment['reseller_id'],
						);
						$this->db->insert("payment_transaction", $insert_arr);
					}
				}
			}
		}

		return true;
	}

	function customer_rates_batch_update($update_array) {
		unset($update_array['action']);
		$date = gmdate("Y-m-d h:i:s");
		$this->db_model->build_search('customer_list_search');
		$reseller_info = $this->session->userdata['accountinfo'];
		if ($reseller_info['type'] == 1) {
			$this->db->where('reseller_id', $reseller_info['id']);
		}
		$this->db_model->build_search('customer_list_search');
		$this->db->where('type !=', '1');
		$this->db_model->build_batch_update_array($update_array);
		$this->db->update("accounts");
		if (isset($update_array['balance']['balance']) && $update_array['balance']['balance'] != '') {
			$account_data = $this->session->userdata("accountinfo");

			if ($account_data['type'] == 1) {
				$where = array(
					'type'        => 1,
					"reseller_id" => $account_data['id'],
					'deleted'     => '0',
					'status'      => '0',
				);
			} else {
				$where = array(
					'type !=' => '-1',
					"balance" => $update_array['balance']['balance'],
					'deleted' => '0',
					'status'  => '0',
				);
			}

			$this->db_model->build_search('customer_list_search');
			$query_pricelist = $this->db_model->getSelect("id,reseller_id,balance", "accounts", $where);
			if ($query_pricelist->num_rows() > 0) {
				$description = '';
				if ($update_array['balance']['operator'] == '2') {
					$description .= "Customer update set balance by admin";
				}
				if ($update_array['balance']['operator'] == '3') {
					$description .= "Customer update increase balance by admin";
				}
				if ($update_array['balance']['operator'] == '4') {
					$description .= "Customer update descrise balance by admin";
				}
				foreach ($query_pricelist->result_array() as $key => $customer_payment) {
					if (!empty($customer_payment['reseller_id']) && $customer_payment['reseller_id'] != '0') {
						$payment_by = $customer_payment['reseller_id'];
					} else {
						$payment_by = '-1';
					}
					$insert_arr = array(
						"accountid"      => $customer_payment['id'],
						"amount"         => $update_array['balance']['balance'],
						'tax'            => 0,
						'payment_method' => "SYSTEM",
						"date"           => $date,
						'reseller_id'    => isset($customer_payment['reseller_id'])?$customer_payment['reseller_id']:0,
					);
					$this->db->insert("payment_transaction", $insert_arr);
				}
			}
		}
		return true;
	}

	function edit_account($accountinfo, $edit_id) {
		unset($accountinfo['action']);
		unset($accountinfo['onoffswitch']);
		$accountinfo = array_map('trim', $accountinfo);
		$this->db->where('id', $edit_id);
		$result = $this->db->update('accounts', $accountinfo);
		return true;
	}

	function bulk_insert_accounts($add_array) {
		$account_data             = $this->session->userdata("accountinfo");
		$add_array['reseller_id'] = ($account_data['type'] == 1 || $account_data['type'] == 5)?$account_data['id']:0;
		$this->load->library("flux/signup_lib");

		$this->signup_lib->bulk_account_creation($add_array);
		return TRUE;
	}

	function get_max_limit($add_array) {
		$this->db->where('deleted', '0');
		$this->db->where("length(number)", $add_array['account_length']);
		$this->db->like('number', $add_array['prefix'], 'after');
		$this->db->select("count(id) as count");
		$this->db->from('accounts');
		$result           = $this->db->get();
		$result           = $result->result_array();
		$count            = $result[0]['count'];
		$remaining_length = 0;
		if (!empty($add_array['account_length'] || $add_array['prefix'])) {
			$remaining_length = $add_array['account_length']-strlen($add_array['prefix']);
		}
		$currentlength = pow(10, $remaining_length);
		$currentlength = $currentlength-$count;
		return $currentlength;
	}

	function account_process_payment($data, $update_balance_flag = 'true') {
		$data['accountid'] = $data['id'];
		$accountdata       = (array) $this->db->get_where('accounts', array(
				"id" => $data['accountid'],
			))->first_row();
		$accountinfo          = $this->session->userdata('accountinfo');
		$data["payment_by"]   = $accountdata['reseller_id'] > 0?$accountdata['reseller_id']:'-1';
		$data['payment_mode'] = $data['payment_type'];
		unset($data['action'], $data['id'], $data['account_currency'], $data['payment_type']);
		if (isset($data) && !empty($accountdata)) {

			$payment_type = ($data['payment_mode'] == 1)?'POSTCHARGE':'REFILL';

			$payment_array = array(
				"accountid"        => $accountdata['id'],
				"reseller_id"      => $accountdata['reseller_id'],
				"product_category" => 3,
				"price"            => $data['credit'],
				"payment_by"       => $accountinfo['first_name'],
				"payment_method"   => "Manual",
				"order_item_id"    => 0,
				"charge_type"      => $payment_type,
				"description"      => $data['notes'] != "" ? $data['notes'] : "Account has been ".$payment_type." by ".$accountinfo['first_name']." '(' ".$accountinfo['number']." ')' ",
				"invoice_type"     => $data['payment_mode'] == 1?"debit":"credit",
				"is_apply_tax"     => "false",
			);

			$where = array(
				'id' => $accountinfo['currency_id'],
			);
			$currency_info = (array) $this->db->get_where("currency", $where)->result_array()[0];
			$invoiceid     = $this->payment->add_payments_transcation($payment_array, $accountdata, $currency_info);
			$current_id    = $accountinfo['type'] == 1?$accountinfo['id']:'0';
		}
	}

	function get_admin_Account_list($flag, $start = 0, $limit = 0, $reseller_id = 0) {
		$accountinfo = $this->session->userdata('accountinfo');
		$this->db_model->build_search('admin_list_search');
		if ($accountinfo['type'] == -1)
			{
				$where = "reseller_id =".$reseller_id." AND deleted =0 AND type in (2,4,-1,6)";
			}else{
				$where = "reseller_id =".$reseller_id." AND deleted =0 AND type in (2,4,6)";
			}
		if ($this->session->userdata('advance_search') == 1) {
			$search = $this->session->userdata('admin_list_search');
			if ($search['type'] == '') {
				$this->db->where($where);
				$this->db_model->build_search('admin_list_search');
			} else {
				$this->db->where('type', $search['type']);
			}
		} else {
			$this->db->where($where);
			$this->db_model->build_search('admin_list_search');
		}
		if ($flag) {
			$this->db->limit($limit, $start);
		}
		if (isset($_GET['sortname']) && $_GET['sortname'] != 'undefined') {
			$this->db->order_by($_GET['sortname'], ($_GET['sortorder'] == 'undefined')?'desc':$_GET['sortorder']);
		} else {
			$this->db->order_by('number', 'desc');
		}
		$result = $this->db->get('accounts');

		if ($flag) {
			return $result;
		} else {
			return $result->num_rows();
		}
	}

	function get_customer_Account_list($flag, $start = 0, $limit = 0, $export = false) {
		$this->db_model->build_search('customer_list_search');
		$where['deleted'] = 0;
		$accountinfo      = $this->session->userdata("accountinfo");
		if ($accountinfo['type'] == 1) {
			if (empty($this->session->userdata('customer_list_search'))) {
				if ($accountinfo['type'] == 1 || $accountinfo['type'] == 5) {
					$reseller_id = $this->db_model->getSelect("GROUP_CONCAT(id) as id", "accounts", array(
							"reseller_id" => $accountinfo['id'], 'type' => 1, "deleted" => 0,
						))->first_row();
				}
				if (!empty($reseller_id)) {
					if (!empty($reseller_id->id)) {
						$accountinfo['r_id'] = $reseller_id->id.",".$accountinfo['id'];
					} else {
						$accountinfo['r_id'] = $accountinfo['id'];
					}
					$this->db->where("reseller_id IN (".$accountinfo['r_id'].")", NULL, false);
				} else {
					$where['reseller_id'] = $accountinfo['id'];
				}
			}
		}
		if ($accountinfo['type'] != -1 && $accountinfo['type'] != 2) {
			$this->db->where_not_in('reseller_id', array("0",
				));
		}
		if (($accountinfo['type'] == 1 || $accountinfo['type'] == 5) && $accountinfo['reseller_id'] != 0) {
			$this->db->where_not_in('reseller_id', array("0", $accountinfo['reseller_id'],
				));
		}

		$this->db->select('*');
		$this->db->select('reseller_id as rid');
		$this->db->where_in('type', array(
				'0',
				'3',
			));
		$this->db->where($where);
		if ($flag) {
			if (!$export) {
				$get_array      = $this->input->get();
				$sortfield_name = 'number';
				$sordorder_name = 'desc';
				if (isset($get_array['sortname'])) {
					$sortfield_name = $get_array['sortname'];
					$sordorder_name = $get_array['sortorder'];
				}
				$this->db->limit($limit, $start);
				$this->db->order_by($sortfield_name, $sordorder_name);
			}
		}
		$result = $this->db->get('accounts');
		if ($result->num_rows() > 0) {
			if ($flag) {
				return $result;
			} else {
				return $result->num_rows();
			}
		} else {

			if ($flag) {
				$result = $result;
			} else {
				$result = 0;
			}

			return $result;
		}
	}

	function get_reseller_Account_list($flag, $start = 0, $limit = 0, $export = false) {
		$this->db_model->build_search('reseller_list_search');
		$where = array(

			"deleted" => "0",
			"type"    => "1",
		);
		if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
			$where['reseller_id'] = $this->session->userdata["accountinfo"]['id'];
		}
		if ($flag) {
			$query = $this->db_model->select("*", "accounts", $where, "number", "desc", $limit, $start);
		} else {
			$query = $this->db_model->countQuery("*", "accounts", $where);
		}
		return $query;
	}

	function get_provider_Account_list($flag, $start = 0, $limit = 0) {
		$this->db_model->build_search('provider_list_search');
		$where = array(
			"deleted"     => "0",
			"type"        => "3",
			'reseller_id' => 0,
		);
		if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
			$where['reseller_id'] = $this->session->userdata["accountinfo"]['id'];
		}
		if ($flag) {
			$query = $this->db_model->select("*", "accounts", $where, "number", "desc", $limit, $start);
		} else {
			$query = $this->db_model->countQuery("*", "accounts", $where);
		}
		return $query;
	}

	function remove_customer($id, $type) {
		$this->db->where("id", $id);
		$this->db->where("type", $type);
		$data = array(
			'deleted' => '1',
		);
		$this->db->update("accounts", $data);
		return true;
	}

    function insert_block($data, $accountid, $direction = 'outbound')
    {
        $data = explode(",", $data);
        $tmp  = array();
        if (!empty($data)) {
            foreach ($data as $key => $data_value) {
                $tmp[$key]["accountid"]        = $accountid;
                $result                        = $this->get_pattern_by_id($data_value);
                $tmp[$key]["blocked_patterns"] = $result[0]['pattern'];
                $tmp[$key]["destination"]      = $result[0]['comment'];
                $tmp[$key]["direction"]        = $direction;
            }
            return $this->db->insert_batch("block_patterns", $tmp);
        }
    }

    function insert_manual_block($prefix, $destination, $direction, $accountid)
    {
        $data = array(
            "accountid"        => $accountid,
            "blocked_patterns" => '^'.$prefix.'.*',
            "destination"      => $destination,
            "direction"        => $direction,
        );
        return $this->db->insert("block_patterns", $data);
    }

	function get_pattern_by_id($pattern) {
		$patterns = $this->db_model->getSelect("pattern,comment", "routes", array(
				"id" => $pattern,
			));
		$patterns_value = $patterns->result_array();
		return $patterns_value;
	}

	function get_account_number($accountid) {
		$accountinfo = $this->session->userdata('accountinfo');
		$reseller_id = $accountinfo['type'] == 1 || $accountinfo['type'] == 5?$accountinfo['id']:0;

		$query = $this->db_model->getSelect("number", "accounts", array(
				"id"          => $accountid,
				'reseller_id' => $reseller_id,
			));
		if ($query->num_rows() > 0) {
			return $query->row_array();
		} else {
			return false;
		}

	}

	function remove_all_account_tax($account_tax) {
		$this->db->where('accountid', $account_tax);
		$this->db->delete('taxes_to_accounts');
		return true;
	}

	function add_account_tax($data) {
		$this->db->insert('taxes_to_accounts', $data);
	}

	function get_accounttax_by_id($account_id) {
		$this->db->where("accountid", trim($account_id));
		$query = $this->db->get("taxes_to_accounts");
		if ($query->num_rows() > 0) {
			return $query->result_array();
		} else {
			return false;
		}

	}

	function add_account_domain($data) {
		$this->db->insert('domains_to_accounts', $data);
	}

	function get_accountdomain_by_id($account_id) {
		$this->db->where("accountid", trim($account_id));
		$query = $this->db->get("domains_to_accounts");
		if ($query->num_rows() > 0) {
			return $query->result_array();
		} else {
			return false;
		}

	}

	function remove_all_account_domain($account_id) {
		$this->db->where('accountid', $account_id);
		$this->db->delete('domains_to_accounts');
		return true;
	}

	function edit_domain($data, $id) {
		$new_array = array(
			'domain'    => $data['domain'],
			'accountid' => $data['accountid'],
		);
		$this->db->where('accountid', $id);
		$this->db->update('domains', $new_array);
		return true;
	}

	function check_account_num($acc_num) {
		$this->db->select('accountid');
		$this->db->where("number", $acc_num);
		$query = $this->db->get("accounts");

		if ($query->num_rows() > 0) {
			return $query->row_array();
		} else {
			return false;
		}

	}

	function get_account_by_number($id) {
		$accountinfo = $this->session->userdata('accountinfo');
		$reseller_id = $accountinfo['type'] == 1 || $accountinfo['type'] == 5?$accountinfo['id']:0;
		$this->db->where('reseller_id', $reseller_id);
		$this->db->where("id", $id);
		$query = $this->db->get("accounts");

		if ($query->num_rows() > 0) {
			return $query->row_array();
		} else {
			return false;
		}

	}

	function get_currency_by_id($currency_id) {
		$query = $this->db_model->getSelect("*", 'currency', array(
				'id' => $currency_id,
			));
		if ($query->num_rows() > 0) {
			return $query->row_array();
		} else {
			return false;
		}

	}

	function update_balance($amount, $accountid, $payment_type) {
		if ($payment_type == 0) {
			$query = "update accounts set balance =  IF(posttoexternal=1,balance-".$amount.",balance+".$amount.") where id ='".$accountid."'";

			return $this->db->query($query);
		}
		if ($payment_type == 1) {
			$query = "update accounts set balance =  IF(posttoexternal=1,balance+".$amount.",balance-".$amount.") where id ='".$accountid."'";

			return $this->db->query($query);
		}
	}

	function account_authentication($where_data, $id) {
		if ($id != "") {
			$this->db->where("id <>", $id);
		}
		$this->db->where($where_data);
		$this->db->from("accounts");
		$query = $this->db->count_all_results();
		return $query;
	}

	function get_animap($flag, $start, $limit, $id) {
		$where = array(
			'accountid' => $id,
		);

		if ($flag) {
			$query = $this->db_model->select("*", "ani_map", $where, "number", "DESC", $limit, $start);
		} else {
			$query = $this->db_model->countQuery("*", "ani_map", $where);
		}
		return $query;
	}

	function add_animap($data) {
		$this->db->insert('ani_map', $data);
		return true;
	}

	function edit_animap($data, $id) {
		$new_array = array(
			'number' => $data['number'],
			'status' => $data['status'],
		);
		$this->db->where('id', $id);
		$this->db->update('ani_map', $new_array);
		return true;
	}

	function remove_ani_map($id) {
		$this->db->where('id', $id);
		$this->db->delete('ani_map');
		return true;
	}

	function animap_authentication($where_data, $id) {
		if ($id != "") {
			$this->db->where("id <>", $id);
		}
		$this->db->where($where_data);
		$this->db->from("ani_map");
		$query = $this->db->count_all_results();
		return $query;
	}

	function add_invoice_config($add_array) {
		$result = $this->db->insert('invoice_conf', $add_array);
		return true;
	}

	function edit_invoice_config($add_array, $edit_id) {
		$this->db->where('id', $edit_id);
		$result = $this->db->update('invoice_conf', $add_array);
		return true;
	}

	// ==========================================================================
	// Consulta de Documentos (CPF/CNPJ) - incorporado de Documents_model
	// ==========================================================================

	public function consultar($doc_number, $account_id)
	{
	
	   if ($account_id === null) {
	   $account_id = '0';
	   
	   }
		$doc_number = preg_replace('/\D+/', '', (string) $doc_number);
		$len = strlen($doc_number);
		$doc_type = ($len === 14) ? 'CNPJ' : null;
		if ($doc_type === null) {
			return array('ok' => false, 'error' => 'Documento invalido', 'message' => 'Documento inválido');
		}

		$cached = $this->get_cached($doc_number);
		if ($cached !== null) {
			return array(
				'ok' => true,
				'source' => 'mysql_cache',
				'data' => $cached,
			);
		}

		$provider = null;
		if ($doc_type === 'CPF') {
			$cpf_provider = isset($this->documents_cfg['cpf_provider']) ? (string) $this->documents_cfg['cpf_provider'] : 'apicpf';
			if ($cpf_provider === '' || $cpf_provider === 'null') {
				$cpf_provider = 'apicpf';
			}
			switch (strtolower($cpf_provider)) {
				case 'apicpf':
					$provider = 'apicpf';
					$api = $this->call_apicpf_cpf($doc_number);
					break;
				default:
					return array(
						'ok' => false,
						'error' => 'Provedor de CPF não configurado ou não suportado: '.$cpf_provider,
					);
			}
		}
		else {
			$provider = 'cnpja';
			$api = $this->call_cnpja_cnpj($doc_number);
//			$api2 = $this->call_open_cnpj($doc_number);
//			$provider2 = 'open';
		}

		$this->log_consulta($doc_number, $doc_type, $account_id, $provider, $api);
//		$this->log_consulta($doc_number, $doc_type, $account_id, $provider2, $api2);

		if (!$api['ok']) {
			return array('ok' => false, 'error' => $api['error'], 'message' => $api['message']);
		}

		return array(
			'ok' => true,
			'source' => 'provider',
			'data' => $api['data'],
		);
	}

	public function get_cached($doc_number)
	{
		$days = isset($this->documents_cfg['mysql_cache_days']) ? (int) $this->documents_cfg['mysql_cache_days'] : 0;
		if ($days <= 0) {
			return null;
		}

		$doc_number = preg_replace('/\D+/', '', (string) $doc_number);
		$threshold = date('Y-m-d H:i:s', strtotime('-'.$days.' days'));

		$q = $this->db
			->select('response_json')
			->from('account_document_consultations')
			->where('doc_number', $doc_number)
			->where('success', 1)
			->where('created_at >=', $threshold)
			->order_by('id', 'DESC')
			->limit(1)
			->get();

		if (!$q || $q->num_rows() === 0) {
			return null;
		}
		$row = (array) $q->row_array();
		if (!isset($row['response_json']) || $row['response_json'] === '') {
			return null;
		}

		$decoded = json_decode($row['response_json'], true);
		return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
	}

	public function get_latest_consultation($doc_number)
	{
		$doc_number = preg_replace('/\D+/', '', (string) $doc_number);
		$q = $this->db
			->select('id, doc_number, doc_type, provider, http_code, success, error_message, response_json, created_at')
			->from('account_document_consultations')
			->where('doc_number', $doc_number)
			->order_by('id', 'DESC')
			->limit(1)
			->get();

		if (!$q || $q->num_rows() === 0) {
			return null;
		}
		$row = (array) $q->row_array();
		$row['response'] = null;
		if (isset($row['response_json']) && $row['response_json'] !== '') {
			$decoded = json_decode($row['response_json'], true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$row['response'] = $decoded;
			}
		}
		return $row;
	}

	public function log_consulta($doc_number, $doc_type, $account_id, $provider, array $api_result)
	{
		$provider = ($provider !== null && $provider !== '') ? (string) $provider : 'unknown';
		$insert = array(
			'account_id'     => $account_id,
			'doc_number'     => $doc_number,
			'doc_type'       => $doc_type,
			'provider'       => $provider,
			'http_code'      => isset($api_result['http_code']) ? (int) $api_result['http_code'] : null,
			'success'        => isset($api_result['ok']) && $api_result['ok'] ? 1 : 0,
			'error_message'  => isset($api_result['error']) ? substr((string) $api_result['error'], 0, 255) : null,
			'response_json'  => isset($api_result['raw']) ? $api_result['raw'] : (isset($api_result['data']) ? json_encode($api_result['data']) : null),
		);

		try {
			$this->db->insert('account_document_consultations', $insert);
		} catch (Exception $e) {
		}
	}

	public function map_result_to_account($doc_number, $accountid, array $data)
	{
		$doc_number = preg_replace('/\D+/', '', (string) $doc_number);
		$out = array(
			'tax_number' => $doc_number,
		);

		$get = function ($arr, $path, $default = null) {
			$parts = explode('.', $path);
			$cur = $arr;
			foreach ($parts as $p) {
				if (!is_array($cur) || !array_key_exists($p, $cur)) {
					return $default;
				}
				$cur = $cur[$p];
			}
			return $cur;
		};

		$first_non_empty = function ($candidates) use ($get, $data) {
			foreach ($candidates as $path) {
				$v = $get($data, $path, null);
				if (is_string($v) && trim($v) !== '') {
					return trim($v);
				}
			}
			return null;
		};

		$company = $first_non_empty(array('company.name', 'name', 'razao_social', 'company.razao_social', 'data.nome', 'data.name', 'nome'));
		if ($company) {
			$out['company_name'] = $company;
			$out['rms_fantasia'] = $company;
		}

		$alias = $first_non_empty(array('alias', 'company.alias', 'nome_fantasia', 'company.nome_fantasia', 'trade_name'));
		if ($alias) {
			$out['reference'] = $alias;
		}

		$email = $first_non_empty(array('email', 'company.email', 'contacts.email'));
		if (!$email) {
			$emails = $get($data, 'emails', null);
			if (is_array($emails) && isset($emails[0])) {
				$email = is_array($emails[0]) ? ($emails[0]['address'] ?? null) : $emails[0];
			}
		}
		if ($email) {
			$out['email'] = $email;
			$out['notification_email'] = $email;
		}

		$registrations = $first_non_empty(array('registrations', 'company.registrations', 'contacts.registrations'));
		if (!$registrations) {
			$registrations = $get($data, 'registrations', null);
			if (is_array($registrations) && isset($registrations[0])) {
				$registrations = is_array($registrations[0]) ? ($registrations[0]['number'] ?? null) : $registrations[0];
			}
		}
		if ($registrations) {
			$out['registrations'] = $registrations;
		}

		$phone = $first_non_empty(array('phone', 'company.phone', 'contacts.phone', 'telephone'));
		if (!$phone) {
			$phones = $get($data, 'phones', null);
			if (is_array($phones) && isset($phones[0])) {
				$phone     = is_array($phones[0]) ? ($phones[0]['number'] ?? null) : $phones[0];
				$areaphone = is_array($phones[0]) ? ($phones[0]['area'] ?? null) : $phones[0];
			}
		}
		if ($phone) {
			$out['telephone_1'] = $areaphone.$phone;
		}

		$addr = $get($data, 'address', null);
		if (!is_array($addr)) {
			$addr = $get($data, 'company.address', null);
		}
		if (is_array($addr)) {
			$street  = $addr['street']   ?? ($addr['logradouro'] ?? ($addr['street_name'] ?? ''));
			$number  = $addr['number']   ?? ($addr['numero'] ?? '');
			$details = $addr['details']  ?? ($addr['complement'] ?? ($addr['complemento'] ?? ''));
			$district = $addr['district'] ?? ($addr['bairro'] ?? '');
			$city    = $addr['city']     ?? ($addr['municipality'] ?? ($addr['cidade'] ?? ''));
			$state   = $addr['state']    ?? ($addr['uf'] ?? ($addr['estado'] ?? ''));
			$zip     = $addr['zip']      ?? ($addr['zip_code'] ?? ($addr['cep'] ?? ''));

			$line1 = trim(trim($street.' '.$number).($details ? ' - '.$details : ''));
			$line2 = trim($district);

			if ($line1 !== '') { $out['address_1'] = $line1; }
			if ($line2 !== '') { $out['address_2'] = $line2; }
			if ($zip   !== '') { $out['postal_code'] = $zip; }
			if ($city  !== '') { $out['city'] = $city; }
			if ($state !== '') { $out['province'] = $state; }
		}

		return $out;
	}

	public function update_account_from_doc($account_id, array $mapped)
	{
		if (!$account_id) {
			return false;
		}
		$allowed = array(
			'tax_number',
			'company_name',
			'address_1',
			'address_2',
			'postal_code',
			'province',
			'city',
			'telephone_1',
			'email',
			'notification_email',
			'reference',
		);
		$update = array();
		foreach ($allowed as $k) {
			if (isset($mapped[$k]) && $mapped[$k] !== null && $mapped[$k] !== '') {
				$update[$k] = $mapped[$k];
			}
		}
		if (empty($update)) {
			return false;
		}

		$this->db->where('id', (int) $account_id);
		return (bool) $this->db->update('accounts', $update);
	}

	private function call_apicpf_cpf($cpf)
	{
		$key     = isset($this->documents_cfg['apicpf_api_key'])  ? (string) $this->documents_cfg['apicpf_api_key']  : 'f5d385f4b3bd94623bd20373c8bd2103a7d00fc2b5c9618d31664b47a20dbe23';
		$base    = isset($this->documents_cfg['apicpf_base_url']) ? rtrim((string) $this->documents_cfg['apicpf_base_url'], '/') : 'https://apicpf.com';
		$path    = isset($this->documents_cfg['apicpf_path'])     ? (string) $this->documents_cfg['apicpf_path']     : '/api/consulta';
		$timeout = isset($this->documents_cfg['apicpf_timeout'])  ? (int) $this->documents_cfg['apicpf_timeout']     : 30;

		if ($key === '' || $key === 'CHANGE_ME') {
			return array(
				'ok'        => false,
				'http_code' => 0,
				'error'     => 'API key do provedor de CPF (apicpf.com) não configurada. Ajuste em accounts/config/documents.php',
			);
		}

		$query_params = array('cpf' => $cpf);
		if (isset($this->documents_cfg['apicpf_query_params']) && is_array($this->documents_cfg['apicpf_query_params'])) {
			$query_params = array_merge($query_params, $this->documents_cfg['apicpf_query_params']);
		}

		$url  = $base.$path;
		$url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($query_params);

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'GET',
			CURLOPT_HTTPHEADER     => array(
				'Accept: application/json',
				'X-API-KEY: '.$key,
			),
		));

		$body      = curl_exec($ch);
		$curl_err  = curl_error($ch);
		$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($body === false) {
			return array(
				'ok'        => false,
				'http_code' => $http_code,
				'error'     => 'Falha no cURL: '.$curl_err,
			);
		}

		$decoded = json_decode($body, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return array(
				'ok'        => false,
				'http_code' => $http_code,
				'raw'       => $body,
				'error'     => 'Resposta não é JSON válido: '.json_last_error_msg(),
			);
		}

		$api_code = isset($decoded['code'])    ? (int)    $decoded['code']    : null;
		$message  = isset($decoded['message']) ? (string) $decoded['message'] : null;

		if ($http_code < 200 || $http_code >= 300 || ($api_code !== null && $api_code !== 200)) {
			$err = 'API retornou HTTP '.$http_code;
			$this->flux_log->write_log('message', json_encode($message));
			if ($api_code !== null && $api_code !== 200) {
				$err = 'API retornou code '.$api_code;
			}
			if ($message) {
				$err .= ' - '.$message;
			}
			return array(
				'ok'        => false,
				'http_code' => $http_code,
				'raw'       => $body,
				'message'   => $message,
				'error'     => $err,
				'data'      => $decoded,
			);
		}

		return array(
			'ok'        => true,
			'http_code' => $http_code,
			'raw'       => $body,
			'data'      => $decoded,
		);
	}

	private function call_cnpja_cnpj($cnpj)
	{
		$token = isset($this->documents_cfg['cnpja_token'])    ? (string) $this->documents_cfg['cnpja_token']    : '1cb69254-77f7-40cd-bb35-4b3bb93b764e-1ba5147d-c935-4b7d-9ea5-b5bd4c5616c3';
		$base  = isset($this->documents_cfg['cnpja_base_url']) ? rtrim((string) $this->documents_cfg['cnpja_base_url'], '/') : 'https://open.cnpja.com';
		$path  = isset($this->documents_cfg['cnpja_cnpj_path']) ? (string) $this->documents_cfg['cnpja_cnpj_path'] : '/office/{doc}?simples=true&registrations=BR';
		$path  = str_replace('{doc}', $cnpj, $path);

		if ($token === '' || $token === 'CHANGE_ME') {
			return array(
				'ok'        => false,
				'http_code' => 0,
				'error'     => 'Token da API CNPJá não configurado. Ajuste em accounts/config/documents.php',
			);
		}

		$query_params = isset($this->documents_cfg['cnpja_query_params']) && is_array($this->documents_cfg['cnpja_query_params']) ? $this->documents_cfg['cnpja_query_params'] : array();
		$url = $base.$path;
		if (!empty($query_params)) {
			$url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($query_params);
		}
		$this->flux_log->write_log('url', json_encode($url));

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'GET',
			CURLOPT_HTTPHEADER     => array(
				'Accept: application/json',
				'Authorization: '.$token,
			),
		));

		$body      = curl_exec($ch);
		$curl_err  = curl_error($ch);
		$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($body === false) {
			return array(
				'ok'        => false,
				'http_code' => $http_code,
				'error'     => 'Falha no cURL: '.$curl_err,
			);
		}

		$decoded = json_decode($body, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return array(
				'ok'        => false,
				'http_code' => $http_code,
				'raw'       => $body,
				'error'     => 'Resposta não é JSON válido: '.json_last_error_msg(),
			);
		}

		if ($http_code < 200 || $http_code >= 300) {
			$err = 'API retornou HTTP '.$http_code;
			if (isset($decoded['message'])) {
				$err .= ' - '.$decoded['message'];
			}
			return array(
				'ok'        => false,
				'http_code' => $http_code,
				'message'   => $decoded['message'],
				'raw'       => $body,
				'error'     => $err,
				'data'      => $decoded,
			);
		}

		return array(
			'ok'        => true,
			'http_code' => $http_code,
			'raw'       => $body,
			'data'      => $decoded,
		);
	}
	
	private function call_open_cnpj($cnpj)
	{

			$base  = isset($this->documents_cfg['doc_open_base_url']) ? rtrim((string) $this->documents_cfg['doc_open_base_url'], '/') : 'https://publica.cnpj.ws';
			$path  = isset($this->documents_cfg['doc_open_path']) ? (string) $this->documents_cfg['doc_open_path'] : '/cnpj/{doc}';
			$path  = str_replace('{doc}', $cnpj, $path);
						
			$url = $base.$path;

			$this->flux_log->write_log('url', json_encode($url));
	
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'GET',
				CURLOPT_HTTPHEADER     => array(
					'Accept: application/json',
				),
			));
	
			$body      = curl_exec($ch);
			$curl_err  = curl_error($ch);
			$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
	
			if ($body === false) {
				return array(
					'ok'        => false,
					'http_code' => $http_code,
					'error'     => 'Falha no cURL: '.$curl_err,
				);
			}
	
			$decoded = json_decode($body, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				return array(
					'ok'        => false,
					'http_code' => $http_code,
					'raw'       => $body,
					'error'     => 'Resposta não é JSON válido: '.json_last_error_msg(),
				);
			}
	
			if ($http_code < 200 || $http_code >= 300) {
				$err = 'API retornou HTTP '.$http_code;
				if (isset($decoded['message'])) {
					$err .= ' - '.$decoded['message'];
				}
				return array(
					'ok'        => false,
					'http_code' => $http_code,
					'message'   => $decoded['message'],
					'raw'       => $body,
					'error'     => $err,
					'data'      => $decoded,
				);
			}
	
			return array(
				'ok'        => true,
				'http_code' => $http_code,
				'raw'       => $body,
				'data'      => $decoded,
			);
		}
}
