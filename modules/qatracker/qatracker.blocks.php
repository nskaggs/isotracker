<?php
/*
Copyright (C) 2008-2012 Stephane Graber <stgraber@ubuntu.com>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

function qatracker_block_info() {
    $items = array();

    $items['noticeboard'] = array(
        'info' => t("Testing tracker notice board"),
        'weight' => -1,
        'status' => 1,
        'region' => 'highlighted',
        'visibility' => BLOCK_VISIBILITY_LISTED,
        'cache' => DRUPAL_CACHE_PER_PAGE,
        'pages' => implode("\n", array("qatracker", "qatracker/milestones", "qatracker/milestones/*")),
    );

    $items['filtering'] = array(
        'info' => t("Testing tracker filters"),
        'weight' => -1,
        'status' => 1,
        'region' => 'sidebar_first',
        'visibility' => BLOCK_VISIBILITY_LISTED,
        'cache' => DRUPAL_CACHE_PER_PAGE,
        'pages' => implode("\n", array("qatracker", "qatracker/milestones",
                                        "qatracker/milestones/*/builds",
                                        "qatracker/milestones/*/history")),
    );

    return $items;
}

function qatracker_block_view($delta = 0) {
    switch($delta) {
        case "noticeboard":
            $item = array(
                'subject' => t("Notice board"),
                'content' => qatracker_block_noticeboard(),
            );
            return $item;
        break;

        case "filtering":
            $item = array(
                'subject' => t("Filters"),
                'content' => qatracker_block_filters(),
            );
            return $item;
        break;

        default:
            return;
        break;
    }
}

function qatracker_block_noticeboard() {
    $site = qatracker_get_current_site();

    if (!$site) {
        return;
    }

    if (arg(3) == "builds" && arg(4)) {
        $query = db_select('qatracker_build_milestone');
        $query->addField('qatracker_build_milestone', 'note', 'value');
        $query->condition('qatracker_build_milestone.milestoneid', arg(2));
        $query->condition('qatracker_build_milestone.buildid', arg(4));
        $result = $query->execute();
        $record = $result->fetchField();
    }
    else {
        $record = array_key_exists("noticeboard", $site->options) ? $site->options['noticeboard'] : "";
    }

    if (!$record) {
        return;
    }

    return array("#type" => "markup", "#markup" => check_markup($record, "filtered_html"));
}

function qatracker_block_filters() {
    $site = qatracker_get_current_site();

    if (!$site) {
        return;
    }

    # Javscript to filter the main page
    drupal_add_js("modules/qatracker/misc/jquery.cookies.2.2.0.min.js");
    drupal_add_js("
jQuery(document).ready(function(){
    jQuery('.qatracker_block_filters_filter[value=\"status_archived\"]').removeAttr('checked');

    function hide_table(child) {
        if(jQuery(child).is('table')) {
            if (jQuery(child).is(':hidden')) {
                jQuery(child).addClass('producthidden')
            }
            else {
                jQuery(child).removeClass('producthidden')
            }

            return;
        }

        jQuery(child).each(function() {
            parent=jQuery(this).parents('table')[0];
            tbody=jQuery(parent).children('tbody')[0];

            if (jQuery(parent).hasClass('producthidden')) {
                return;
            }

            if (jQuery(tbody).children('tr').filter(function() {
                    return jQuery(this).css('display') !== 'none';
                }).length == 0) {
                jQuery(parent).hide();
            }
            else {
                jQuery(parent).show();
            }
        })
    }

    function save_cookie() {
        cookie = '0';
        jQuery('.qatracker_block_filters_filter').each(function() {
            id = jQuery(this).val();
            state = jQuery(this).is(':checked');
            if (id == 'status_archived') {
                if (state == 1) {
                    cookie+=','+id
                }
            }
            else {
                if (state == 0) {
                    cookie+=','+id
                }
            }
        })
        expiry=new Date();
        expiry.setDate(expiry.getDate()+365);
        jQuery.cookies.set('qatracker_filters', cookie, {expiresAt: expiry});
    }

    jQuery('.qatracker_block_filters_filter').click(function() {
        id = jQuery(this).val();
        state = jQuery(this).is(':checked');
        jQuery('.qatracker_filter_'+id).toggle(state);
        hide_table('.qatracker_filter_'+id);
        save_cookie();
    })

    jQuery('.qatracker_block_filters_toggle').click(function() {
        state = jQuery(this).hasClass('checked');
        jQuery(this).toggleClass('checked');
        classes = jQuery(this).attr('class').split(' ');

        jQuery('.qatracker_block_filters_filter.'+classes[1]).each(function() {
            checkbox = jQuery(this);
            if (checkbox.is(':checked') && state == false) {
                checkbox.removeAttr('checked');
            }
            else if (!checkbox.is(':checked') && state == true) {
                checkbox.attr('checked', 'checked');
            }
        })

        jQuery('.qatracker_block_filters_filter').each(function() {
            id = jQuery(this).val();
            state = jQuery(this).is(':checked');
            jQuery('.qatracker_filter_'+id).toggle(state);
            hide_table('.qatracker_filter_'+id);
        })
        save_cookie();
    })

    if (jQuery.cookies.get('qatracker_filters')) {
        cookie = jQuery.cookies.get('qatracker_filters').split(',');
        jQuery.each(cookie, function() {
            if (this == 'status_archived') {
                jQuery('.qatracker_block_filters_filter[value=\"'+this+'\"]').attr('checked', 'checked');
            }
            else {
                jQuery('.qatracker_block_filters_filter[value=\"'+this+'\"]').removeAttr('checked');
            }
        })
    }

    jQuery('.qatracker_block_filters_filter').each(function() {
        id = jQuery(this).val();
        state = jQuery(this).is(':checked');
        jQuery('.qatracker_filter_'+id).toggle(state);
        hide_table('.qatracker_filter_'+id);
    })
});
        ", 'inline');

    $items = array();

    $query = db_select('qatracker_product_family');
    $query->fields('qatracker_product_family', array('id', 'title'));
    $query->orderBy('qatracker_product_family.weight', 'DESC');
    $query->orderBy('qatracker_product_family.title', 'ASC');
    $query->condition('qatracker_product_family.siteid', $site->id);
    $result = $query->execute();
    $options = array();

    $mode = "milestone";
    if (count(arg()) > 2) {
        $mode = "build";
    }

    if ($mode == "build") {
        # Product filtering
        $items[] = array(
            '#type' => 'markup',
            '#markup' => '<b style="cursor:pointer; font-weight:bold;" title="'.t("Toggle").'" class="qatracker_block_filters_toggle qatracker_block_filters_product">'.t("Products").'</b>',
            '#suffix' => '<br />',
        );

        foreach ($result as $record) {
            $items[] = array(
                '#type' => 'markup',
                '#prefix' => '<label class="option"><input type="checkbox" class="qatracker_block_filters_filter qatracker_block_filters_product form-checkbox" value="family_'.$record->id.'" checked="checked"/>',
                '#markup' => ' '.$record->title,
                '#suffix' => '</label><br />',
            );
        }
        $items[] = array(
            '#type' => 'markup',
            '#markup' => '<br />',
        );
    }

    # Status filtering
    $items[] = array(
        '#type' => 'markup',
        '#markup' => '<b style="cursor:pointer; font-weight:bold;" title="'.t("Toggle").'" class="qatracker_block_filters_toggle qatracker_block_filters_status">'.t("Status").'</b>',
        '#suffix' => '<br />',
    );

    if ($mode == "build") {
        $status = array(
            'untested' => t("Untested"),
            'partial' => t("Partial"),
            'tested' => t("Tested"),
        );
    }
    else {
        $status = array(
            'testing' => t("Testing"),
            'released' => t("Released"),
            'archived' => t("Archived")
        );
    }

    foreach ($status as $stid => $stname) {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<label class="option"><input type="checkbox" class="qatracker_block_filters_filter qatracker_block_filters_status form-checkbox" value="status_'.$stid.'" checked="checked"/>',
            '#markup' => ' '.$stname,
            '#suffix' => '</label><br />',
        );
    }

    return $items;
}

?>
