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

function qatracker_report_testers() {
    global $qatracker_milestone_status;
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    $items = array_merge($items, qatracker_filter_by_date());

    # Getting all the entries
    $query = db_select('qatracker_milestone');
    $query->addExpression('COUNT(qatracker_result.id)', 'count');
    $query->addField('users', 'name', 'name');
    $query->leftjoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.milestoneid = qatracker_milestone.id');
    $query->leftjoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_build_milestone.buildid');
    $query->leftjoin('qatracker_result', 'qatracker_result', 'qatracker_result.buildid = qatracker_build.id');
    $query->leftjoin('users', 'users', 'users.uid = qatracker_result.reporterid');
    if (arg(3) != "top20") {
        $query->fields('qatracker_milestone', array('title', 'status'));
        $query->condition('qatracker_milestone.status', array(0,1), 'IN');
        $query->orderBy('qatracker_milestone.status', 'ASC');
        $query->orderBy('qatracker_milestone.id', 'DESC');
        $query->groupBy('qatracker_milestone.id, qatracker_milestone.title, qatracker_milestone.status, users.name');
    }
    else {
        $query->range(0, 20);
        $query->groupBy('users.name');
    }
    if(array_key_exists("date_from", $_POST) && $_POST['date_from']) {
        $query->condition('qatracker_result.date', DateTime::createFromFormat('m/d/Y', $_POST['date_from'])->format("Ymd"), ">=");
    }
    if(array_key_exists("date_to", $_POST) && $_POST['date_to']) {
        $query->condition('qatracker_result.date', DateTime::createFromFormat('m/d/Y', $_POST['date_to'])->format("Ymd"), "<=");
    }
    $query->orderBy('count', 'DESC');
    $query->condition('qatracker_milestone.siteid', $site->id);
    $query->condition('qatracker_result.status', 1, '<>');
    $result = $query->execute();

    # And generating the table
    $rows = array();
    $milestone = "";
    $status = "";
    $position = 1;

    if (!function_exists("new_table")) {
        function new_table($rows, $milestone, $status) {
            if (arg(3) != "top20") {
                $title = $milestone." (".$status.")";
            }
            else {
                $title = t("Top 20 (of all time)");
            }
            return array(
                '#theme' => 'table',
                '#header' => array(
                    array('data' => "", 'style' => 'width:0em;'),
                    array('data' => $title),
                    array('data' => t("Results"), 'style' => 'width:8em;'),
                ),
                '#rows' => $rows,
            );
        }
    }

    foreach ($result as $record) {
        if (arg(3) != "top20" && $milestone && $milestone != $record->title) {
            $items[] = new_table($rows, $milestone, $status);
            $rows = array();
            $position = 1;
        }

        # FIXME: Integrate with SSO to get a link to the profile instead of
        # hardcoding Launchpad
        $rows[] = array(
            $position,
            l($record->name, "http://launchpad.net/~".$record->name),
            $record->count,
        );
        if (arg(3) != "top20") {
            $milestone = $record->title;
            $status = $qatracker_milestone_status[$record->status];
        }
        $position++;
    }
    $items[] = new_table($rows, $milestone, $status);

    return $items;
}
?>
