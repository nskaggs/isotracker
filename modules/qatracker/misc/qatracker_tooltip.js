jQuery(document).ready(function(){
    /**
     *  Make sure bug tooltips fit the viewport 
     */
    var header_height = 150;

    jQuery('.blacklink').mouseover(function(){
        box_position = jQuery(this).closest('.qawebsite_balloonleft').offset();
        box_height = jQuery(this).closest('.qawebsite_balloonleft').children('div').height();
        box_width = jQuery(this).closest('.qawebsite_balloonleft').children('div').width();

        if((box_position.top + box_height) > (jQuery(window).height() + jQuery(window).scrollTop())){
            jQuery(this).closest('.qawebsite_balloonleft').children('div').css('top', '-' + ( box_height + 16 ) + 'px');
        }

        if(box_position.left < box_width){
            jQuery(this).closest('.qawebsite_balloonleft').children('div').css('left', '20px');
            jQuery(this).closest('.qawebsite_balloonleft').children('div').css('width', box_width );
        }
    });
});