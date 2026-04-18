<?php
$currency_symbol    = $this->customlib->getSchoolCurrencyFormat();
$receipt_header     = $this->setting_model->unlink_receiptheader();
$receipt_header_url = get_school_asset_url($receipt_header, 'uploads/print_headerfooter/student_receipt');
$watermark_logo     = !empty($sch_setting->image) ? $sch_setting->image : '';
$watermark_logo_url = get_school_asset_url($watermark_logo, 'uploads/school_content/logo');
?>
<style type="text/css">
    .page-break { display: block; page-break-before: always; }
    .invoice {
        min-height: 560px;
        overflow: hidden;
        position: relative;
    }
    .receipt-watermark {
        left: 50%;
        opacity: 0.08;
        pointer-events: none;
        position: absolute;
        top: 52%;
        transform: translate(-50%, -50%);
        width: 240px;
        z-index: 0;
    }
    .receipt-watermark img {
        display: block;
        width: 100%;
    }
    .invoice .row,
    .invoice hr,
    .invoice table {
        position: relative;
        z-index: 1;
    }
    .receipt-header-image {
        display: block;
        height: 100px;
        object-fit: contain;
        width: 100%;
    }
</style>

<div class="container">
    <?php foreach ($receipts as $receipt_index => $receipt) {
        $feeList        = $receipt['feeList'];
        $record         = $receipt['record'];
        $sub_invoice_id = $receipt['sub_invoice_id'];
        $copy_labels    = $sch_setting->is_duplicate_fees_invoice ? array('office_copy', 'student_copy') : array('');
        $collected_by   = '';

        if (!empty($record->collected_by)) {
            $collected_by = $record->collected_by;
        } elseif (!empty($record->received_by)) {
            $collected_by = $record->received_by;
        }

        foreach ($copy_labels as $copy_index => $copy_label) {
            if ($receipt_index > 0 || $copy_index > 0) {
                echo '<div class="page-break"></div>';
            }
            ?>
            <div class="row">
                <div id="content" class="col-lg-12 col-sm-12">
                    <div class="invoice">
                        <?php if ($watermark_logo_url != '') { ?>
                            <div class="receipt-watermark">
                                <img src="<?php echo $watermark_logo_url; ?>" alt="<?php echo $this->customlib->getAppName(); ?>">
                            </div>
                        <?php } ?>

                        <div class="row header">
                            <div class="col-sm-12">
                                <?php if ($receipt_header_url != '') { ?>
                                    <img src="<?php echo $receipt_header_url; ?>" class="receipt-header-image" alt="<?php echo $this->lang->line('fees_receipt'); ?>">
                                <?php } ?>
                            </div>
                        </div>

                        <?php if ($copy_label != '') { ?>
                            <div class="row">
                                <div class="col-md-12 text text-center">
                                    <?php echo $this->lang->line($copy_label); ?>
                                </div>
                            </div>
                        <?php } ?>

                        <div class="row">
                            <div class="col-xs-6 text-left">
                                <br/>
                                <address>
                                    <strong><?php echo $this->customlib->getFullName($feeList->firstname, $feeList->middlename, $feeList->lastname, $sch_setting->middlename, $sch_setting->lastname); ?></strong><?php echo " (" . $feeList->admission_no . ")"; ?><br>
                                    <?php echo $this->lang->line('father_name'); ?>: <?php echo $feeList->father_name; ?><br>
                                    <?php echo $this->lang->line('class'); ?>: <?php echo $feeList->class . " (" . $feeList->section . ")"; ?>
                                </address>
                            </div>
                            <div class="col-xs-6 text-right">
                                <br/>
                                <address>
                                    <strong><?php echo $this->lang->line('date'); ?>: <?php echo date($this->customlib->getSchoolDateFormat(), $this->customlib->dateyyyymmddTodateformat(date('Y-m-d'))); ?></strong><br/>
                                    <strong><?php echo $this->lang->line('payment_id'); ?>: <?php echo $feeList->student_fees_deposite_id . "/" . $sub_invoice_id; ?></strong><br/>
                                    <?php if ($collected_by != '') { ?>
                                        <strong><?php echo $this->lang->line('collected_by'); ?>: <?php echo $collected_by; ?></strong>
                                    <?php } ?>
                                </address>
                            </div>
                        </div>

                        <hr style="margin-top: 0px;margin-bottom: 0px;" />

                        <div class="row">
                            <table class="table table-striped table-responsive" style="font-size: 8pt;">
                                <thead>
                                    <tr>
                                        <th><?php echo $this->lang->line('date'); ?></th>
                                        <th><?php echo $this->lang->line('fees_group'); ?></th>
                                        <th><?php echo $this->lang->line('fees_code'); ?></th>
                                        <th><?php echo $this->lang->line('mode'); ?></th>
                                        <th class="text text-right"><?php echo $this->lang->line('amount'); ?></th>
                                        <th class="text text-right"><?php echo $this->lang->line('discount'); ?></th>
                                        <th class="text text-right"><?php echo $this->lang->line('fine'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo date($this->customlib->getSchoolDateFormat(), $this->customlib->dateyyyymmddTodateformat($record->date)); ?></td>
                                        <td><?php echo $feeList->name; ?></td>
                                        <td><?php echo $feeList->code; ?></td>
                                        <td><?php echo $record->payment_mode; ?></td>
                                        <td class="text text-right"><?php echo $currency_symbol . number_format($record->amount, 2, '.', ''); ?></td>
                                        <td class="text text-right"><?php echo $currency_symbol . number_format($record->amount_discount, 2, '.', ''); ?></td>
                                        <td class="text text-right"><?php echo $currency_symbol . number_format($record->amount_fine, 2, '.', ''); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row header">
                            <div class="col-sm-12">
                                <?php $this->setting_model->get_receiptfooter(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php }
    } ?>
</div>
