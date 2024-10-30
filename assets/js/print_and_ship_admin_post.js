function selectRow(element) {
    var checked = true;
    var tr = jQuery(element).parent().parent();

    if (jQuery(element).val().length === 0) {
        checked = false;
    }

    jQuery('th input', tr).prop('checked', checked);
}
