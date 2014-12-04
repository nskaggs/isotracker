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

function qatracker_xmlrpc_products_get_list($status) {
    global $qatracker_product_type, $qatracker_product_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    $query = db_select('qatracker_product');
    $query->leftJoin('qatracker_product_family', 'qatracker_product_family', 'qatracker_product_family.id = qatracker_product.familyid');
    $query->fields('qatracker_product', array('id', 'title', 'type', 'status'));
    $query->addField('qatracker_product_family', 'title', 'family');
    $query->condition('qatracker_product.siteid', $site->id);
    $query->condition('qatracker_product.status', $status, 'IN');
    $result = $query->execute();

    $products = array();
    foreach ($result as $record) {
        $products[] = array(
            'id' => $record->id,
            'title' => $record->title,
            'type' => $record->type,
            'type_string' => $qatracker_product_type[$record->type],
            'status' => $record->status,
            'status_string' => $qatracker_product_status[$record->status],
            'family' => $record->family,
        );
    }

    return $products;
}
?>
