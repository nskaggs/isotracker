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

function qatracker_user_milestones() {
    global $qatracker_milestone_status;

    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Get the list of active milestones
    $query = db_select('qatracker_milestone');
    $query->fields('qatracker_milestone', array('id', 'title', 'status', 'seriesid'));
    $query->addField('qatracker_milestone_series', 'title', 'series');
    $query->leftjoin('qatracker_milestone_series', 'qatracker_milestone_series', 'qatracker_milestone_series.id=qatracker_milestone.seriesid');
    $query->condition('qatracker_milestone.siteid', $site->id);
    $query->condition('qatracker_milestone_series.status', 0);
    $query->orderBy('qatracker_milestone_series.title', 'DESC');
    $query->orderBy('qatracker_milestone.status', 'ASC');
    $query->orderBy('qatracker_milestone.id', 'DESC');
    $result = $query->execute();

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => t("Please choose a milestone in the list below:"),
        '#suffix' => '</p>',
    );

    function new_table($rows, $series, $seriesid) {
        return array(
            '#theme' => 'table',
            '#header' => array(
                array(
                    'data' => t("Milestones for '!series' series (!manifest | !testsuites)",
                                array(
                                    '!series' => $series,
                                    '!manifest' => l(t("product manifest"), "qatracker/series/".$seriesid."/manifest", array("attributes" => array('style' => 'text-decoration:underline;color:#ff8246'))),
                                    '!testsuites' => l(t("testsuites"), "qatracker/series/".$seriesid."/testsuites", array("attributes" => array('style' => 'text-decoration:underline;color:#ff8246'))),
                                )),
                ),
                array('data' => t('Status'), 'style' => 'width:0'),
            ),
            '#rows' => $rows,
        );
    }

    $rows = array();
    $series = NULL;
    $seriesid = NULL;

    foreach($result as $milestone) {
        # Group by series by creating a new table everytime it changes
        if ($series !== Null && $series != $milestone->series) {
            $items[] = new_table($rows, $series, $seriesid);
            $rows = array();
        }

        $rows[] = array(
            'data' => array(
                        l($milestone->title, "qatracker/milestones/".$milestone->id."/builds"),
                        $qatracker_milestone_status[$milestone->status],
            ),
            'class' => array(
                'qatracker_filter_status_'.strtolower($qatracker_milestone_status[$milestone->status]),
            )
        );

        $series = $milestone->series;
        $seriesid = $milestone->seriesid;
    }

    if ($rows) {
        $items[] = new_table($rows, $series, $seriesid);
    }

    return $items;
}

?>
