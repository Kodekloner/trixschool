<?php
if (!empty($result)) {
   $is_image = 1;
    foreach ($result as $result_key => $result_value) {
        $file     = "https://schoollift.s3.us-east-2.amazonaws.com/" . $result_value->thumb_path . $result_value->img_name;
        $file_src = "https://schoollift.s3.us-east-2.amazonaws.com/" . $result_value->thumb_path . $result_value->img_name;
        ?>
  <div class='col-sm-3 col-md-2 col-xs-6 img_div_modal image_div div_record_<?php echo $result_value->id; ?>'>
  <div class='fadeoverlay'>
  <div class='fadeheight'>
  <img class='' data-fid='<?php echo $result_value->id; ?>' data-content_type='<?php echo $result_value->file_type; ?>' data-content_name='<?php echo $result_value->img_name; ?>' data-is_image='<?php echo $is_image; ?>' data-vid_url="<?php echo $result_value->vid_url; ?>" data-img='<?php echo base_url() . $result_value->dir_path . $result_value->img_name; ?>' src='<?php echo $file; ?>'>
  </div>

      <i class='fa fa-picture-o videoicon'></i>

      <p class=''><?php echo $result_value->img_name;?></p>

  </div>
  </div>
	<?php
}
}

?>
