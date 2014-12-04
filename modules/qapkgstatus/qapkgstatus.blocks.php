<?php
/*
Copyright (C) 2008-2011 Stephane Graber <stgraber@ubuntu.com>

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

function qapkgstatus_block_info() {
    $items = array();

    $items['stats'] = array(
        'info' => t("Package statistics and links"),
        'weight' => -1,
        'status' => 1,
        'region' => 'sidebar_first',
        'visibility' => BLOCK_VISIBILITY_LISTED,
        'cache' => DRUPAL_CACHE_PER_PAGE,
        'pages' => implode("\n", array("qapkgstatus/*")),
    );

    $items['packages'] = array(
        'info' => t("List of all available packages"),
        'weight' => 0,
        'status' => 1,
        'region' => 'sidebar_first',
        'visibility' => BLOCK_VISIBILITY_LISTED,
        'cache' => DRUPAL_CACHE_PER_PAGE,
        'pages' => implode("\n", array("qapkgstatus", "qapkgstatus/*")),
    );

    return $items;
}

function qapkgstatus_block_view($delta = 0) {
    switch($delta) {
        case "packages":
            $item = array(
                'subject' => t("Packages"),
                'content' => qapkgstatus_block_packages(),
            );
            return $item;
        break;

        case "stats":
            $item = array(
                'subject' => t("Package details"),
                'content' => qapkgstatus_block_stats(),
            );
            return $item;
        break;

        default:
            return;
        break;
    }
}

function qapkgstatus_block_packages() {
    # This is the block showing the list of all packages

    # A bit of javascript to collapse the categories
    drupal_add_js("
jQuery(document).ready(function(){
        jQuery('.qapkgstatus_block_packages_collapsed').css('cursor','pointer');
        jQuery('.qapkgstatus_block_packages_collapsed').parent().children('div').toggle();

        //
        jQuery('.qapkgstatus_block_packages_collapsed').click(function() {
            jQuery(this).parent().children('div').toggle();
        })
});
        ", 'inline');

    # Generate a sorted list of categories from the xml files
    $categories=array();
    foreach (glob(drupal_realpath("public:///qapkgstatus")."/*.xml") as $path) {
        $xml=qapkgstatus_getxml($path);
        if ($xml == -1 or $xml == null) {
            continue;
        }

        $attributes=$xml->attributes();
        $category=trim($attributes['category']);
        $name=trim($attributes['name']);
        $categories[$category][]=$name;
    }
    ksort($categories);

    # And the packages inside these categories
    $items = array();
    foreach ($categories as $category => $content) {
        sort($content);

        $children = array();
        foreach ($content as $app) {
            $children[] = array(
                'data' => l($app, "qapkgstatus/".$app),
            );
        }
        $items[] = array(
            'data' => strtr("<b class='qapkgstatus_block_packages_collapsed'>@category</b>", array('@category' => $category)),
            'children' => $children,
        );
    }

    return theme("item_list", array('items' => $items));
}

function qapkgstatus_block_stats() {
    # This is the block showing all the stats

    $package = arg(1);
    $items = array();

    if ($package) {
        $input = qapkgstatus_getxml($package);

        # Skip invalid xml
        if ($input == -1) {
            return;
        }

        # Iterate through all the blocks
        foreach ($input->block as $item) {
            $items[] = array(
                "#type" => "markup",
                "#markup" => theme('table', qapkgstatus_gentable($item)),
            );
        }
    }
    return $items;
}
?>
