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

function qatracker_xmlrpc_series_get_list($status) {
    global $qatracker_milestone_series_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    $query = db_select('qatracker_milestone_series');
    $query->fields('qatracker_milestone_series', array('id', 'title', 'status'));
    $query->condition('qatracker_milestone_series.siteid', $site->id);
    $query->condition('qatracker_milestone_series.status', $status, "IN");
    $result = $query->execute();

    $series = array();
    foreach ($result as $record) {
        $series[] = array(
            'id' => $record->id,
            'title' => $record->title,
            'status' => $record->status,
            'status_string' => $qatracker_milestone_series_status[$record->status],
        );
    }

    return $series;
}

function qatracker_xmlrpc_series_get_manifest($seriesid, $status) {
    global $qatracker_milestone_series_manifest_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    $query = db_select('qatracker_milestone_series_manifest');
    $query->leftjoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_milestone_series_manifest.productid');
    $query->fields('qatracker_milestone_series_manifest', array('id', 'productid', 'contact', 'status'));
    $query->fields('qatracker_product', array('title'));
    $query->condition('qatracker_milestone_series_manifest.seriesid', $seriesid);
    $query->condition('qatracker_milestone_series_manifest.status', $status, "IN");
    $result = $query->execute();

    $series = array();
    foreach ($result as $record) {
        $series[] = array(
            'id' => $record->id,
            'contact' => $record->contact,
            'productid' => $record->productid,
            'product_title' => $record->title,
            'status' => $record->status,
            'status_string' => $qatracker_milestone_series_manifest_status[$record->status],
        );
    }

    return $series;
}
?>
