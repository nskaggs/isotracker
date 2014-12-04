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

function qatracker_xmlrpc_milestones_get_list($status) {
    global $qatracker_milestone_notify, $qatracker_milestone_autofill, $qatracker_milestone_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    $query = db_select('qatracker_milestone');
    $query->fields('qatracker_milestone', array('id', 'title', 'notify', 'autofill', 'status', 'seriesid'));
    $query->addField('qatracker_milestone_series', 'title', 'series');
    $query->leftJoin('qatracker_milestone_series', 'qatracker_milestone_series', 'qatracker_milestone_series.id=qatracker_milestone.seriesid');
    $query->condition('qatracker_milestone.siteid', $site->id);
    $query->condition('qatracker_milestone.status', $status, 'IN');
    $result = $query->execute();

    $milestones = array();
    foreach ($result as $record) {
        $milestones[] = array(
            'id' => $record->id,
            'title' => $record->title,
            'notify' => $record->notify,
            'notify_string' => $qatracker_milestone_notify[$record->notify],
            'autofill' => $record->status,
            'autofill_string' => $qatracker_milestone_autofill[$record->autofill],
            'status' => $record->status,
            'status_string' => $qatracker_milestone_status[$record->status],
            'series' => $record->seriesid,
            'series_string' => $record->series,
        );
    }

    return $milestones;
}
?>
