<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); ?>
<?php
// Popup facebox: fragmento HTML (sem master.php).
// Recebe: $reg (registro), $xml_pretty, $payload_pretty, $response_pretty,
// $status_badge, $origem_badge.

$id             = isset($reg['id']) ? (int) $reg['id'] : 0;
$accountid      = isset($reg['accountid']) ? (int) $reg['accountid'] : 0;
$tab_id         = 'cobilling_details_' . $id;
$danfe          = isset($reg['danfe_com']) ? (string) $reg['danfe_com'] : '';
$xml_file       = isset($reg['xml_file']) ? (string) $reg['xml_file'] : '';
$chave_nfcom    = isset($reg['chave_nfcom']) ? (string) $reg['chave_nfcom'] : '';
$chave_cofat    = isset($reg['chave_cofaturamento']) ? (string) $reg['chave_cofaturamento'] : '';
$numero         = isset($reg['numero']) ? $reg['numero'] : '';
$situacao       = isset($reg['situacao']) ? (string) $reg['situacao'] : '';
$motivo         = isset($reg['motivo']) ? (string) $reg['motivo'] : '';
$guid           = isset($reg['guid']) ? (string) $reg['guid'] : '';
$http_code      = isset($reg['http_code']) ? $reg['http_code'] : '';
$tentativas     = isset($reg['tentativas']) ? (int) $reg['tentativas'] : 0;
$origem         = isset($reg['origem']) ? (string) $reg['origem'] : '';
$created_at     = isset($reg['created_at']) ? (string) $reg['created_at'] : '';
$updated_at     = isset($reg['updated_at']) ? (string) $reg['updated_at'] : '';
?>
<div class="cobilling-details" style="max-width: 900px; min-width: 700px;">
    <h4 class="mb-3"><?php echo gettext('Detalhes da NFCom Co-Billing'); ?> #<?php echo $id; ?></h4>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#<?php echo $tab_id; ?>-summary" role="tab"><?php echo gettext('Resumo'); ?></a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#<?php echo $tab_id; ?>-xml" role="tab"><?php echo gettext('XML recebido'); ?></a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#<?php echo $tab_id; ?>-payload" role="tab"><?php echo gettext('Payload enviado'); ?></a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#<?php echo $tab_id; ?>-response" role="tab"><?php echo gettext('Resposta'); ?></a></li>
    </ul>

    <div class="tab-content border border-top-0 p-3" style="max-height: 60vh; overflow-y: auto;">

        <div class="tab-pane fade show active" id="<?php echo $tab_id; ?>-summary" role="tabpanel">
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php echo gettext('Data'); ?></th>
                        <td><?php echo htmlspecialchars($created_at, ENT_QUOTES, 'UTF-8'); ?>
                            <small class="text-muted"><?php echo gettext('(atualizado'); ?> <?php echo htmlspecialchars($updated_at, ENT_QUOTES, 'UTF-8'); ?>)</small>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Status'); ?></th>
                        <td><?php echo $status_badge; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Origem'); ?></th>
                        <td><?php echo $origem_badge; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Conta vinculada'); ?></th>
                        <td>
                            <?php if ($accountid > 0): ?>
                                <a href="<?php echo base_url(); ?>accounts/customer_edit/<?php echo $accountid; ?>" target="_blank">
                                    <?php echo gettext('#'); ?><?php echo $accountid; ?>
                                </a>
                            <?php else: ?>
                                <?php echo gettext('(sem vínculo)'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Nº NFCom'); ?></th>
                        <td><?php echo htmlspecialchars((string) $numero, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Chave NFCom'); ?></th>
                        <td><code><?php echo htmlspecialchars($chave_nfcom, ENT_QUOTES, 'UTF-8'); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Chave Cofaturamento'); ?></th>
                        <td><code><?php echo htmlspecialchars($chave_cofat, ENT_QUOTES, 'UTF-8'); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Situação'); ?></th>
                        <td><?php echo htmlspecialchars($situacao, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Motivo'); ?></th>
                        <td><?php echo htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('GUID'); ?></th>
                        <td><code><?php echo htmlspecialchars($guid, ENT_QUOTES, 'UTF-8'); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('HTTP'); ?></th>
                        <td><?php echo htmlspecialchars((string) $http_code, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Tentativas'); ?></th>
                        <td><?php echo (int) $tentativas; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('Arquivo XML'); ?></th>
                        <td><?php echo $xml_file !== '' ? htmlspecialchars($xml_file, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo gettext('DANFE'); ?></th>
                        <td>
                            <?php if ($danfe !== ''): ?>
                                <a href="<?php echo htmlspecialchars($danfe, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-external-link"></i>&nbsp;<?php echo gettext('Abrir DANFE'); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-3">
                <a class="btn btn-royelblue btn-sm" href="<?php echo base_url(); ?>cobilling/cobilling_download_xml/<?php echo $id; ?>">
                    <i class="fa fa-cloud-download"></i>&nbsp;<?php echo gettext('Baixar XML'); ?>
                </a>
                <a class="btn btn-royelblue btn-sm" href="<?php echo base_url(); ?>cobilling/cobilling_reprocess/<?php echo $id; ?>"
                   onclick="return confirm('<?php echo gettext('Reprocessar esta NFCom?'); ?>');">
                    <i class="fa fa-repeat"></i>&nbsp;<?php echo gettext('Reprocessar'); ?>
                </a>
            </div>
        </div>

        <div class="tab-pane fade" id="<?php echo $tab_id; ?>-xml" role="tabpanel">
            <pre style="max-height: 50vh; overflow: auto;"><?php echo htmlspecialchars((string) $xml_pretty, ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>

        <div class="tab-pane fade" id="<?php echo $tab_id; ?>-payload" role="tabpanel">
            <pre style="max-height: 50vh; overflow: auto;"><?php echo htmlspecialchars((string) $payload_pretty, ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>

        <div class="tab-pane fade" id="<?php echo $tab_id; ?>-response" role="tabpanel">
            <pre style="max-height: 50vh; overflow: auto;"><?php echo htmlspecialchars((string) $response_pretty, ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    </div>
</div>
