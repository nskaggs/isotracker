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

function qatracker_xmlrpc_testcases_get_list($productid, $seriesid, $status) {
    global $qatracker_testsuite_testcase_status;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    $query = db_select('qatracker_testsuite_product');
    $query->leftjoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testsuiteid = qatracker_testsuite_product.testsuiteid');
    $query->leftjoin('qatracker_testsuite', 'qatracker_testsuite', 'qatracker_testsuite.id = qatracker_testsuite_product.testsuiteid');
    $query->leftjoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_testsuite_testcase.testcaseid');
    $query->fields('qatracker_testcase', array('id', 'title', 'link'));
    $query->fields('qatracker_testsuite_testcase', array('status', 'weight'));
    $query->addField('qatracker_testsuite', 'id', 'suiteid');
    $query->addField('qatracker_testsuite', 'title', 'suite');
    $query->condition('qatracker_testsuite_product.productid', $productid);
    $query->condition('qatracker_testsuite_product.milestone_seriesid', $seriesid);
    $query->condition('qatracker_testsuite_testcase.status', $status, "IN");
    $result = $query->execute();

    $testcases = array();
    foreach ($result as $record) {
        $testcases[] = array(
            'id' => $record->id,
            'title' => $record->title,
            'link' => $record->link,
            'status' => $record->status,
            'status_string' => $qatracker_testsuite_testcase_status[$record->status],
            'suite' => $record->suiteid,
            'suite_string' => $record->suite,
            'weight' => $record->weight,
        );
    }

    return $testcases;
}
?>
