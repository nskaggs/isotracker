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

function qatracker_xmlrpc_builds_add($productid, $milestoneid, $version, $note, $notify) {
    global $user;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return False;
    }

    # Check that the user is authenticated and has admin access
    if (!qatracker_xmlrpc_security($site->adminrole)) {
        return False;
    }

    # Check that the product is indeed valid for that instance
    # and that the product is active
    $query = db_select('qatracker_product');
    $query->fields('qatracker_product', array('id'));
    $query->condition('qatracker_product.id', $productid);
    $query->condition('qatracker_product.siteid', $site->id);
    $query->condition('qatracker_product.status', 0);
    $result = $query->execute();
    $record = $result->fetch();

    if (!$record) {
        return False;
    }

    # Exactly the Same check but for the milestone
    $query = db_select('qatracker_milestone');
    $query->fields('qatracker_milestone', array('id'));
    $query->condition('qatracker_milestone.id', $milestoneid);
    $query->condition('qatracker_milestone.siteid', $site->id);
    $query->condition('qatracker_milestone.status', 0);
    $result = $query->execute();
    $record = $result->fetch();

    if (!$record) {
        return False;
    }

    qatracker_builds_add(array($productid), $milestoneid, $version, $note, $notify, $user->uid);
    return True;
}

function qatracker_xmlrpc_builds_get_list($milestoneid, $status) {
    global $qatracker_build_milestone_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    $query = db_select('qatracker_build_milestone');
    $query->fields('qatracker_build', array('id', 'productid', 'version'));
    $query->fields('qatracker_build_milestone', array('userid', 'note', 'date', 'status'));
    $query->leftjoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_build_milestone.buildid');
    $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
    $query->condition('qatracker_build_milestone.status', $status, 'IN');
    $result = $query->execute();

    $builds = array();
    foreach ($result as $record) {
        $account = user_load($record->userid);
        $username = "unknown";
        if ($account) {
            $username = $account->name;
        }

        $builds[] = array(
            'id' => $record->id,
            'productid' => $record->productid,
            'userid' => $record->userid,
            'username' => $username,
            'version' => $record->version,
            'note' => $record->note,
            'date' => $record->date,
            'status' => $record->status,
            'status_string' => $qatracker_build_milestone_status[$record->status],
        );
    }

    return $builds;
}
?>
