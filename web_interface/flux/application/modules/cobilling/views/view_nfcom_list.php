<?php extend('master.php') ?>
<?php startblock('extra_head') ?>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        build_grid("cobilling_grid", "", <?php echo $grid_fields; ?>, <?php echo $grid_buttons; ?>);

        $("#cobilling_search_btn").click(function() {
            post_request_for_search("cobilling_grid", "", "cobilling_list_search");
        });
        $("#id_reset").click(function() {
            clear_search_request("cobilling_grid", "cobilling_clearsearchfilter");
        });
    });
</script>
<?php endblock() ?>

<?php startblock('page-title') ?>
    <?php echo $page_title; ?>
<?php endblock() ?>

<?php startblock('content') ?>

<section class="slice color-three">
    <div class="w-section inverse p-0">
        <div class="col-12">
            <div class="portlet-content mb-4" id="search_bar" style="display: none">
                <?php echo $form_search; ?>
            </div>
        </div>
    </div>
</section>

<section class="slice color-three pb-4">
    <div class="w-section inverse p-0">
        <div class="card col-md-12 pb-4">
            <form method="POST" action="<?php echo base_url(); ?>cobilling/cobilling_list_delete/0/" enctype="multipart/form-data" id="ListForm">
                <table id="cobilling_grid" align="left" style="display: none;"></table>
            </form>
        </div>
    </div>
</section>

<?php endblock() ?>
<?php end_extend() ?>
