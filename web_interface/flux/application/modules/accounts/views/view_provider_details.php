<? extend('master.php') ?>
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
                        $(".invoice_day").html(response);
                        $('.invoice_day').show();
                        $('label[for="Billing Day"]').show()
                    }
                });
            }else{
                $('label[for="Billing Day"]').hide()
                $('.invoice_day').css('display','none');                
            }
        });
        $(".change_pass").click(function(){
            $.ajax({type:'POST',
                url: "<?= base_url() ?>accounts/customer_generate_password/",
                success: function(response) {
                    $('#password').val(response.trim());
                }
            });
        })
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
                            }else{
                                window.location.reload();
                            }
                        }
                    });
                });
        $(".change_number").click(function(){
            $.ajax({type:'POST',
                url: "<?= base_url() ?>accounts/customer_generate_number/"+10,
                success: function(response) {
                    var data=response.replace('-',' ');
                    $('#number').val(data.trim());
                }
            });
        })
    });


</script>
<script type="text/javascript">
  $(document).ready(function(){
      $('.page-wrap').addClass('addon_wrap');
  });
</script>
<script type="text/javascript">
  $(document).ready(function(){
      $(".breadcrumb li a").removeAttr("data-ripple",""); 
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
<? endblock() ?>

<? startblock('page-title') ?>
<?= $page_title ?>
<? endblock() ?>

<? startblock('content') ?>

<div class="p-0">
	<section class="slice color-three">
		<div class="w-section inverse p-0">
      <?php echo $form; ?>
      <?php
    if (isset($validation_errors) && $validation_errors != '') {
        ?>
        <script>
            var ERR_STR = '<?php echo $validation_errors; ?>';
            print_error(ERR_STR);
        </script>
    <? } ?>


</div>
	</section>
</div>
<? endblock() ?>	
<? startblock('sidebar') ?>
Filter by
<? endblock() ?>
<? end_extend() ?>
<script type="text/javascript" language="javascript">

$(document).ready(function() {
  $("textarea").parents('li.form-group').addClass("h-auto");	
});

</script>
