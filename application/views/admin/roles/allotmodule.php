
<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-gears"></i> <?php echo $this->lang->line('system_settings'); ?></h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><?php echo $this->lang->line('assign_permission'); ?> (<?php echo $role['name'] ?>) </h3>
                    </div>
                    <form id="form1" action="<?php echo site_url('admin/roles/permission/' . $role['id']) ?>"  id="employeeform" name="employeeform" method="post" accept-charset="utf-8">
                        <div class="box-body">

                            <?php echo $this->customlib->getCSRF(); ?>  
                            <input type="hidden" name="role_id" value="<?php echo $role['id'] ?>"/>
                            <div class="table-responsive">  
                                <table class="table table-stripped">
                                    <thead>
                                        <tr>
                                            <th><?php echo $this->lang->line('module'); ?></th>
                                            <th><?php echo $this->lang->line('feature'); ?></th>
                                            <th><?php echo $this->lang->line('view'); ?></th>
                                            <th><?php echo $this->lang->line('add'); ?></th>
                                            <th><?php echo $this->lang->line('edit'); ?></th>
                                            <th><?php echo $this->lang->line('delete'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php
//                                   
                                        foreach ($role_permission as $key => $value) {
                                            ?>
                                            <tr>
                                                <th><?php echo $value->name ?></th>
                                                <?php
                                                if (!empty($value->permission_category)) {
                                                    ?>
                                                    <td>
                                                        <?php echo $value->permission_category[0]->name ?></td>
                                                    <td>
                                                        <?php
                                                        if ($value->permission_category[0]->enable_view == 1) {
                                                            ?>
                                                            <label class="">
                                                                <input type="checkbox" name="<?php echo "permissions[" . $value->permission_category[0]->id . "][can_view]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $value->permission_category[0]->id . "][can_view]", 1, ($value->permission_category[0]->can_view == 1) ? TRUE : FALSE); ?>>
                                                            </label> 

                                                            <?php
                                                        }
                                                        ?>

                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($value->permission_category[0]->enable_add == 1) {
                                                            ?>
                                                            <label class="">
                                                                <input type="checkbox" name="<?php echo "permissions[" . $value->permission_category[0]->id . "][can_add]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $value->permission_category[0]->id . "][can_add]", 1, ($value->permission_category[0]->can_add == 1) ? TRUE : FALSE); ?>>
                                                            </label> 
                                                            <?php
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($value->permission_category[0]->enable_edit == 1) {
                                                            ?>
                                                            <label class="">
                                                                <input type="checkbox" name="<?php echo "permissions[" . $value->permission_category[0]->id . "][can_edit]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $value->permission_category[0]->id . "][can_edit]", 1, ($value->permission_category[0]->can_edit == 1) ? TRUE : FALSE); ?>>
                                                            </label> 
                                                            <?php
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($value->permission_category[0]->enable_delete == 1) {
                                                            ?>
                                                            <label class="">
                                                                <input type="checkbox" name="<?php echo "permissions[" . $value->permission_category[0]->id . "][can_delete]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $value->permission_category[0]->id . "][can_delete]", 1, ($value->permission_category[0]->can_delete == 1) ? TRUE : FALSE); ?>>
                                                            </label> 
                                                            <?php
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <td colspan="5"></td>
                                                    <?php
                                                }
                                                ?>

                                            </tr>
                                            <?php
                                            if (!empty($value->permission_category) && count($value->permission_category) > 1) {
                                                unset($value->permission_category[0]);
                                                foreach ($value->permission_category as $new_feature_key => $new_feature_value) {
                                                    ?>
                                                    <tr>
                                                        <td></td>
                                                        <td>
                                                            <?php echo $new_feature_value->name ?></td>
                                                        <td>
                                                            <?php
                                                            if ($new_feature_value->enable_view == 1) {
                                                                ?>
                                                                <label class="">
                                                                    <input type="checkbox" name="<?php echo "permissions[" . $new_feature_value->id . "][can_view]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $new_feature_value->id . "][can_view]", 1, ( $new_feature_value->can_view == 1) ? TRUE : FALSE); ?>>
                                                                </label> 
                                                                <?php
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if ($new_feature_value->enable_add == 1) {
                                                                ?>
                                                                <label class="">
                                                                    <input type="checkbox" name="<?php echo "permissions[" . $new_feature_value->id . "][can_add]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $new_feature_value->id . "][can_add]", 1, ( $new_feature_value->can_add == 1) ? TRUE : FALSE); ?>>
                                                                </label> 
                                                                <?php
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if ($new_feature_value->enable_edit == 1) {
                                                                ?>
                                                                <label class="">
                                                                    <input type="checkbox" name="<?php echo "permissions[" . $new_feature_value->id . "][can_edit]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $new_feature_value->id . "][can_edit]", 1, ( $new_feature_value->can_edit == 1) ? TRUE : FALSE); ?>>
                                                                </label> 
                                                                <?php
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if ($new_feature_value->enable_delete == 1) {
                                                                ?>
                                                                <label class="">
                                                                    <input type="checkbox" name="<?php echo "permissions[" . $new_feature_value->id . "][can_delete]"; ?>" value="1" <?php echo set_checkbox("permissions[" . $new_feature_value->id . "][can_delete]", 1, ( $new_feature_value->can_delete == 1) ? TRUE : FALSE); ?>>
                                                                </label> 
                                                                <?php
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>
                                            <?php
                                        }
                                        ?>

                                    </tbody>

                                </table>
                            </div><!--./table-responsive-->   


                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-info pull-right"><?php echo $this->lang->line('save'); ?></button>
                        </div>
                    </form>
                </div>
            </div>         

        </div>

    </section>
</div>
