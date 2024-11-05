<?php $currency_symbol = $this->customlib->getSchoolCurrencyFormat(); ?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <section class="content-header">
        <h1>
            <i class="fa fa-money"></i> Next Term Fee</h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <?php
            if ($this->rbac->hasPrivilege('fees_type', 'can_add')) {
                ?>
                <div class="col-md-4">
                    <!-- Horizontal Form -->
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Edit Next Term Fee </h3>
                        </div><!-- /.box-header -->
                        <form id="form1" action="<?php echo base_url() ?>admin/feenext/edit/<?php echo $fee['id']; ?>"  id="employeeform" name="employeeform" method="post" accept-charset="utf-8">
                            <div class="box-body">
                                <?php if ($this->session->flashdata('msg')) { ?>
                                    <?php echo $this->session->flashdata('msg') ?>
                                <?php } ?>
                                <?php
                                if (isset($error_message)) {
                                    echo "<div class='alert alert-danger'>" . $error_message . "</div>";
                                }
                                ?>
                                <?php echo $this->customlib->getCSRF(); ?>

                                <div class="form-group">
                                    <label for="exampleInputEmail1">Next Fee Name</label><small class="req">*</small>
                                    <input autofocus="" id="name" name="name" type="text" class="form-control" required value="<?php echo set_value('name', $fee['name']); ?>" />
                                    <span class="text-danger"><?php echo form_error('name'); ?></span>
                                </div>
                                
                                <div class="form-group">
                                    <label for="exampleInputEmail1"><?php echo $this->lang->line('class'); ?></label><small class="req"> *</small>
                                    <select  id="class_id" name="class" class="form-control"  >
                                        <option value=""><?php echo $this->lang->line('select'); ?></option>
                                        <?php
                                        foreach ($classlist as $class) {
                                            ?>
                                            <option value="<?php echo $class['id'] ?>"<?php if (set_value('class_id', $fee['class_id']) == $class['id']) echo "selected=selected" ?>><?php echo 							 
  						$class['class'] ?></option>
                                            <?php
                                            $count++;
                                        }
                                        ?>
                                    </select>
                                    <span class="text-danger"><?php echo form_error('class_id'); ?></span>
                                </div>
                               
                               <div class="form-group">
					<label for="exampleInputEmail1"><?php echo 'Term'; ?></label> <small class="req">*</small>
					<select name="term" required class="form-control">
						<option value="">~~ select Term ~~</option>
						<option value="1st" <?php if(set_value('term', $fee['term']) == '1st'){ echo "selected";}; ?> >First Term</option>
						<option value="2nd" <?php if(set_value('term', $fee['term']) == '2nd'){ echo "selected";}; ?> >Second Term</option>
						<option value="3rd" <?php if(set_value('term', $fee['term']) == '3rd'){ echo "selected";}; ?> >Third Term</option>
													
					</select>
					<span class="text-danger"><?php echo form_error('term'); ?></span>
				</div>
                               
                                
                                <div class="form-group">
                                    <label for="exampleInputEmail1">Next Fee Amount </label> <small class="req">*</small>
                                    <input id="code" name="amount" type="number" class="form-control" required value="<?php echo set_value('amount', $fee['amount']); ?>" />
                                    <span class="text-danger"><?php echo form_error('amount'); ?></span>
                                </div>


                            </div><!-- /.box-body -->

                            <div class="box-footer">
                                <button type="submit" class="btn btn-info pull-right"><?php echo $this->lang->line('save'); ?></button>
                            </div>
                        </form>
                    </div>

                </div><!--/.col (right) -->
                <!-- left column -->
            <?php } ?>
            <div class="col-md-<?php
            if ($this->rbac->hasPrivilege('fees_type', 'can_add')) {
                echo "8";
            } else {
                echo "12";
            }
            ?>">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header ptbnull">
                        <h3 class="box-title titlefix">Next Term Fee List</h3>
                        <div class="box-tools pull-right">
                        </div><!-- /.box-tools -->
                    </div><!-- /.box-header -->
                    <div class="box-body">
                        <div class="download_label">Next Fee List</div>
                        <div class="mailbox-messages table-responsive">
                            <table class="table table-striped table-bordered table-hover example">
                                <thead>
                                    <tr>
                                        <th>Fee</th>
                                        <th>Class</th>
                                        <th>Term</th>
                                        <th>Amount</th>

                                        <th class="text-right"><?php echo $this->lang->line('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($fee_next_list as $fee) {
                                        ?>
                                        <tr>
                                            <td class="mailbox-name">
                                                <a href="#" data-toggle="popover" class="detail_popover"><?php echo $fee['name'] ?></a>

                                            </td>
                                            
                                            <td class="mailbox-name">
                                                <?php echo $fee['class']; ?>
                                            </td>
                                            
                                            <td class="mailbox-name">
                                                <?php echo $fee['term']; ?> Term
                                            </td>
                                            
                                            <td class="mailbox-name">
                                                <?php echo $fee['amount']; ?>
                                            </td>
                                            
                                            

                                            <td class="mailbox-date pull-right">
                                            
                                            <a data-placement="left" href="<?php echo base_url(); ?>admin/feenext/edit/<?php echo $fee['id'] ?>"
                                                           class="btn btn-default btn-xs" data-toggle="tooltip" title="Edit">
                                                            <i class="fa fa-pencil"></i>
                                                        </a>
                                                
                                                <?php
                                                if ($this->rbac->hasPrivilege('fees_type', 'can_delete')) {
                                                    ?>
                                                    <a data-placement="left" href="<?php echo base_url(); ?>admin/feenext/delete/<?php echo $fee['id'] ?>"class="btn btn-default btn-xs"  data-toggle="tooltip" title="<?php echo $this->lang->line('delete'); ?>" onclick="return confirm('<?php echo $this->lang->line('delete_confirm') ?>');">
                                                        <i class="fa fa-remove"></i>
                                                    </a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>

                                </tbody>
                            </table><!-- /.table -->



                        </div><!-- /.mail-box-messages -->
                    </div><!-- /.box-body -->
                </div>
            </div><!--/.col (left) -->
            <!-- right column -->

        </div>
        <div class="row">
            <!-- left column -->

            <!-- right column -->
            <div class="col-md-12">

            </div><!--/.col (right) -->
        </div>   <!-- /.row -->
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<script>
    $(document).ready(function () {
        $('.detail_popover').popover({
            placement: 'right',
            trigger: 'hover',
            container: 'body',
            html: true,
            content: function () {
                return $(this).closest('td').find('.fee_detail_popover').html();
            }
        });
    });
</script>