<?php extend('master.php') ?>
<?php startblock('page-title') ?>
<?php echo $page_title; ?>
<?php endblock() ?>
<?php startblock('content') ?>
<section class="slice color-three">
	<div class="w-section">

		<form method="post" action="<?php echo base_url() ?>cobilling/upload_save/"
			enctype="multipart/form-data" id="cobilling_form" name="cobilling_form">
			<div class="row">
				<div class="col-md-12">
					<div class="card col-md-12 p-0 mb-4">
						<div class="pb-4" id="floating-label">
							<h3 class="bg-secondary text-light p-3 rounded-top"><?php echo gettext("Importar XML NFCom"); ?></h3>
							<div class="col-md-12">
								<div class="p-0 row">

									<div class="col-md-6 form-group">
										<label class="p-0 control-label"><?php echo gettext("Conta"); ?></label>
										<?php echo $account_dropdown; ?>
									</div>

									<div class="col-md-6 form-group">
										<label class="p-0 control-label"><?php echo gettext("Arquivo XML (NFCom)"); ?></label>
										<div class="col-12 mt-2 p-0" data-ripple="">
											<input type="file" name="nfcom_xml" id="nfcom_xml" accept=".xml"
												class="custom-file-input" required
												title="<?php echo gettext('Somente arquivos .xml.'); ?>" />
											<label class="custom-file-label btn-primary btn-file text-left" for="nfcom_xml"> </label>
										</div>
									</div>

									<div class="col-md-12 mt-3">
										<div class="form-check">

											<label class="p-0 orm-check-label" for="emitir_agora"><span class="mr-4 align-middle">
													<?php echo gettext("Emitir agora no Emissor62"); ?></span>
												<input
													class="align-middle form-check-input"
													type="checkbox"
													id="emitir_agora"
													name="emitir_agora"
													value="1">
											</label>

											<small class="form-text text-muted">
												<?php echo gettext("Se desmarcado, o XML fica vinculado à conta como pendente para emissão/reprocessamento posterior."); ?>
											</small>
										</div>
									</div>


								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-12">
					<div class="text-center">
						<button class="btn btn-success" type="submit" name="action" value="upload"><?php echo gettext("Enviar"); ?></button>
					</div>
				</div>
			</div>
		</form>

	</div>
</section>
<script type="text/javascript">
	$('input[type="file"]').change(function(e) {
		var fileName = e.target.files[0].name;
		$('.custom-file-label').html(fileName);
	});
	$(document).ready(function() {
		$('.selectpicker').selectpicker();
	});
</script>
<?php endblock() ?>
<?php end_extend() ?>