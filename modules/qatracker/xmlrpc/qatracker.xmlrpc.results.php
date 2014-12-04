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

function qatracker_xmlrpc_results_add($buildid, $testcaseid, $result, $comment, $hardware, $bugs) {
    global $user;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return -1;
    }

    # Check that the user is authenticated and has user access
    if (!qatracker_xmlrpc_security($site->userrole)) {
        return -1;
    }

    # Check that the build, product, milestone and testcase are all valid
    $query = db_select('qatracker_build_milestone');
    $query->fields('qatracker_build', array('id'));
    $query->leftJoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_build_milestone.buildid');
    $query->leftJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->leftJoin('qatracker_testsuite_product', 'qatracker_testsuite_product', 'qatracker_testsuite_product.productid = qatracker_build.productid AND qatracker_testsuite_product.milestone_seriesid = qatracker_milestone.seriesid');
    $query->rightJoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testsuiteid = qatracker_testsuite_product.testsuiteid');
    $query->rightJoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_testsuite_testcase.testcaseid');
    $query->condition('qatracker_build_milestone.buildid', $buildid);
    $query->condition('qatracker_testsuite_testcase.testcaseid', $testcaseid);
    $query->condition('qatracker_build_milestone.status', array(0, 4), "IN");
    $query->condition('qatracker_product.status', 0);
    $query->condition('qatracker_milestone.status', 0);
    $query->condition('qatracker_testsuite_testcase.status', array(0,2,3), "IN");
    $check = $query->execute();
    $record = $check->fetch();
    if (!$record) {
        return -1;
    }

    # Check that all the bugs are valid
    foreach ($bugs as $bugnumber => $bugimportance) {
        if (!ctype_digit($bugnumber)) {
            return -1;
        }
        if ($bugimportance != 0 && $bugimportance != 1) {
            return -1;
        }
    }

    # Grab the latest revision of the testcase
    $query = db_select('qatracker_testcase_revision');
    $query->fields('qatracker_testcase_revision', array('id'));
    $query->condition('qatracker_testcase_revision.testcaseid', $testcaseid);
    $query->orderBy('qatracker_testcase_revision.id', 'DESC');
    $revisionid = $query->execute()->fetchField();
    if (!$revisionid) {
        $revisionid = NULL;
    }

    # Add the result
    $query = db_insert('qatracker_result');
    $query->fields(array(
        'reporterid' => $user->uid,
        'buildid' => $buildid,
        'testcaseid' => $testcaseid,
        'revisionid' => $revisionid,
        'date' => date("Y-m-d H:i:s"),
        'result' => $result,
        'comment' => $comment,
        'hardware' => $hardware,
        'status' => 0,
    ));
    $resultid = $query->execute();

    if (!$resultid) {
        return -1;
    }

    # Add all the bugs
    foreach ($bugs as $bugnumber => $bugimportance) {
        $query = db_insert('qatracker_bug');
        $query->fields(array(
            'resultid' => $resultid,
            'bugnumber' => $bugnumber,
            'bugimportance' => $bugimportance,
        ));
        $query->execute();
    }

    return $resultid;
}

function qatracker_xmlrpc_results_delete($resultid) {
    global $user;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return False;
    }

    # Check that the user is authenticated and has user access
    $admin = qatracker_xmlrpc_security($site->adminrole);
    if (!qatracker_xmlrpc_security($site->userrole)) {
        return False;
    }


    # Get the result
    $query = db_select('qatracker_result');
    $query->fields('qatracker_result', array('id'));
    $query->leftJoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_result.buildid');
    $query->leftJoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_build.id');
    $query->leftJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->leftJoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_result.testcaseid');
    $query->condition('qatracker_result.id', $resultid);
    if (!$admin) {
        $query->condition('qatracker_result.reporterid', $user->uid);
    }
    $query->condition('qatracker_build_milestone.status', array(0, 4), "IN");
    $query->condition('qatracker_product.status', 0);
    $query->condition('qatracker_milestone.status', 0);
    $result = $query->execute()->fetch();

    if (!$result) {
        return False;
    }

    # Remove the result
    $query = db_update('qatracker_result');
    $query->fields(array(
        'status' => '1'
    ));
    $query->condition('qatracker_result.id', $resultid);
    $query->execute();

    return True;
}

function qatracker_xmlrpc_results_update($resultid, $result, $comment, $hardware, $bugs) {
    global $user;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return False;
    }

    # Check that the user is authenticated and has user access
    if (!qatracker_xmlrpc_security($site->userrole)) {
        return False;
    }

    # Check that the build, product, milestone and testcase are all valid
    $query = db_select('qatracker_result');
    $query->fields('qatracker_result', array('id', 'testcaseid'));
    $query->leftJoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_result.buildid');
    $query->leftJoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_build.id');
    $query->leftJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->rightJoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_result.testcaseid');
    $query->condition('qatracker_result.id', $resultid);
    $query->condition('qatracker_build_milestone.status', array(0, 4), "IN");
    $query->condition('qatracker_product.status', 0);
    $query->condition('qatracker_milestone.status', 0);
    $query->condition('qatracker_result.status', 1, '<>');
    if (!qatracker_xmlrpc_security($site->adminrole)) {
        $query->condition('qatracker_result.reporterid', $user->uid);
    }
    $check = $query->execute();
    $record = $check->fetch();
    if (!$record) {
        return False;
    }

    # Check that all the bugs are valid
    foreach ($bugs as $bugnumber => $bugimportance) {
        if (!ctype_digit($bugnumber)) {
            return False;
        }
        if ($bugimportance != 0 && $bugimportance != 1) {
            return False;
        }
    }

    # Grab the latest revision of the testcase
    $query = db_select('qatracker_testcase_revision');
    $query->fields('qatracker_testcase_revision', array('id'));
    $query->condition('qatracker_testcase_revision.testcaseid', $record->testcaseid);
    $query->orderBy('qatracker_testcase_revision.id', 'DESC');
    $revisionid = $query->execute()->fetchField();
    if (!$revisionid) {
        $revisionid = NULL;
    }

    # Update the result
    $query = db_update('qatracker_result');
    $query->fields(array(
        'revisionid' => $revisionid,
        'changedby' => $user->uid,
        'lastchange' => date("Y-m-d H:i:s"),
        'result' => $result,
        'comment' => $comment,
        'hardware' => $hardware,
        'status' => 0,
    ));
    $query->condition('qatracker_result.id', $resultid);
    $result = $query->execute();

    if (!$result) {
        return False;
    }

    # Wipe all the existing bugs
    $query = db_delete('qatracker_bug');
    $query->condition('resultid', $resultid);
    $query->execute();

    # Update all the bugs
    foreach ($bugs as $bugnumber => $bugimportance) {
        $query = db_insert('qatracker_bug');
        $query->fields(array(
            'resultid' => $resultid,
            'bugnumber' => $bugnumber,
            'bugimportance' => $bugimportance,
        ));
        $query->execute();
    }

    return True;
}

function qatracker_xmlrpc_results_get_list($buildid, $testcaseid, $status) {
    global $qatracker_result_status, $qatracker_result_result;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return array();
    }

    if (!is_array($status) || count($status) == 0) {
        return array();
    }

    $query = db_select('qatracker_result');
    $query->fields('qatracker_result', array('id', 'reporterid', 'revisionid', 'date', 'result', 'comment', 'hardware', 'lastchange', 'changedby', 'status'));
    $query->condition('qatracker_result.buildid', $buildid);
    $query->condition('qatracker_result.testcaseid', $testcaseid);
    $query->condition('qatracker_result.status', $status, 'IN');
    $result = $query->execute();

    $results = array();
    foreach ($result as $record) {
        # Resolv reporter name
        $account = user_load($record->reporterid);
        $reportername = "unknown";
        if ($account) {
            $reportername = $account->name;
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

        $query = db_select('qatracker_bug');
        $query->fields('qatracker_bug', array('bugnumber', 'bugimportance'));
        $query->condition('qatracker_bug.resultid', $record->id);
        $bugs = $query->execute()->fetchAllKeyed();

        $results[] = array(
            'id' => $record->id,
            'revisionid' => $record->revisionid,
            'reporterid' => $record->reporterid,
            'reportername' => $reportername,
            'date' => $record->date,
            'result' => $record->result,
            'result_string' => $qatracker_result_result[$record->result],
            'comment' => $record->comment,
            'hardware' => $record->hardware,
            'lastchange' => $record->lastchange,
            'changedby' => $record->changedby,
            'changedbyname' => $changedbyname,
            'status' => $record->status,
            'status_string' => $qatracker_result_status[$record->status],
            'bugs' => $bugs,
        );
    }

    return $results;
}
?>
