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

function qatracker_xmlrpc_bugs_get_list($milestoneid) {
    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    $query = db_select('qatracker_bug');
    $query->addExpression('MIN(qatracker_result.date)', 'earliest_report');
    $query->addExpression('GREATEST(MAX(qatracker_result.lastchange), MAX(qatracker_result.date))', 'latest_report');
    $query->addExpression('COUNT(qatracker_result)', 'count');
    $query->fields('qatracker_bug', array('bugnumber'));
    $query->leftJoin('qatracker_result', 'qatracker_result', 'qatracker_result.id = qatracker_bug.resultid');
    $query->leftJoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_result.buildid');
    $query->leftJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->condition('qatracker_result.status', 1, '<>');
    $query->condition('qatracker_milestone.siteid', $site->id);
    if ($milestoneid) {
        $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
    }
    $query->groupBy('qatracker_bug.bugnumber');
    $query->orderBy('qatracker_bug.bugnumber', 'DESC');
    $result = $query->execute();

    $bugs = array();
    foreach ($result as $record) {
        $bugs[] = array(
            'bugnumber' => $record->bugnumber,
            'earliest_report' => $record->earliest_report,
            'latest_report' => $record->latest_report,
            'count' => $record->count,
        );
    }

    return $bugs;
}
?>
