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

function qatracker_report_bugs() {
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    $items[] = array(
        '#type' => 'markup',
        '#markup' => '<br /><br />',
    );

    $items['qatracker_report_bugs']['bugnumber'] = array(
        '#type' => 'textfield',
        '#title' => t('Bug number'),
        '#description' => t('Bug number to search for'),
        '#default_value' => "",
    );
    $items['qatracker_report_bugs']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Search'),
    );

    return $items;
}

function qatracker_report_bugs_submit($form, &$form_state) {
    $bugnumber = $form['qatracker_report_bugs']['bugnumber']['#value'];
    $form_state['redirect'] = 'qatracker/reports/bugs/'.$bugnumber;
    return $form;
}

function qatracker_report_bugs_bugnumber() {
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    drupal_set_title(t("List of results for !bug (and its duplicates)", array("!bug" => l("bug #".arg(3), "http://bugs.launchpad.net/bugs/".arg(3)))), $output = PASS_THROUGH);

    # Getting all the entries
    $query = db_select('qatracker_launchpad_bug');
    $query->addField('qatracker_milestone', 'title', 'milestone');
    $query->addField('qatracker_product', 'title', 'product');
    $query->addField('qatracker_testcase', 'title', 'testcase');
    $query->addField('qatracker_milestone', 'id', 'milestoneid');
    $query->addField('qatracker_build', 'id', 'buildid');
    $query->addField('qatracker_build', 'version', 'version');
    $query->addField('qatracker_testcase', 'id', 'testcaseid');
    $query->distinct();
    $query->leftjoin('qatracker_bug', 'qatracker_bug', 'qatracker_bug.bugnumber = qatracker_launchpad_bug.originalbug');
    $query->leftjoin('qatracker_result', 'qatracker_result', 'qatracker_result.id = qatracker_bug.resultid');
    $query->leftjoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_result.buildid');
    $query->leftjoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->leftjoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_result.testcaseid');
    $query->leftjoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_build.id');
    $query->leftjoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->orderBy('qatracker_milestone.id', 'DESC');
    $query->orderBy('qatracker_product.title', 'DESC');
    $query->orderBy('qatracker_product.id', 'DESC');
    $query->orderBy('qatracker_testcase.title', 'DESC');
    $query->condition('qatracker_product.siteid', $site->id);
    $query->condition('qatracker_result.status', 1, '<>');
    $query->condition(db_or()->condition('qatracker_launchpad_bug.bugnumber', arg(3))->condition('qatracker_launchpad_bug.originalbug', arg(3)));
    $result = $query->execute();

    # And generating the table
    $rows = array();
    $milestone = -1;

    function new_table($rows) {
        return array(
            '#theme' => 'table',
            '#header' => array(
                array('data' => t('Milestone'), 'style' => 'width:10em'),
                array('data' => t('Product'), 'style' => 'width:20em'),
                array('data' => t('Version'), 'style' => 'width:8em'),
                array('data' => t('Testcase')),
                array('data' => t('Results'), 'style' => 'width:2em; text-align:center;'),
            ),
            '#rows' => $rows,
        );
    }

    foreach ($result as $record) {
        if ($milestone != -1 && $milestone != $record->milestoneid) {
            $items[] = new_table($rows);
            $rows = array();
        }

        # Link to results
        $results = l(
            theme(
                "image",
                array(
                    'path' => "/modules/qatracker/misc/test.png",
                    'alt' => t("Results")
                )
            ),
            "qatracker/milestones/".$record->milestoneid."/builds/".$record->buildid."/testcases/".$record->testcaseid."/results",
            array('html' => TRUE)
        );

        $rows[] = array(
            $record->milestone,
            $record->product,
            $record->version,
            $record->testcase,
            array('data' => $results, 'style' => 'text-align:center;'),
        );
        $milestone = $record->milestoneid;
    }
    $items[] = new_table($rows);

    return $items;
}
?>
