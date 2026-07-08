<? extend('left_panel_master.php') ?>
<? startblock('extra_head') ?>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
       
        $(".sweep_id").change(function(){
            var sweep_id =$('.sweep_id option:selected').val();
            if(sweep_id != 0){
                $.ajax({
                    type:'POST',
                    url: "<?= base_url() ?>/accounts/customer_invoice_option/<?= $invoice_date ?>",
                    data:"sweepid="+sweep_id, 
                    success: function(response) {
                        $("#invoice_day").html(response);
                        $('.selectpicker').selectpicker('refresh');
                        $('.invoice_day').parent('li').show();
                    }
                });
            }else{
               $('.invoice_day').parent('li').hide();               
            }
        });
        $(".change_pin").click(function(){
           var str_size='<?php echo $callingcard; ?>';
           $.ajax({type:'POST',
            url: "<?= base_url()?>accounts/customer_generate_number/"+str_size,
            success: function(response) {
              var data=response.replace('-',' ');
              $('#change_pin').val(data.trim());
          }
      });
       });
        $(document).on('click', '.consult_tax_number', function(){
            var doc = $("input[name='tax_number']").val() || $('#tax_number').val();
            var accountid = <?php echo json_encode($account_data['id']); ?>;
            if(!doc){
                if(typeof print_error === 'function'){
                    alert('Informe um CPF/CNPJ no campo Tax Number.');
                }else{
                    alert('Informe um CPF/CNPJ no campo Tax Number.');
                }
                return;
            }
            $.ajax({
                type:'POST',
                url: "<?=base_url()?>accounts/customer_generate_cnpj/",
                data: {doc: doc, accountid: accountid},
                success: function(resp){
                    try{ if(typeof resp === 'string') resp = JSON.parse(resp); }catch(e){}
                    if(resp && resp.ok){
                        var m = resp.mapped || {};
                        var fullName = m.company_name || '';
                        
                        if (fullName) {
                            var parts = fullName.trim().split(/\s+/);
                            var first_name = parts.shift() || '';
                            var last_name = parts.join(' ');
                        
                            $("input[name='first_name']").val(first_name);
                            $("input[name='last_name']").val(last_name);
                        }
                        var ieNumber = m.registrations || '';
                        if(m.company_name) $("input[name='company_name']").val(m.company_name);                        
                        if(m.address_1) $("input[name='address_1']").val(m.address_1);
                        if(m.address_2) $("input[name='address_2']").val(m.address_2);
                        if(m.city) $("input[name='city']").val(m.city);
                        if(m.province) $("input[name='province']").val(m.province);
                        if(m.postal_code) $("input[name='postal_code']").val(m.postal_code);
                        if(m.telephone_1) $("input[name='telephone_1']").val(m.telephone_1);
                        if(ieNumber) $("input[name='tax_city_number']").val(ieNumber);
                        if(m.email){
                            $("input[name='email']").val(m.email);
                            $("input[name='notification_email']").val(m.email);
                        }
                        if(resp.doc) $("input[name='tax_number']").val(resp.doc);
                    }
                    else{
                        var err = (resp && resp.error) ? resp.error : 'Falha ao consultar.';
                        if(typeof print_error === 'function'){
                            window.location.reload();
                        }else{
                            window.location.reload();
                        }
                    }
                },
                error: function(xhr){
                    var msg = xhr.responseJSON.message;
                    if(typeof print_error === 'function'){
                        window.location.reload();
                    }else{
                        window.location.reload();
                    }
                }
            });
        });
        var expiry_date = $("#expiry").val();
        $("#expiry").datetimepicker({
           value:expiry_date,
           uiLibrary: 'bootstrap4',
           iconsLibrary: 'fontawesome',
           modal:true,
           format: 'yyyy-mm-dd HH:mm:ss',
           footer:true
       });
    });
</script>
<script type="text/javascript">
  $(document).ready(function(){
      $('.page-wrap').addClass('addon_wrap');
      $("span.input-group-append").addClass('align-self-end').removeClass('input-group-append');
      $(".reset_password").parents("li").removeClass('form-group').addClass('mt-4');
  });
</script>
<style>
label.error {
	float: left;
	color: red;
	padding-left: .3em;
	vertical-align: top;
	padding-left: 40px;
	margin-top: 20px;
	width: 1500% !important;
}
</style>
<?php endblock() ?>
<? startblock('page-title') ?>
<?= $page_title ?>
<? endblock() ?>
<?php startblock('content') ?>
<div id="main-wrapper">
	<div id="content" class="container-fluid">
		<div class="row">
			<div class="col-md-12 color-three border_box">
				<div class="float-left m-2 lh19">
					<nav aria-label="breadcrumb">
						<ol class="breadcrumb m-0 p-0">
                        <?php $entity = $entity_name == 'provider' ? 'customer' : $entity_name; ?>
                        <li class="breadcrumb-item"><a
								href="<?= base_url()."accounts/".strtolower($entity)."_list/"; ?>"><?= gettext(ucfirst($entity_name)); ?>s</a></li>
							<li class="breadcrumb-item active" aria-current="page"><a
								href="<?= base_url()."accounts/".strtolower($entity_name)."_edit/".$edit_id."/"; ?>"> <?= ucfirst(@$entity_name); ?> <?php echo gettext('Profile');?> </a></li>
						</ol>
					</nav>
				</div>

				<div class="m-2 float-right">
					<a class="btn btn-light btn-hight"
						href="<?= base_url()."accounts/customer_list/"; ?>"> <i
						class="fa fa-fast-backward" aria-hidden="true"></i> <?php echo gettext('Back');?></a>
				</div>
   </div>


			<div class="p-4 col-md-12">
				<div class="slice color-three float-left content_border">
        <?php echo $form; ?>
        <?php if (isset($validation_errors) && $validation_errors != '') { ?>
            <script>
                var ERR_STR = '<?php echo $validation_errors; ?>';
                print_error(ERR_STR);
            </script>
        <?php } ?>
    </div>
			</div>
		</div>
	</div>
</div>
<? endblock() ?>
<? end_extend() ?>  

