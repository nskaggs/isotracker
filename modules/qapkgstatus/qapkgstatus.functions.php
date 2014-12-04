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

function qapkgstatus_getxml($package) {
    # Simple XML loader function, doing a few basic checks
    # and returning -1 if something goes wrong

    if (file_exists($package)) {
        $path = $package;
    }
    else {
        $path = drupal_realpath("public:///qapkgstatus/".$package.".xml");
        if (!file_exists($path)) {
            return -1;
        }
    }

    $xml=new domDocument;
    $xml->load($path);
    if (!$xml) {
        return -1;
    }
    else {
        $input=simplexml_import_dom($xml);
        if (!$input) {
            return -1;
        }
        return $input;
    }
}

function qapkgstatus_gentable($node) {
    # Recursive function generating the table for the statistics block

    drupal_add_css('modules/qawebsite/misc/qawebsite_tooltip.css');

    $rows = array();
    foreach ($node as $item) {
        $item_attributes = $item->attributes();
        $cols = array(trim($item_attributes['name']));

        if ($item->metric) {
            foreach ($item->metric as $child)
            {
                $child_attributes = $child->attributes();
                $title = trim($child);
                if($child_attributes['balloon']) {
                    array_push($cols, "
    <div class=\"qawebsite_balloonright\">
        <div>
            ".$child_attributes['balloon']."
        </div>
    ".l("<b>$title</b>", $child_attributes['url'], array("html" => TRUE))."
    </div>");
                }
                else {
                    array_push($cols, l("<b>$title</b>", $child_attributes['url'], array("html" => TRUE)) );
                }
            }
        }
        elseif ($item->description) {
            # It's a sub-table, so call ourself with the sub-item
            $cols[0] = theme('table', qapkgstatus_gentable($item));
        }
        array_push($rows, $cols);
    }

    $attributes = $node->attributes();
    $table = array(
        "caption" => trim($attributes['name']),
        "rows" => $rows,
    );
    return $table;
}
?>
