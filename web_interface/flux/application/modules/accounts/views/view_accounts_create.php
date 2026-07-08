<?extend('master.php')?>
<?startblock('extra_head')?>
<script type="text/javascript">
    $(document).ready(function() {

        $('#customer_form').submit(function(){
            $("input[type='submit']", this)
            .val("Please Wait...")
            .attr('disabled', 'disabled');
            return true;
        });
        $(".change_pass").click(function(){
            $.ajax({type:'POST',
                url: "<?=base_url()?>accounts/customer_generate_password/",
                success: function(response) {
                    if(response.length > 50){
                       location.reload(true);
                   }
                   $('#password').val(response.trim());
               }
           });
        })
        $(".change_number").click(function(){
            $.ajax({type:'POST',
                url: "<?=base_url()?>accounts/customer_generate_number/",
                success: function(response) {
                   if(response.length > 50){
                       location.reload(true);
                   }
                   var data=response.replace('-',' ');
                   $('#number').val(data.trim());
               }
           });
        });
        $(".change_pin").click(function(){
            $.ajax({type:'POST',
                url: "<?=base_url()?>accounts/customer_generate_pin/",
                success: function(response) {
                  var data=response.replace('-',' ');
                  $('#change_pin').val(data.trim());
              }
          });
        });
        $(document).on('click', '.consult_tax_number', function(){
            var doc = $("input[name='tax_number']").val() || $('#tax_number').val();
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
                data: {doc: doc},
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
                        if(m.company_name) $("input[name='company_name']").val(m.company_name);
                        if(m.address_1) $("input[name='address_1']").val(m.address_1);
                        if(m.address_2) $("input[name='address_2']").val(m.address_2);
                        if(m.city) $("input[name='city']").val(m.city);
                        if(m.province) $("input[name='province']").val(m.province);
                        if(m.postal_code) $("input[name='postal_code']").val(m.postal_code);
                        if(m.telephone_1) $("input[name='telephone_1']").val(m.telephone_1);
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
                    }
                    else{
                        window.location.reload();
                    }
                }
            });
        });
        $(".digit_length").change(function(){
            var digit=this.value;
            $.ajax({type:'POST',
                url: "<?=base_url()?>accounts/customer_generate_number/"+digit,
                success: function(response) {
                    $('#number').val(response.trim());
                }
            });
        });
<?php if ($entity_name != 'admin' && $entity_name != 'subadmin') {?>
		           document.getElementsByName("sweep_id")[0].selectedIndex = <?=1?>;

		           $(".sweep_id").change(function(e){
		            if(this.value != 0){
		                $.ajax({
		                    type:'POST',
		                    url: "<?=base_url()?>accounts/customer_invoice_option/",
		                    data:"sweepid="+this.value,
		                    success: function(response) {

		                        $('.invoice_day').parents('li.form-group').removeClass("d-none");
		                        $('.invoice_day').selectpicker('show');
		                        $('#invoice_day').html(response);
		                        $('.selectpicker').selectpicker('refresh');
		                    }
		                });
		            }else{

		                $('.invoice_day').parents('li.form-group').addClass("d-none");
		            }
		        });
		           $("#reseller").change(function(){
		            $.ajax({
		                type:'POST',
		                url: "<?=base_url()?>/accounts/customer_pricelist/",
		                data:"reseller_id="+this.value,
		                success: function(response) {
		                   $("#pricelist_id").html(response);
		                   $("#non_cli_pricelist_id").html(response);
		                   $('.selectpicker').selectpicker('refresh');
		               }
		           });
		            $.ajax({
		                type:'POST',
		                url: "<?=base_url()?>/accounts/reseller_distributor/",
		                data:"reseller_id="+this.value,
		                success: function(response) {
		                   response = $.trim(response);
		                   if(response == "Yes"){
		                     $('.is_distributor').parents('li.form-group').removeClass("d-none");
		                     $('.is_distributor').selectpicker('show');
		                 }else{
		                     $('.is_distributor').parents('li.form-group').addClass("d-none");
		                 }
		             }
		         });
		        });
		           $("#reseller").change();
		           $(".sweep_id").change();
	<?php }?>
});

</script>
<?php endblock()?>
<?php startblock('page-title')?>
<?=$page_title?>
<?php endblock()?>
<?php startblock('content')?>
<div class="p-0">
	<section class="slice color-three">
		<div class="w-section inverse p-0">
<?php echo $form;?>
    <?php
if (isset($validation_errors) && $validation_errors != '') {
	?>
		      <script>
		       var ERR_STR = '<?php echo $validation_errors;?>';
		       print_error(ERR_STR);
		   </script>
	<?}?>
</div>
	</section>
</div>
<?endblock()?>
<?startblock('sidebar')?>
<?endblock()?>
<?end_extend()?>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        $("input[type='hidden']").parents('li.form-group').addClass("d-none");


    });
</script>
