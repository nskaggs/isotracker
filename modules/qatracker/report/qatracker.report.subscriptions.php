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

function qatracker_report_subscriptions() {
    global $qatracker_testsuite_testcase_status;
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Getting all the entries
    $query = db_select('qatracker_testcase');
    $query->fields('qatracker_testcase', array('id', 'title', 'link'));
    $query->fields('qatracker_testsuite_testcase', array('status'));
    $query->addField('qatracker_product', 'title', 'product');
    $query->addField('qatracker_product', 'id', 'productid');
    $query->addField('qatracker_product', 'familyid', 'familyid');
    $query->leftjoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testcaseid = qatracker_testcase.id');
    $query->leftjoin('qatracker_testsuite_product', 'qatracker_testsuite_product', 'qatracker_testsuite_product.testsuiteid = qatracker_testsuite_testcase.testsuiteid');
    $query->leftjoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_testsuite_product.productid');
    $query->orderBy('qatracker_product.title', 'ASC');
    $query->orderBy('qatracker_testsuite_testcase.status', 'ASC');
    $query->orderBy('qatracker_testsuite_testcase.weight', 'ASC');
    $query->condition('qatracker_product.siteid', $site->id);
    $query->condition('qatracker_product.status', 0);
    $query->condition('qatracker_testsuite_testcase.status', 1, '<>');
    $query->distinct();
    $result = $query->execute();

    # And generating the table
    $rows = array();
    $family = -1;

    function new_table($rows) {
        return array(
            '#theme' => 'table',
            '#header' => array(
                array('data' => t('Product'), 'style' => 'width:30em'),
                array('data' => t('Testcase')),
                array('data' => t('Priority'), 'style' => 'width:8em'),
                array('data' => t('Subscribers'), 'style' => 'width:8em'),
            ),
            '#rows' => $rows,
        );
    }

    foreach ($result as $record) {
        if ($family != -1 && $family != $record->familyid) {
            $items[] = new_table($rows);
            $rows = array();
        }

        $query = db_select('qatracker_user_subscription');
        $query->leftjoin('users', 'users', 'users.uid = qatracker_user_subscription.userid');
        $query->fields('users', array('name'));
        $query->condition('qatracker_user_subscription.testcaseid', $record->id);
        $query->condition('qatracker_user_subscription.productid', $record->productid);
        $users_result = $query->execute();

        $count = $users_result->rowCount();

        if ($count == 0) {
            $count = t("None");
        }
        else {
            $count = qatracker_tooltip("left", url("qatracker/reports/subscriptions/".$record->productid."/".$record->id), $count, implode("<br />", $users_result->fetchCol()));
        }

        $rows[] = array(
            $record->product,
            l($record->title, "qatracker/testcases/".$record->id."/info"),
            $qatracker_testsuite_testcase_status[$record->status],
            $count,
        );
        $family = $record->familyid;
    }
    $items[] = new_table($rows);

    return $items;
}

function qatracker_report_subscriptions_testcase() {
    global $qatracker_testsuite_testcase_status;
    $site = qatracker_get_current_site();

    $productid = arg(3);
    $testcaseid = arg(4);

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Get information on the product and testcase
    $query = db_select('qatracker_testcase');
    $query->fields('qatracker_testcase', array('title'));
    $query->fields('qatracker_testsuite_testcase', array('status'));
    $query->addField('qatracker_product', 'title', 'product');
    $query->leftjoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testcaseid = qatracker_testcase.id');
    $query->leftjoin('qatracker_testsuite_product', 'qatracker_testsuite_product', 'qatracker_testsuite_product.testsuiteid = qatracker_testsuite_testcase.testsuiteid');
    $query->leftjoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_testsuite_product.productid');
    $query->condition('qatracker_product.id', $productid);
    $query->condition('qatracker_testcase.id', $testcaseid);
    $query->distinct();
    $result = $query->execute();

    if ($result->rowCount() != 1) {
        drupal_not_found();
        exit;
    }

    $result = $result->fetch();

    # Get the list of subscribers
    $query = db_select('qatracker_user_subscription');
    $query->leftjoin('users', 'users', 'users.uid = qatracker_user_subscription.userid');
    $query->leftJoin('qatracker_result', 'qatracker_result', 'qatracker_result.testcaseid = qatracker_user_subscription.testcaseid AND qatracker_result.reporterid = qatracker_user_subscription.userid');
    $query->leftJoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_result.buildid AND qatracker_build.productid = qatracker_user_subscription.productid');
    $query->fields('users', array('name'));
    $query->addExpression('COUNT(qatracker_result.id)', 'count');
    $query->condition('qatracker_user_subscription.testcaseid', $testcaseid);
    $query->condition('qatracker_user_subscription.productid', $productid);
    $query->groupBy('users.name');
    $query->orderBy('count', 'DESC');
    $users_result = $query->execute();

    drupal_set_title(t("Subscribers for !testcase in !product (!status)", array(
        "!testcase" => ucfirst($result->title),
        "!product" => $result->product,
        "!status" => $qatracker_testsuite_testcase_status[$result->status],
    )));

    $rows = array();
    foreach ($users_result as $entry) {
        #FIXME: hardcoded launchpad.net
        $rows[] = array(l($entry->name, "http://launchpad.net/~".$entry->name), $entry->count);
    }

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
                        t('Subscriber name'),
                        t('Number of results submitted')
                    ),
        '#rows' => $rows,
    );

    return $items;
}
?>
