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

function qatracker_get_current_site() {
    # Return site object of current site or Null if none match
    $query = db_select('qatracker_site');
    $query->fields('qatracker_site', array('id', 'subdomain', 'title', 'userrole', 'adminrole', 'testcaserole'));
    $query->condition('qatracker_site.subdomain', $_SERVER['HTTP_HOST']);
    $result = $query->execute();
    $site = $result->fetch();

    if (!$site) {
        return;
    }

    # Get all site options
    $query = db_select('qatracker_site_setting');
    $query->fields('qatracker_site_setting', array('option', 'value'));
    $query->condition('qatracker_site_setting.siteid', $site->id);
    $result = $query->execute();
    $options = $result->fetchAllKeyed(0);

    $site->options = $options;
    return $site;
}

function qatracker_tooltip($side, $link, $title, $text) {
    drupal_add_css('modules/qawebsite/misc/qawebsite_tooltip.css');
    return "
        <div class=\"qawebsite_balloon".$side."\">
            <div>$text</div>
            <a href=\"$link\">$title</a>
        </div>";
}

function qatracker_bug_tooltip($bug, $side) {
    drupal_add_css('modules/qawebsite/misc/qawebsite_tooltip.css');

    # If too long, wrap the title to 75 characters line
    $title = wordwrap($bug->title, 75, "<br />");

    # Generate the icon
    if ($bug->maximportance == 1) {
        $logo = "badbug.png";
    }
    else {
        $logo = "bug.png";
    }

    # FIXME: Need to be changed to work when Drupal is installed in a sub-directory
    $icon = "<img src='/modules/qatracker/misc/".$logo."' alt='".$bug->originalbug."' />";

    # Note for duplicates
    $duplicate = "";
    if ($bug->bugnumber != $bug->originalbug) {
        $duplicate = "<br />".t("(master bug of duplicate: %bug)", array("%bug" => $bug->originalbug));
    }

    # Generate the tooltip
    if (!$bug->title) {
        return "
            <div class=\"qawebsite_balloon".$side."\">
                <div>
                    <b>".t(
                            "No information about this bug (#%bugnumber)",
                            array(
                                "%bugnumber" => $bug->originalbug
                            )
                        )."
                    </b>
                    <br />
                    ".t("Bug information are updated every 5 minutes.")."
                </div>
                <a class=\"blacklink\" rel=\"external\" href=\"http://launchpad.net/bugs/".$bug->originalbug."\">$icon</a>
            </div>";
    }
    else {
        return "
            <div class=\"qawebsite_balloon".$side."\">
                <div>
                    <b>".$title." (#".$bug->bugnumber.")</b>
                    ".$duplicate."
                    <br />
                    <table>
                        <tr>
                            <td style='width:0'><b>".t("In:")."</b></td>
                            <td>".$bug->product."</td>
                        </tr>
                        <tr>
                            <td style='width:0'><b>".t("Status:")."</b></td>
                            <td>".$bug->status."</td>
                        </tr>
                        <tr>
                            <td style='width:0'><b>".t("Importance:")."</b></td>
                            <td>".$bug->importance."</td>
                        </tr>
                        <tr>
                            <td style='width:0'><b>".t("Assignee:")."</b></td>
                            <td>".$bug->assignee."</td>
                        </tr>
                    </table>
                    <i>".t(
                            "%reportedcount reports, %commentscount comments, %subscriberscount subscribers, %duplicatescount duplicates",
                            array(
                                "%reportedcount" => $bug->reportedcount,
                                "%commentscount" => $bug->commentscount,
                                "%subscriberscount" => $bug->subscriberscount,
                                "%duplicatescount" => $bug->duplicatescount
                            )
                        )."
                    </i>
                </div>
                <a class=\"blacklink\" rel=\"external\" href=\"http://launchpad.net/bugs/".$bug->bugnumber."\">$icon</a>
            </div>";
    }
}

function qatracker_site_header($site) {
    $form = array();
    if (!$site) {
        $form[] = array(
            '#type' => 'markup',
            '#prefix' => '<b>',
            '#markup' => t('You are currently on an invalid subdomain. Please go to a valid testing tracker instance.'),
            '#suffix' => '</b>',
        );
    }
    else {
        $form[] = array(
            '#type' => 'markup',
            '#prefix' => '<b>',
            '#markup' => t('You are currently on: @title', array('@title' => $site->title)),
            '#suffix' => '</b>',
        );
    }
    return $form;
}

function qatracker_build_stats($seriesid, $buildid, $productid, $testcaseid = Null) {
    # Return statistics for a given build as an associative array

    # Create the root of the tree
    $stats = array();
    $stats['testcases'] = array();
    $stats['bugs'] = array();

    # Get the list of valid testcases
    $query = db_select('qatracker_testsuite_product');
    $query->leftjoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testsuiteid = qatracker_testsuite_product.testsuiteid');
    $query->leftjoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_testsuite_testcase.testcaseid');
    $query->fields('qatracker_testcase', array('id'));
    $query->fields('qatracker_testsuite_testcase', array('status'));
    $query->condition('qatracker_testsuite_product.productid', $productid);
    $query->condition('qatracker_testsuite_product.milestone_seriesid', $seriesid);
    $query->condition('qatracker_testsuite_testcase.status', 1, '<>');
    if ($testcaseid !== Null) {
        $query->condition('qatracker_testcase.id', $testcaseid);
    }
    $result = $query->execute();

    $testcases = array();
    # Count the testcases and generate the tree
    foreach ($result as $record) {
        if (!array_key_exists($record->status, $stats['testcases'])) {
            $stats['testcases'][$record->status] = array(
                'total_count' => 0,
                'done_count' => 0,
                'results' => array(),
            );
        }

        $stats['testcases'][$record->status]['total_count'] += 1;
        $testcases[$record->id] = $record->status;
    }

    if (count($testcases) == 0) {
        return $stats;
    }

    # Get the result data for all the testcases above
    $query = db_select('qatracker_result');
    $query->fields('qatracker_result', array('id', 'testcaseid', 'result'));
    $query->condition('qatracker_result.buildid', $buildid);
    $query->condition('qatracker_result.testcaseid', array_keys($testcases), 'IN');
    $query->condition('qatracker_result.status', 0);
    if ($testcaseid !== Null) {
        $query->condition('qatracker_result.testcaseid', $testcaseid);
    }
    $result = $query->execute();

    $done_testcases = array();
    $results = array();

    # Update the array with the number of tested testcases
    foreach ($result as $record) {
        $status = $testcases[$record->testcaseid];

        if ($record->result != 2 && !in_array($record->testcaseid, $done_testcases)) {
            $stats['testcases'][$status]['done_count'] += 1;
            $done_testcases[] = $record->testcaseid;
        }

        if (!array_key_exists($record->result, $stats['testcases'][$status]['results'])) {
            $stats['testcases'][$status]['results'][$record->result] = 0;
        }
        $stats['testcases'][$status]['results'][$record->result] += 1;

        # Needed to optimize the search for bugs below
        $results[] = $record->id;
    }

    if (count($results) == 0) {
        return $stats;
    }

    # Build a list of unique bugs affecting this build
    $stats['bugs'] = array();
    $query = db_select('qatracker_launchpad_bug');
    $query->fields('qatracker_launchpad_bug', array(
        'bugnumber',
        'title',
        'product',
        'status',
        'importance',
        'assignee',
        'commentscount',
        'duplicatescount',
        'subscriberscount',
        'lastchange'
    ));
    $query->addField('qatracker_bug', 'bugnumber', 'originalbug');
    $query->addExpression('COUNT(qatracker_bug.bugnumber)', 'reportedcount');
    $query->addExpression('MAX(qatracker_bug.bugimportance)', 'maximportance');
    $query->rightjoin('qatracker_bug', 'qatracker_bug', 'qatracker_bug.bugnumber = qatracker_launchpad_bug.originalbug');
    $query->groupBy('
        qatracker_bug.bugnumber,
        qatracker_launchpad_bug.bugnumber,
        qatracker_launchpad_bug.title,
        qatracker_launchpad_bug.product,
        qatracker_launchpad_bug.status,
        qatracker_launchpad_bug.importance,
        qatracker_launchpad_bug.assignee,
        qatracker_launchpad_bug.commentscount,
        qatracker_launchpad_bug.duplicatescount,
        qatracker_launchpad_bug.subscriberscount,
        qatracker_launchpad_bug.lastchange'
    );
    $query->condition('qatracker_bug.resultid', $results, 'IN');
    $query->orderBy('qatracker_bug.bugnumber', 'ASC');
    $stats['bugs'] = $query->execute()->fetchAll();

    return $stats;
}

function qatracker_builds_add($products, $milestoneid, $version, $note, $notify, $userid) {
    $site = qatracker_get_current_site();

    # Get the seriesid
    $query = db_select('qatracker_milestone');
    $query->condition('qatracker_milestone.id', $milestoneid);
    $query->fields('qatracker_milestone', array('seriesid'));
    $seriesid = $query->execute()->fetchField();

    foreach ($products as $productid) {
        # Mark any pending rebuild as done
        if (array_key_exists("rebuilds_allowed", $site->options) && $site->options['rebuilds_allowed'] == 1) {
            $query = db_update('qatracker_rebuild');
            $query->fields(array('status' => 4));
            $query->condition('qatracker_rebuild.productid', $productid);
            $query->condition('qatracker_rebuild.seriesid', $seriesid);
            $query->condition(db_or()->condition('qatracker_rebuild.milestoneid', $milestoneid)->condition('qatracker_rebuild.milestoneid', NULL));
            $query->condition('qatracker_rebuild.status', array(0, 1, 2, 3), 'IN');
            $query->execute();
        }


        # Look for the previous build
        $query = db_select('qatracker_build');
        $query->fields('qatracker_build', array('id', 'version'));
        $query->addField('qatracker_build_milestone', 'status', 'status');
        $query->leftjoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_build.id');
        $query->condition('qatracker_build.productid', $productid);
        $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
        $query->condition('qatracker_build_milestone.status', array(0, 1, 4), 'IN');
        $result = $query->execute();
        $old_record = $result->fetch();

        # Build already exists, make sure it's active
        if ($old_record && $old_record->version == $version) {
            $query = db_update('qatracker_build_milestone');
            $query->condition('qatracker_build_milestone.buildid', $old_record->id);
            $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
            $query->fields(array(
                            'status' => 0,
                            'note' => $note
            ));
            $query->execute();
            continue;
        }

        # If we have an active build for this product, disable it
        if ($old_record) {
            # Disable old build
            $query = db_update('qatracker_build_milestone');
            $query->condition('qatracker_build_milestone.buildid', $old_record->id);
            $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
            $query->fields(array('status' => 3));
            $query->execute();
        }


        $query = db_select('qatracker_build');
        $query->fields('qatracker_build', array('id'));
        $query->leftjoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid=qatracker_build.id');
        $query->condition('qatracker_build.productid', $productid);
        $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
        $query->condition('qatracker_build.version', $version);
        $result = $query->execute();
        $record = $result->fetch();

        if ($record) {
            # Current version already existed, so disable the current one
            # and restore the old one
            $action = "restore";

            # Restore old build (update the userid, date and note)
            $query = db_update('qatracker_build_milestone');
            $query->condition('qatracker_build_milestone.buildid', $record->id);
            $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
            $query->fields(array(
                'userid' => $userid,
                'note' => $note,
                'date' => date("Y-m-d H:i:s"),
                'status' => 0
            ));
            $query->execute();

            $buildid = $record->id;
            watchdog("qatracker",
                t("Restored older build (@version) with ID: @buildid"),
                array('@buildid' => $buildid, '@version' => $version),
                WATCHDOG_NOTICE,
                l(t("Go to build"), "qatracker/milestones/".$milestoneid."/builds/".$buildid."/testcases")
            );
        }
        else {
            # No build to restore, just add a new one
            $action = "new";

            # Get seriesid for milestone
            $query = db_select('qatracker_milestone');
            $query->fields('qatracker_milestone', array('seriesid'));
            $query->condition('qatracker_milestone.id', $milestoneid);
            $result = $query->execute();
            $record = $result->fetch();
            $seriesid = $record->seriesid;

            # Check for a build with the same version, product and series but in another milestone
            $query = db_select('qatracker_build');
            $query->fields('qatracker_build', array('id'));
            $query->leftjoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid=qatracker_build.id');
            $query->leftjoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
            $query->condition('qatracker_milestone.seriesid', $seriesid);
            $query->condition('qatracker_build.productid', $productid);
            $query->condition('qatracker_build.version', $version);
            $result = $query->execute();
            $record = $result->fetch();

            if ($record) {
                # Reuse existing build
                $buildid = $record->id;
            }
            else {
                # Add the new build
                $query = db_insert('qatracker_build');
                $query->fields(array(
                    'productid' => $productid,
                    'version' => $version,
                ));
                $buildid = $query->execute();
            }


            $query = db_insert('qatracker_build_milestone');
            $query->fields(array(
                'buildid' => $buildid,
                'milestoneid' => $milestoneid,
                'userid' => $userid,
                'note' => $note,
                'date' => date("Y-m-d H:i:s"),
                'status' => 0,
            ));
            $query->execute();

            watchdog("qatracker",
                t("Added build (@version) with ID: @buildid"),
                array('@buildid' => $buildid, '@version' => $version),
                WATCHDOG_NOTICE,
                l(t("Go to build"), "qatracker/milestones/".$milestoneid."/builds/".$buildid."/testcases")
            );

            # Check if the current product is in the manifest
            $query = db_select('qatracker_milestone_series_manifest');
            $query->fields('qatracker_milestone_series_manifest', array('productid'));
            $query->condition('qatracker_milestone_series_manifest.seriesid', $seriesid);
            $query->condition('qatracker_milestone_series_manifest.productid', $productid);
            $query->condition('qatracker_milestone_series_manifest.status', 0);
            $result = $query->execute();
            $record = $result->fetch();

            # If not in the manifest, go the next build
            if (!$record) {
                continue;
            }

            # Now check if we have different milestones for the same series
            # that's marked as autofill and where we should also be pushing the new build
            $query = db_select('qatracker_milestone');
            $query->fields('qatracker_milestone', array('id'));
            $query->condition('qatracker_milestone.seriesid', $seriesid);
            $query->condition('qatracker_milestone.id', $milestoneid, "<>");
            $query->condition('qatracker_milestone.autofill', 1);
            $query->condition('qatracker_milestone.status', 0);
            $result = $query->execute();

            foreach ($result as $record) {
                qatracker_builds_add(array($productid), $record->id, $version, $note, $notify, $userid);
            }
        }

        if ($notify) {
            # Send the e-mail only if old_version != new_version
            # and handle cases where new_version < old_version
            qatracker_notify("builds_new", array(
                "action" => $action,
                "buildid" => $buildid,
                "milestoneid" => $milestoneid,
                "productid" => $productid
            ));
        }
    }
}

function qatracker_notify($action, $param) {
    switch($action) {
        case "builds_new":
            $action = $param['action'];
            $buildid = $param['buildid'];
            $milestoneid = $param['milestoneid'];
            $productid = $param['productid'];

            # Get information on the milestone
            $query = db_select('qatracker_milestone');
            $query->fields('qatracker_milestone', array('id', 'title', 'notify', 'seriesid'));
            $query->condition('qatracker_milestone.id', $milestoneid);
            $milestone = $query->execute()->fetch();

            # Get information on the build
            $query = db_select('qatracker_build_milestone');
            $query->fields('qatracker_build_milestone', array('note'));
            $query->fields('qatracker_build', array('id', 'version'));
            $query->leftjoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_build_milestone.buildid');
            $query->condition('qatracker_build_milestone.buildid', $buildid);
            $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
            $build = $query->execute()->fetch();

            # Get information on the product
            $query = db_select('qatracker_product');
            $query->fields('qatracker_product', array('id', 'title', 'type'));
            $query->condition('qatracker_product.id', $productid);
            $product = $query->execute()->fetch();

            # Get a list of active testcase
            $query = db_select('qatracker_testsuite_product');
            $query->leftjoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testsuiteid = qatracker_testsuite_product.testsuiteid');
            $query->leftjoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_testsuite_testcase.testcaseid');
            $query->fields('qatracker_testcase', array('id', 'title'));
            $query->condition('qatracker_testsuite_product.productid', $productid);
            $query->condition('qatracker_testsuite_product.milestone_seriesid', $milestone->seriesid);
            $query->condition('qatracker_testsuite_testcase.status', 1, "<>");
            $testcases = $query->execute()->fetchAllKeyed();

            # FIXME: For now, if a milestone is marked as don't-notify, just exit
            if ($milestone->notify == 0) {
                return;
            }

            if (count($testcases) == 0) {
                return;
            }

            # Get list of subscribers
            $query = db_select('qatracker_user_subscription');
            $query->fields('qatracker_user_subscription', array('userid', 'testcaseid'));
            $query->condition('qatracker_user_subscription.testcaseid', array_keys($testcases), 'IN');
            $query->condition('qatracker_user_subscription.productid', $productid);
            $result = $query->execute();

            $subscribers = array();
            foreach ($result as $record) {
                if (!array_key_exists($record->userid, $subscribers)) {
                    $subscribers[$record->userid] = array();
                }
                $subscribers[$record->userid][] = $record->testcaseid;
            }

            foreach ($subscribers as $subscriber => $tests) {
                $account = user_load($subscriber);

                $user_tests = array();
                foreach ($tests as $testcaseid) {
                    $record = $testcases[$testcaseid];
                    $user_tests[] = $record;
                }

                $params = array();
                $params['account'] = $account;
                $params['build'] = $build;
                $params['product'] = $product;
                $params['milestone'] = $milestone;
                $params['testcases'] = $user_tests;
                drupal_mail("qatracker", "builds_new", $account->mail, user_preferred_language($account), $params);
            }
        break;
    }
}

function qatracker_acl($acl, $roles, $site = NULL) {
    global $user;

    # First check if the user is a allowed by a global ACL
    if (user_access($acl)) {
        return TRUE;
    }

    if (!$site) {
        # Get the current site
        $site = qatracker_get_current_site();
    }
    if (!$site) {
        return False;
    }

    # Build a list of rids for the current user
    $user_rids = array();
    foreach ($user->roles as $rid => $entry) {
        $user_rids[] = $rid;
    }

    # Check if the user has one of the needed roles
    if (in_array("user", $roles) && in_array($site->userrole, $user_rids)) {
        return True;
    }

    if (in_array("admin", $roles) && in_array($site->adminrole, $user_rids)) {
        return True;
    }

    if (in_array("testcase", $roles) && in_array($site->testcaserole, $user_rids)) {
        return True;
    }

    if (in_array("product", $roles) && $user_rids) {
        $query = db_select('qatracker_product');
        $query->fields('qatracker_product', array('id'));
        $query->condition('qatracker_product.siteid', $site->id);
        $query->condition('qatracker_product.ownerrole', $user_rids, 'IN');
        if ($query->execute()->rowCount() != 0) {
            return True;
        }
    }

    return FALSE;
}

function qatracker_acl_product($productid) {
    global $user;

    if (qatracker_acl("administer site configuration", array("admin"))) {
        return True;
    }
    else {
        $query = db_select('qatracker_product');
        $query->leftJoin('role', 'role', 'role.rid=qatracker_product.ownerrole');
        $query->fields('role', array('name'));
        $query->condition('qatracker_product.id', $productid);
        $role = $query->execute()->fetchField();

        if (!$role || !in_array($role, $user->roles)) {
            return False;
        }
    }
    return True;
}

function qatracker_filter_by_date() {
    $items = array();

    drupal_add_library('system','ui.datepicker');
    drupal_add_css("
div.ui-datepicker{
 font-size:10px;
}
", "inline");
    drupal_add_js("
  jQuery(document).ready(function() {
    jQuery('.datepicker').datepicker({ autoSize: true });
  });
", "inline");

    $items['datefilter'] = array(
        '#type' => 'fieldset',
        '#title' => t('Filter results by time'),
        '#collapsible' => true,
        '#collapsed' => true,
    );

    $date_from = new DateTime();
    $date_from->sub(new DateInterval('P31D'));
    
    $items['datefilter']['date_from'] = array(
        '#type'  => 'textfield',
        '#title' => t('Shows result from'),
        '#attributes' => array('class' => array('datepicker'), 'value' => $date_from->format('m/d/Y')),
        '#description' => t('Date in MM/DD/YYYY format.')
    );

    $date_to = new DateTime();
    
    $items['datefilter']['date_to'] = array(
        '#type'  => 'textfield',
        '#title' => t('Show results to'),
        '#attributes' => array('class' => array('datepicker'), 'value' => $date_to->format('m/d/Y')),
        '#description' => t('Date in MM/DD/YYYY format.')
    );

    $items['datefilter']['submit'] = array(
        '#type'  => 'submit',
        '#submit' => array('qatracker_filter_by_date_submit'),
        '#value' => t('Filter results'),
    );

    return $items;
}

function qatracker_filter_by_date_submit($form, &$form_state) {
    $form_state['rebuild'] = true;
    return $form;
}

?>
