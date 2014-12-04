jQuery(document).ready(function(){
    /**
     *  When dragging and dropping bug links to bug fields, strip out the URL and format nicely
     */
    jQuery('#edit-critical-bugs').addClass('bugfield');
    jQuery('#edit-regular-bugs').addClass('bugfield');

    var these_bugs;
    var critical_bugs = jQuery('#edit-critical-bugs').val();
    var regular_bugs = jQuery('#edit-regular-bugs').val();

    /* Trigger the 'change' even when the 'drop' event is activated on the bug inputs */
    jQuery('.bugfield').bind('drop', function(){
        setTimeout( jQuery.proxy(function(){
            if(this.value != critical_bugs){
                jQuery(this).trigger('change');
            }
        }, this), 0);
    });

    /* On 'change' event, turn (pasted) URLs into bug numbers */
    jQuery('.bugfield').bind('change', function(){
        if(jQuery(this).attr('id') == 'edit-critical-bugs'){
            these_bugs = critical_bugs;
        }
        else {
            these_bugs = regular_bugs;
        }

        /* Check if we are actually increasing the length */
        if(this.value.length >= these_bugs.length){
            var paste_length = this.value.length - these_bugs.length;
            var paste = this.value.substring(this.selectionStart - paste_length, this.selectionStart)
            var bug = paste.substr(paste.lastIndexOf('/') + 1);

            /* Make sure the obtained bug number is an integer */
            if(bug % 1 === 0){
                if(these_bugs.length == 0){
                    this.value = bug;
                }
                else {
                    this.value = these_bugs + ', ' + bug;
                }
            }
        }

        if(jQuery(this).attr('id') == 'edit-critical-bugs'){
            critical_bugs = this.value;
        }
        else {
            regular_bugs = this.value;
        }
    });
});