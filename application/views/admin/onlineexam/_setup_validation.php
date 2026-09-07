<script>
window.onlineexamValidateWindow = function (form, startName, endName) {
    var $form = jQuery(form), $start = $form.find('[name="' + startName + '"]'), $end = $form.find('[name="' + endName + '"]');
    var duration = Number($form.find('[name="duration_minutes"]').val());
    var format = typeof calendar_date_time_format !== 'undefined' ? calendar_date_time_format + ' hh:mm a' : null;
    var picker = $start.data('DateTimePicker');
    if (picker && typeof picker.format === 'function') { format = picker.format(); }
    if (!$end.length || !window.moment || !format) { return true; }
    var start = moment($start.val(), format, true), end = moment($end.val(), format, true);
    var message = start.isValid() && end.isValid() && isFinite(duration) && duration > 0 && end.diff(start, 'seconds') < duration * 60
        ? 'The time between opening and closing must be at least the full duration.' : '';
    $end[0].setCustomValidity(message);
    if (message) { $end[0].reportValidity(); return false; }
    return true;
};
jQuery(document).on('input change dp.change', '.onlineexam-ui [name="exam_from"], .onlineexam-ui [name="exam_to"], .onlineexam-ui [name="starts_at"], .onlineexam-ui [name="ends_at"], .onlineexam-ui [name="duration_minutes"]', function () {
    jQuery(this).closest('form').find('[name="exam_to"], [name="ends_at"]').each(function () { this.setCustomValidity(''); });
});
</script>
