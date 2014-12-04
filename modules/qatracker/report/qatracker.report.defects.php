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

function qatracker_report_defects() {
    $items = array();
    $arg = arg();

    if(count($arg) < 4) {
        $arg[3] = "all";
    }

    $noreport[] = array(
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => t("<b>There is no milestone being currently tested.</b><br />"),
        '#suffix' => '</p>',
    );

    $site = qatracker_get_current_site();
    if (!$site) {
        return $noreport;
    }

    $content = qatracker_buildreport($site, $arg[3]);
    if (!$content) {
        return $noreport;
    }

    return $content;
}

function qatracker_getbugs_for_release($siteid) {
    /* 
     * Get all the bugs for all the milestones of a release 
     * for a given siteid 
     */

    $result = db_query("
SELECT  DISTINCT m.title mtitle
       ,l.bugnumber
       ,l.product
       ,l.title btitle
       ,l.status
       ,l.importance 
       ,l.assignee
       ,l.commentscount
       ,l.duplicatescount
       ,l.subscriberscount
       ,m.id
       ,(CASE 
            WHEN l.importance = 'Critical' THEN 0
            WHEN l.importance = 'High' THEN 1
            WHEN l.importance = 'Medium' THEN 2
            WHEN l.importance = 'Low' THEN 3
            WHEN l.importance = 'Undecided' THEN 10
        ELSE 5 END
        ) importance_order
FROM qatracker_build b
JOIN qatracker_product p ON p.id = b.productid
JOIN qatracker_build_milestone bm ON bm.buildid = b.id
JOIN qatracker_milestone m ON bm.milestoneid = m.id
JOIN qatracker_testsuite_product tp ON tp.productid = b.productid AND tp.milestone_seriesid = m.seriesid
JOIN qatracker_testsuite_testcase tt ON tt.testsuiteid = tp.testsuiteid
JOIN qatracker_testcase t ON tt.testcaseid = t.id
JOIN qatracker_result r ON (r.testcaseid = t.id AND r.buildid = b.id)
JOIN qatracker_bug g ON g.resultid = r.id
JOIN qatracker_launchpad_bug l ON g.bugnumber = l.originalbug
WHERE m.siteid = :siteid
AND SUBSTRING(m.title, '^[^ ]+') IN (
    SELECT DISTINCT SUBSTRING(m2.title, '^[^ ]+') 
    FROM qatracker_milestone m2
    WHERE m2.status IN (0,1)
    AND m2.siteid = m.siteid
)
AND tt.status IN (0, 2)
ORDER by m.id DESC, importance_order, l.status", array(":siteid" => intval($siteid)));

    return $result->fetchAll();
}


function qatracker_buildreport($site, $filter) {
    /*
     * Build the defect report
     *
     * @param $site: A site object 
     * @param $filter: Type of bugs we are interested in
     *      all: All the reports
     *      closed: Only closed reports (see $closed array below)
     *      opened: Only opened reports (see $closed array below)
     *
     * TODO:
     *      Colorize the importance column like LP
     *      Nicer milestone rows
     */
    if (!$site) {
        return false;
    }

    $closed = array("Invalid", "Fix Released", "Won't Fix", "Expired", "Opinion");

    $siteid = $site->id;
    if(!is_numeric($siteid)){
        return false;
    }

    $header = array(
        array('data' => 'Bug #'),
        array('data' => 'Title'),
        array('data' => 'Affects'),
        array('data' => 'Status'),
        array('data' => 'Importance'),
        array('data' => 'Assignee'),
        array('data' => 'Com.'),
        array('data' => 'Sub.'),
        array('data' => 'Dup.'),
    );

    $bugs = qatracker_getbugs_for_release($siteid);

    $curr_milestone = "";
    $rows = array();
    foreach ($bugs as $record) {
        // Milestone changed
        if ( $curr_milestone != $record->mtitle ) {
            $curr_milestone = $record->mtitle;
            $rows[] = array(
                'data' => array( array (
                    'data' => "<b>".$curr_milestone."</b>",
                    'colspan' => 9,
                    'class' => 'rpt_tbl_milestone'
                ))
            );
        }

        // FIXME
        // Bad practice: move the filter to the query
        //
        $filter = strtolower($filter);
        if (   ($filter == 'all')
            or ($filter == 'closed' and in_array($record->status, $closed))
            or ($filter == 'opened' and !in_array($record->status, $closed))) 
        {
            $rows[] = array(
                'data' => array(
                    l($record->bugnumber,
                        'http://bugs.launchpad.net/bugs/' . $record->bugnumber),
                    $record->btitle,
                    str_replace(' (Ubuntu)', '', $record->product),
                    $record->status,
                    $record->importance,
                    str_replace('None', '-', $record->assignee),
                    intval($record->commentscount),
                    intval($record->subscriberscount),
                    intval($record->duplicatescount)
                )
            );
        }
    }

    $items = array();
    $items[] = array(
                '#theme' => 'table',
                '#header' => $header,
                '#rows'=> $rows,
                '#empty' => 'No bug reported...',
               );

    return ($items);
}

?>
