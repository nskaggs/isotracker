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

function qatracker_xmlrpc_rebuilds_get_list($status) {
    global $qatracker_rebuild_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    if (!array_key_exists("rebuilds_allowed", $site->options) || $site->options['rebuilds_allowed'] == 0) {
        return array();
    }

    $query = db_select('qatracker_rebuild');
    $query->fields('qatracker_rebuild', array('id', 'seriesid', 'productid', 'milestoneid', 'requestedby', 'requestedat', 'changedby', 'changedat', 'status'));
    $query->leftJoin('qatracker_milestone_series', 'qatracker_milestone_series', 'qatracker_milestone_series.id=qatracker_rebuild.seriesid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id=qatracker_rebuild.productid');
    $query->leftJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id=qatracker_rebuild.milestoneid');
    $query->addField('qatracker_milestone_series', 'title', 'series_title');
    $query->addField('qatracker_product', 'title', 'product_title');
    $query->addField('qatracker_milestone', 'title', 'milestone_title');
    $query->condition('qatracker_product.siteid', $site->id);
    $query->condition('qatracker_rebuild.status', $status, "IN");
    $result = $query->execute();

    $series = array();
    foreach ($result as $record) {
        # Resolv requestedby name
        $requestedbyname = "";
        if ($record->requestedby) {
            $account = user_load($record->requestedby);
            $requestedbyname = "unknown";
            if ($account) {
                $requestedbyname = $account->name;
            }
        }

        # Resolv changedby name
        $changedbyname = "";
        if ($record->changedby) {
            $account = user_load($record->changedby);
            $changedbyname = "unknown";
            if ($account) {
                $changedbyname = $account->name;
            }
        }

        $series[] = array(
            'id' => $record->id,
            'seriesid' => $record->seriesid,
            'series_title' => $record->series_title,
            'productid' => $record->productid,
            'product_title' => $record->product_title,
            'milestoneid' => $record->milestoneid,
            'milestone_title' => $record->milestone_title,
            'requestedby' => $record->requestedby,
            'requestedby_name' => $requestedbyname,
            'requestedat' => $record->requestedat,
            'changedby' => $record->changedby,
            'changedby_name' => $changedbyname,
            'changedat' => $record->changedat,
            'status' => $record->status,
            'status_string' => $qatracker_rebuild_status[$record->status],
        );
    }

    return $series;
}

function qatracker_xmlrpc_rebuilds_update_status($rebuildid, $status) {
    global $user, $qatracker_rebuild_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return False;
    }

    if (!array_key_exists("rebuilds_allowed", $site->options) || $site->options['rebuilds_allowed'] == 0) {
        return False;
    }

    # Check that the user is authenticated and has admin access
    if (!qatracker_xmlrpc_security($site->adminrole)) {
        return False;
    }

    # Check that the status provided is valid
    if (!array_key_exists($status, $qatracker_rebuild_status)) {
        return False;
    }

    # Get the record
    $query = db_select('qatracker_rebuild');
    $query->addField('qatracker_product', 'siteid', 'siteid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id=qatracker_rebuild.productid');
    $query->condition('qatracker_rebuild.id', $rebuildid);
    $record = $query->execute()->fetch();

    # Check that it's linked with the right site
    if (!$record || $record->siteid != $site->id) {
        return False;
    }

    $query = db_update('qatracker_rebuild');
    $query->fields(array(
                'status' => $status,
                'changedby' => $user->uid,
                'changedat' => date("Y-m-d H:i:s")));
    $query->condition('qatracker_rebuild.id', $rebuildid);
    $query->execute();

    return True;
}

?>
