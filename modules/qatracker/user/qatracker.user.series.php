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

function qatracker_user_series_manifest() {
    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    # Parse the URL
    $seriesid = arg(2);

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Fetch details on the testcase
    $query = db_select('qatracker_milestone_series');
    $query->fields('qatracker_milestone_series', array('title'));
    $query->condition('qatracker_milestone_series.id', $seriesid);
    $series = $query->execute()->fetch();

    if (!$series) {
        drupal_not_found();
        exit;
    }

    drupal_set_title(t("Product manifest for '!series' series", array(
        "!series" => $series->title,
    )));

    # Full manifest
    if (arg(4) == "full") {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br />',
            '#markup' => l(t("Only show entries that are part of the current milestone"), 'qatracker/series/'.$seriesid.'/manifest', array('html' => TRUE)),
        );
    }
    else {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br />',
            '#markup' => l(t("Also show entries that aren't part of the current milestone"), 'qatracker/series/'.$seriesid.'/manifest/full', array('html' => TRUE)),
        );
    }

    # Get all the products on the manifest
    $query = db_select('qatracker_milestone_series_manifest');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_milestone_series_manifest.productid');
    $query->leftJoin('qatracker_product_family', 'qatracker_product_family', 'qatracker_product_family.id = qatracker_product.familyid');
    $query->fields('qatracker_milestone_series_manifest', array('contact'));
    $query->fields('qatracker_product', array('title'));
    $query->addfield('qatracker_product_family', 'title', 'family');
    $query->orderBy('qatracker_product_family.title', 'ASC');
    $query->orderBy('qatracker_product.title', 'ASC');
    $query->condition('qatracker_milestone_series_manifest.seriesid', $seriesid);
    if (arg(4) != "full") {
        $query->condition('qatracker_milestone_series_manifest.status', 0);
    }
    $products = $query->execute()->fetchAll();

    if (!$products) {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br /><br /><b>',
            '#markup' => t("There are no product on the manifest for this series."),
            '#suffix' => '</b>',
        );
        return $items;
    }

    function new_table($rows) {
        return array(
            '#theme' => 'table',
            '#header' => array(
                array('data' => t('Title')),
                array('data' => t('Contact'), 'style' => 'width:20em'),
            ),
            '#rows' => $rows,
        );
    }

    $family = NULL;
    foreach ($products as $record) {
        if ($family && $family != $record->family) {
            $items[] = new_table($rows);
            $rows = array();
        }

        $rows[] = array(
            $record->title,
            $record->contact,
        );
        $family = $record->family;
    }
    $items[] = new_table($rows);

    return $items;
}

function qatracker_user_series_testsuites() {
    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    global $qatracker_testsuite_testcase_status;

    # Parse the URL
    $seriesid = arg(2);

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Fetch details on the testcase
    $query = db_select('qatracker_milestone_series');
    $query->fields('qatracker_milestone_series', array('title'));
    $query->condition('qatracker_milestone_series.id', $seriesid);
    $series = $query->execute()->fetch();

    if ($seriesid != 0) {
        if (!$series) {
            drupal_not_found();
            exit;
        }

        drupal_set_title(t("Testsuites for '!series' series", array(
            "!series" => $series->title,
        )));

        # Link to the list of orphaned testsuites
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br />',
            '#markup' => l(t("Access the list of orphaned testsuites"), 'qatracker/series/0/testsuites', array('html' => TRUE)),
        );
    }
    else {
        drupal_set_title(t("Orphaned testsuites (not linked to a series)"));
    }

    # Get all the products on the manifest
    $query = db_select('qatracker_testsuite');
    $query->addfield('qatracker_testsuite', 'title', 'testsuite');
    $query->addfield('qatracker_testcase', 'title', 'testcase');
    $query->addfield('qatracker_testcase', 'id', 'testcaseid');
    $query->addfield('qatracker_testsuite_testcase', 'status', 'status');
    $query->leftjoin('qatracker_testsuite_product', 'qatracker_testsuite_product', 'qatracker_testsuite_product.testsuiteid = qatracker_testsuite.id');
    $query->leftjoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_testsuite_product.productid');
    $query->leftjoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testsuiteid = qatracker_testsuite.id');
    $query->leftjoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_testsuite_testcase.testcaseid');
    if ($seriesid == 0) {
        $query->condition('qatracker_testsuite_product.milestone_seriesid', NULL);
    }
    else {
        $query->condition('qatracker_testsuite_product.milestone_seriesid', $seriesid);
    }
    $query->condition(db_or()->condition('qatracker_product.status', 1, '<>')->condition('qatracker_product.status', NULL));
    $query->condition('qatracker_testsuite_testcase.status', 1, '<>');
    $query->orderBy('qatracker_testsuite.title', 'ASC');
    $query->orderBy('qatracker_testsuite_testcase.status', 'ASC');
    $query->orderBy('qatracker_testcase.title', 'ASC');
    $query->distinct();
    $testcases = $query->execute()->fetchAll();

    if (!$testcases) {
        $markup = t("There are no testsuites linked to this series.");
        if ($seriesid == 0) {
            $markup = t("There are no orphaned testsuites.");
        }

        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br /><br /><b>',
            '#markup' => $markup,
            '#suffix' => '</b>',
        );
        return $items;
    }

    function new_table($rows, $testsuite) {
        return array(
            '#theme' => 'table',
            '#header' => array(
                array('data' => t("Testcase title in '!testsuite'", array('!testsuite' => $testsuite))),
                array('data' => t("Testcase status"), 'style' => 'width:20em'),
            ),
            '#rows' => $rows,
        );
    }

    $testsuite = NULL;
    foreach ($testcases as $record) {
        if ($testsuite && $testsuite != $record->testsuite) {
            $items[] = new_table($rows, $testsuite);
            $rows = array();
        }

        $rows[] = array(
            l($record->testcase, "qatracker/testcases/".$record->testcaseid."/info"),
            $qatracker_testsuite_testcase_status[$record->status],
        );
        $testsuite = $record->testsuite;
    }
    $items[] = new_table($rows, $testsuite);

    return $items;
}
?>
