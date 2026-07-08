<?php
// $style = "class='tooltip_text'"; 
$tooltip_data = array(
	// ------------------------ACCOUNT MODULE START-------------------------
	/*CUSTOMER EDIT SECTION START*/
	//PANEL ACCESS SECTION START FOR CUSTOMER
	"customer_form_reseller_id" => "O revendedor é simplesmente o pai deste cliente específico. Se a conta precisa ser criada para o administrador, selecione a opção administrador. Se a conta for adicionada a um revendedor, selecione a conta do revendedor para que o perfil do cliente seja adicionado ao revendedor selecionado.",

	"customer_form_number" => "O número da conta é uma string de identificador único de 10 dígitos. Pode ser o número de telefone ou uma string gerada aleatoriamente ou uma string única personalizada.",

	"customer_form_password" => "A senha que deve ser fornecida ao cliente para que ele/ela possa fazer login no portal.",

	"customer_form_pin" => "PIN do cartão de chamadas. Importante se o cliente estiver usando o recurso de cartão de chamadas. O comprimento do PIN é configurável e o administrador pode alterá-lo na configuração do cartão de chamadas.",

	"customer_form_email" => "Endereço de e-mail dos usuários que também é utilizado para login.",

	"customer_form_permission_id" => "Atribuição de acessibilidade.",

	"customer_form_first_used" => "A data e hora em que a conta foi usada pela primeira vez.",

	"customer_form_validfordays" => "Os dias de validade da conta antes de ela se tornar inativa.",

	"customer_form_expiry" => "A data em que a conta expirou/inativou.",

	"customer_form_status" => "Estado atual da conta.",

	"customer_form_sip_device_flag" => "Sim - Ao selecionar a caixa de verificação, o dispositivo SIP será criado automaticamente para a nova conta do usuário. Não - Se a opção 'Não' for selecionada, o sistema não criará nenhum dispositivo SIP para a conta.",
	//PANEL ACCESS SECTION END FOR CUSTOMER

	// ACCOUNT ACCESS SECTION START FOR CUSTOMER
	"customer_form_maxchannels" => "Define o número de chamadas simultâneas permitidas para o cliente em questão.",

	"customer_form_cps" => "Gerencia as chamadas dentro do limite de chamadas por segundo.", 

	"customer_form_localization_id" => "Gerencia a tradução de números para o ID do chamador e o número de destino.",

	"customer_form_local_call" => "Permite ou não que os clientes façam chamadas locais com base na seleção.",

	"customer_form_charge_per_min" => "Cobrança por minuto para chamadas locais/On-net/Sip2Sip.",

	"customer_form_loss_less_routing" => "Permite chamadas se a tarifa de origem for menor que as tarifas de terminação.",

	"customer_form_is_recording" => "Permite gravação de chamadas.",

	"customer_form_allow_ip_management" => "Permitir ou não a gestão de IPs.",

	"customer_form_notifications" => "Permitir o envio de notificações ao cliente.",

	"customer_form_paypal_permission" => "Ao definir a opção 'Sim', o cliente terá acesso para fazer pagamentos usando o gateway de pagamento disponível. Ao definir a opção 'Não', o cliente não poderá fazer nenhum tipo de pagamento.",
	// ACCOUNT ACCESS SECTION END FOR CUSTOMER

	//PROFILE SECTION SECTION START FOR CUSTOMER
	"customer_form_first_name" => "Primeiro nome do cliente.",

	"customer_form_last_name" => "Último nome do cliente.",

	"customer_form_company_name" => "Nome da empresa do cliente.",

	"customer_form_telephone_1" => "Número de telefone do cliente.",

	"customer_form_notification_email" => "Os clientes podem definir o e-mail para notificações aqui.",	

	"customer_form_address_1" => "Endereço do cliente.",

	"customer_form_address_2" => "Outro endereço do cliente (se houver).",

	"customer_form_city" => "Nome da cidade do cliente.",

	"customer_form_province" => "Nome da província do cliente.",

	"customer_form_postal_code" => "Código postal do cliente.",

	"customer_form_country_id" => "País do cliente.",

	"customer_form_timezone_id" => "Fuso horário do cliente. NOTA: O FluxSBC não suporta o horário de verão por padrão. Será necessário alterar manualmente o fuso horário da conta para lidar com isso.",

	"customer_form_currency_id" => "Definir a moeda para as novas contas.",
	//PROFILE SECTION SECTION END FOR CUSTOMER

	//BILLING SECTION SECTION START FOR CUSTOMER
	"customer_form_posttoexternal" => "Selecione o tipo de conta do cliente. Pré-pago ou pós-pago. Para clientes pré-pagos, o sistema gerará recibos assim que quaisquer cobranças forem aplicadas. Para clientes pós-pagos, o sistema gerará uma fatura no dia de cobrança definido.",

	"customer_form_credit_limit" => "Limite de crédito da conta do cliente. O limite de crédito é usado apenas para contas pós-pagas.",

	"customer_form_pricelist_id" => "O grupo de tarifas é um campo essencial para a cobrança. Sem um grupo de tarifas, o cliente não poderá fazer chamadas. Você pode criar um grupo de tarifas navegando até Tarifa -> Grupo de tarifas.",

	"customer_form_non_cli_pricelist_id" => "Grupo de tarifas selecionado com base nas opções do pool CLI.",

	"customer_form_cli_pool" => "Selecionar o grupo de tarifas ou o grupo de tarifas NON-CLI com base no número do ID do chamador.",

	"customer_form_sweep_id" => "Agenda de faturamento para a geração de faturas.",

	"customer_form_invoice_day" => "Se a programação de faturamento for mensal, ou semanal, você poderá definir o dia em que a fatura do cliente deve ser gerada.",

	"customer_form_tax_number" => "Exibir o número de imposto nas faturas.",

	"customer_form_generate_invoice" => "Permitir a geração de faturas com valor zero.",

	"customer_form_invoice_note" => "Exibirá uma nota na fatura ao gerar faturas.",

	"customer_form_reference" => "Definir a referência para o cliente.",

	"customer_bulk_form_count" => "Quantas contas você deseja gerar.",

	"customer_bulk_form_prefix" => "Definir o prefixo de onde o número da conta deve começar.",

	"customer_bulk_form_account_length" => "Definir o número de caracteres para o número da conta.",

	"customer_bulk_form_pin" => "Se você deseja gerar um cliente de cartão de chamadas, defina a opção 'Sim' para que seja gerado um número PIN para todas as contas.",

	"customer_bulk_form_validfordays" => "Dias de validade para a conta do cliente.",

	"customer_bulk_form_currency_id" => "Definir a moeda para as novas contas.",

	"customer_bulk_form_country_id" => "Definir o país para as novas contas.",

	"customer_bulk_form_timezone_id" => "Definir o fuso horário correto para a nova conta. NOTA: O FluxSBC não suporta horário de verão por padrão. Será necessário alterar manualmente o fuso horário da conta para lidar com isso.",

	"customer_bulk_form_posttoexternal" => "Definir o tipo de conta (Pré-pago/Pós-pago).",

	"customer_bulk_form_balance" => "Definir o saldo inicial, se desejar oferecer na criação da conta.",

	"customer_bulk_form_credit_limit" => "Para clientes pós-pagos, você pode definir o limite de crédito.",

	"customer_bulk_form_cli_pool" => "Selecionar o grupo de tarifas ou o grupo de tarifas NON-CLI com base no número do ID do chamador.",

	"customer_bulk_form_pricelist_id" => "O grupo de tarifas é um campo essencial para a cobrança. Sem um grupo de tarifas, o cliente não poderá fazer chamadas. Você pode criar um grupo de tarifas navegando até Tarifa -> Grupo de tarifas.",

	"customer_bulk_form_non_cli_pricelist_id" => "Grupo de tarifas selecionado com base nas opções do pool CLI.",

	"customer_form_tax_id" => "Impostos aplicáveis na fatura.",

	"customer_bulk_form_sweep_id" => "Agenda de faturamento para a geração de faturas.",

	"reseller_form_reseller_id" => "O revendedor é simplesmente o pai deste cliente específico. Se a conta precisa ser criada para o administrador, selecione a opção administrador. Se a conta for adicionada a um revendedor, selecione a conta do revendedor para que o perfil do cliente seja adicionado ao revendedor selecionado.",

	"reseller_form_number" => "O número da conta é uma string de identificador único de 10 dígitos. Pode ser o número de telefone ou uma string gerada aleatoriamente ou uma string única personalizada.",

	"reseller_form_password" => "A senha que deve ser fornecida ao cliente para que ele/ela possa fazer login no portal.",

	"reseller_form_pin" => "PIN do cartão de chamadas. Importante se o cliente estiver usando o recurso de cartão de chamadas. O comprimento do PIN é configurável e o administrador pode alterá-lo na configuração do cartão de chamadas.",

	"reseller_form_email" => "Endereço de e-mail dos usuários que também é utilizado para login.",

	"reseller_form_permission_id" => "Atribuição de acessibilidade.",

	"reseller_form_is_distributor" => "Tipo de revendedor.",

	//Account Settings For Reseller Start
	"reseller_form_maxchannels" => "Define o número de chamadas simultâneas permitidas para o cliente em questão.",

	"reseller_form_cps" => "Gerencia as chamadas dentro do limite de chamadas por segundo.",

	"reseller_form_notifications" => "Permitir o envio de notificações ao cliente.",
	//Account Settings For Reseller End

	//PROFILE SECTION SECTION START FOR RESELLER
	"reseller_form_first_name" => "Primeiro nome do revendedor.",

	"reseller_form_last_name" => "Último nome do revendedor.",

	"reseller_form_company_name" => "Nome da empresa do revendedor.",

	"reseller_form_telephone_1" => "Número de telefone do revendedor.",

	"reseller_form_notification_email" => "Os revendedores podem definir o e-mail para notificações aqui.",	

	"reseller_form_address_1" => "Endereço do revendedor.",

	"reseller_form_address_2" => "Outro endereço do revendedor (se houver).",

	"reseller_form_city" => "Nome da cidade do revendedor.",

	"reseller_form_province" => "Nome da província do revendedor.",

	"reseller_form_postal_code" => "Código postal do revendedor.",

	"reseller_form_country_id" => "País do revendedor.",

	"reseller_form_timezone_id" => "Fuso horário do revendedor. NOTA: O FluxSBC não suporta o horário de verão por padrão. Será necessário alterar manualmente o fuso horário da conta para lidar com isso.",

	"reseller_form_currency_id" => "Definir a moeda para as novas contas.",

	"reseller_form_invoice_config_flag" => "Refletir os mesmos detalhes no perfil da empresa do revendedor.",
	//PROFILE SECTION SECTION END FOR RESELLER

	/*BILLING SETTINGS SECTION START FOR RESELLER*/
	"reseller_form_posttoexternal" => "Definir o tipo de conta (Pré-pago/Pós-pago).",

	"reseller_form_credit_limit" => "Para clientes pós-pagos, você pode definir o limite de crédito.",

	"reseller_form_pricelist_id" => "O grupo de tarifas é um campo essencial para a cobrança. Sem um grupo de tarifas, o cliente não poderá fazer chamadas. Você pode criar um grupo de tarifas navegando até Tarifa -> Grupo de tarifas.",

	"reseller_form_non_cli_pricelist_id" => "Grupo de tarifas selecionado com base nas opções do pool CLI.",

	"reseller_form_sweep_id" => "Agenda de faturamento para a geração de faturas.",

	"reseller_form_invoice_day" => "Se a programação de faturamento for mensal, você poderá definir o dia em que a fatura do cliente deve ser gerada.",

	"reseller_form_tax_number" => "Exibir o número de imposto nas faturas.",

	"reseller_form_generate_invoice" => "Permitir a geração de faturas com valor zero.",

	"reseller_form_invoice_note" => "Exibirá uma nota na fatura ao gerar faturas.",

	"reseller_form_reference" => "Definir a referência para o cliente.",

	"reseller_form_invoice_interval" => 'Definir intervalo de datas da fatura.',
	/*BILLING SETTINGS SECTION END FOR RESELLER*/

	/*Edit reseller*/

	"reseller_form_registration_url" => 'URL para compartilhar/usar para registrar uma nova conta diretamente sob este revendedor.',

	"reseller_form_status" => 'O status para a entrada adicionada.',

	"reseller_form_tax_id[]" => 'Dos impostos configurados globalmente, selecione os aplicáveis para este revendedor.',

	"reseller_form_loss_less_routing" => "Permite chamadas se a tarifa de origem for menor que as tarifas de terminação.",

	"reseller_form_charge_per_min" => "Cobrança por minuto para chamadas locais/On-net/Sip2Sip.",

	"reseller_form_notify_flag" => 'Ativar ou não o alerta de saldo baixo.',

	"reseller_form_notify_credit_limit" => 'Qual saldo é considerado baixo para acionar o alerta.',

	/*Create Panel Access For Admin Section Start*/
	"admin_form_number" => "O número da conta é uma string de identificador único de 10 dígitos. Pode ser o número de telefone ou uma string gerada aleatoriamente ou uma string única personalizada.",

	"admin_form_password" => "A senha que deve ser fornecida ao administrador para que ele/ela possa fazer login no portal.",

	"admin_form_email" => "Endereço de e-mail do administrador que também é utilizado para login.",

	"admin_form_permission_id" => "Atribuição de acessibilidade.",
	
	"admin_form_domain_id" => "Domínio de login.",
	/*Create Panel Access Admin Section End*/

	/*Create Profile Section For Admin Start*/
	"admin_form_first_name" => "Primeiro nome do administrador.",

	"admin_form_last_name" => "Último nome do administrador.",

	"admin_form_notification_email" => "O administrador pode definir o e-mail para notificações aqui.",

	"admin_form_telephone_1" => "Número de telefone principal.",

	"admin_form_telephone_2" => "Número de telefone secundário.",
	/*Create Profile Section For Admin End*/

	/*Edit Profile Section For Admin Start*/
	"admin_form_company_name" => "Nome da empresa do administrador.",

	"admin_form_address_1" => "Endereço do administrador.",

	"admin_form_address_2" => "Outro endereço do administrador (se houver).",

	"admin_form_city" => "Nome da cidade do administrador.",

	"admin_form_province" => "Nome da província do administrador.",

	"admin_form_postal_code" => "Código postal do administrador.",

	"admin_form_country_id" => "País do administrador.",

	"admin_form_timezone_id" => "Fuso horário do administrador. NOTA: O FluxSBC não suporta o horário de verão por padrão. Será necessário alterar manualmente o fuso horário da conta para lidar com isso.",

	"admin_form_currency_id" => "Definir a moeda para as novas contas.",

	"admin_form_status" => "Status da conta.",

	"ani_map_number" => "Lista de ID de chamadas atribuídas para permitir o uso sem PIN do número de acesso.",

	"did_purchase_country_id" => "Listar DIDs para compra com base no país.",

	"did_purchase_provience" => "Listar DIDs para compra com base na província.",

	"did_purchase_city" => "Listar DIDs para compra com base na cidade.",

	"did_purchase_free_didlist" => "DIDs disponíveis com base nos critérios de busca.",

	"purchase_products_applayable_product" => "Lista de produtos atribuídos a esta conta.",

	"customer_alert_threshold_notify_flag" => "Ativar ou não o alerta de saldo baixo.",

	"customer_alert_threshold_notify_credit_limit" => "Qual saldo é considerado baixo para acionar o alerta.",
	/*Edit Profile Section For Admin End*/

	/*Create Sip Devices*/
	"sipdevices_form_fs_username" => "Nome de usuário da extensão SIP.",

	"sipdevices_form_fs_password" => "Senha da extensão SIP.",

	"sipdevices_form_effective_caller_id_name" => "Nome do chamador apresentado para a extensão.",

	"sipdevices_form_effective_caller_id_number" => "Número do chamador apresentado para a extensão.",

	"sipdevices_form_reseller_id" => "Conta do revendedor à qual esta extensão pertence.",

	"sipdevices_form_accountcode" => "Conta de cliente à qual esta extensão pertence.",

	"sipdevices_form_status" => "O status para a entrada adicionada.",

	"sipdevices_form_sip_profile_id" => "Perfil SIP ao qual esta extensão pertence.",
	/*End*/

	/*Voicemail Options*/
	"sipdevices_form_voicemail_enabled" => "Ativar ou não o correio de voz.",

	"sipdevices_form_voicemail_password" => "Senha para acessar o correio de voz.",

	"sipdevices_form_voicemail_mail_to" => "Endereço de e-mail para receber notificação de correio de voz.",

	"sipdevices_form_voicemail_attach_file" => "Anexar ou não o correio de voz no e-mail.",

	"sipdevices_form_vm_keep_local_after_email" => "Manter uma cópia do correio de voz no sistema após recebê-lo por e-mail.",

	"sipdevices_form_vm_send_all_message" => "Enviar todos os correios de voz como notificação e/ou anexo no e-mail.",
	/*End*/

	// ------------------------ACCOUNT MODULE END-------------------------
);

?>

