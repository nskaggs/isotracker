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

function qatracker_user_builds() {
    # FIXME: Turn off caching for this page even though it's probably the slowest one to render.
    # This is done as data on this page is modified by outside scripts and not through
    # the page itself causing Drupal's cache never to be updated.
    # Proper cache invalidation should be implemented and then caching turned on for everyone
    drupal_page_is_cacheable(FALSE);

    $milestoneid=arg(2);
    $page=arg(3);

    # Fetch details on the milestone
    $query = db_select('qatracker_milestone');
    $query->fields('qatracker_milestone', array('id', 'title', 'status', 'seriesid'));
    $query->condition('qatracker_milestone.id', $milestoneid);
    $result = $query->execute();
    $milestone = $result->fetch();

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Check that the milestone indeed exists
    if (!$milestone) {
        drupal_not_found();
        exit;
    }

    # Manifest
    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<br />',
        '#markup' => l(t("Access the product manifest for this series"), 'qatracker/series/'.$milestone->seriesid.'/manifest', array('html' => TRUE)),
    );

    # Testsuites
    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<br />',
        '#markup' => l(t("Access the list of testsuites/testcases for this series"), 'qatracker/series/'.$milestone->seriesid.'/testsuites', array('html' => TRUE)),
    );

    # History
    if ($page != "history") {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br />',
            '#markup' => l(t("See removed and superseded builds too"), 'qatracker/milestones/'.$milestoneid.'/history', array('html' => TRUE)),
        );
    }
    else {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br />',
            '#markup' => l(t("Only see active builds"), 'qatracker/milestones/'.$milestoneid.'/builds', array('html' => TRUE)),
        );

        $items = array_merge($items, qatracker_filter_by_date());
    }

    # Check if we are in read-only mode
    if ($milestone->status != 0) {
        $admin = False;
        drupal_set_title(t("!milestone (archived)", array("!milestone" => $milestone->title)));
    }
    else {
        $admin = qatracker_acl("administer site configuration", array("admin", "product"), $site);
        drupal_set_title(t($milestone->title));
    }

    # Re-builds
    $scheduled_rebuilds = array();
    if (array_key_exists("rebuilds_allowed", $site->options) && $site->options['rebuilds_allowed'] == 1) {
        $query = db_select('qatracker_rebuild');
        $query->fields('qatracker_rebuild', array('productid'));
        $query->condition('qatracker_rebuild.seriesid', $milestone->seriesid);
        $query->condition('qatracker_rebuild.status', array(4, 5), 'NOT IN');
        $scheduled_rebuilds = $query->execute()->fetchCol();
    }

    # Getting all the entries
    $query = db_select('qatracker_build');
    $query->fields('qatracker_build', array('id', 'version', 'productid'));
    $query->addField('qatracker_product', 'title', 'title');
    $query->addField('qatracker_build_milestone', 'status', 'status');
    $query->addField('qatracker_product_family', 'id', 'family');
    $query->addField('qatracker_product_family', 'title', 'familytitle');
    $query->rightjoin('qatracker_product', 'qatracker_product', 'qatracker_build.productid = qatracker_product.id');
    $query->leftjoin('qatracker_product_family', 'qatracker_product_family', 'qatracker_product.familyid = qatracker_product_family.id');
    $query->leftjoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_build.id');
    $query->orderBy('qatracker_product_family.title', 'ASC');
    $query->orderBy('qatracker_product.title', 'ASC');
    $query->orderBy('qatracker_build_milestone.date', 'DESC');
    $query->condition('qatracker_product.siteid', $site->id);
    if ($page != "history") {
        $query->condition('qatracker_build_milestone.status', array(0, 1, 4), "IN");
    }
    else {
        if(array_key_exists('date_from', $_POST) && $_POST['date_from']) {
            $query->condition('qatracker_build_milestone.date', DateTime::createFromFormat('m/d/Y', $_POST['date_from'])->format('Ymd'), '>=');
        } else {
            $date_from = new DateTime();
            $date_from->sub(new DateInterval('P31D'));
            $query->condition('qatracker_build_milestone.date', $date_from->format('Ymd'), '>=');
        }
        
        if(array_key_exists('date_to', $_POST) && $_POST['date_to']) {
            $query->condition('qatracker_build_milestone.date', DateTime::createFromFormat('m/d/Y', $_POST['date_to'])->format('Ymd'), '<=');
        } else {
            $date_to = new DateTime();
            $query->condition('qatracker_build_milestone.date', $date_to->format('Ymd'), '<=');
        }
    }
    $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
    $result = $query->execute();

    # And generating the table
    $rows = array();
    $family = -1;
    $familytitle = NULL;

    if (!function_exists("new_table")) {
        function new_table($rows, $admin, $class, $family) {
            if ($family) {
                $product_column = t('Product (@family)', array("@family" => $family));
            }
            else {
                $product_column = t('Product');
            }

            $header = array(
                array('data' => '', 'style' => 'width:0'),
                array('data' => $product_column),
                array('data' => t('Mandatory'), 'style' => 'width:5em'),
                array('data' => t('Run Once'), 'style' => 'width:5em'),
                array('data' => t('Optional'), 'style' => 'width:5em'),
                array('data' => t('Bugs'), 'style' => 'width:10em; text-align:center;'),
                array('data' => t('Version'), 'style' => 'width:10em'),
            );

            if ($admin) {
                return array(
                    '#type' => "tableselect",
                    '#header' => $header,
                    '#options' => $rows,
                    '#empty' => t('No build available'),
                    '#attributes' => array('class' => $class),
                );
            }
            else {
                return array(
                    '#theme' => "table",
                    '#header' => $header,
                    '#rows' => $rows,
                    '#attributes' => array('class' => $class),
                );
            }
        }
    }

    foreach ($result as $record) {
        # Group by family by creating a new table everytime the family changes
        if ($family != -1 && $family != $record->family) {
            $items[] = new_table($rows, $admin, array("qatracker_filter_family_".$family), $familytitle);
            $rows = array();
        }

        # Get the stats for this build
        $stats = qatracker_build_stats($milestone->seriesid, $record->id, $record->productid);
        $total_done = 0;
        $total_total = 0;

        # Generate the testcase stats columns
        $cols = array();
        foreach (array(0, 2, 3) as $type) {
            $cols[$type] = "None";
            if (array_key_exists($type, $stats['testcases'])) {
                $test_done = $stats['testcases'][$type]['done_count'];
                $test_total = $stats['testcases'][$type]['total_count'];
                $total_done += $test_done;
                $total_total += $test_total;

                $test_fail = 0;
                if (array_key_exists(0, $stats['testcases'][$type]['results'])) {
                    $test_fail = $stats['testcases'][$type]['results'][0];
                }

                $test_pass = 0;
                if (array_key_exists(1, $stats['testcases'][$type]['results'])) {
                    $test_pass = $stats['testcases'][$type]['results'][1];
                }

                $test_running = 0;
                if (array_key_exists(2, $stats['testcases'][$type]['results'])) {
                    $test_running = $stats['testcases'][$type]['results'][2];
                }

                if ($test_done == $test_total && $test_fail == 0) {
                    # All passed (bold green)
                    $cols[$type] = "<b style='color:#006400;'>".$test_done."/".$test_total."</b>";
                }
                elseif ($test_done == $test_total && $test_pass == 0) {
                    # All failed (bold red)
                    $cols[$type] = "<b style='color:#641500;'>".$test_done."/".$test_total."</b>";
                }
                elseif ($test_done != $test_total && $test_running != 0) {
                    # Not done yet and some are running (light orange)
                    $cols[$type] = "<span style='color:#dd4814;'>".$test_done."/".$test_total."</span>";
                }
                elseif ($test_done == 0 && $test_running == 0) {
                    # No result yet (light blue)
                    $cols[$type] = "<span style='color:#000a8a;'>".$test_done."/".$test_total."</span>";
                }
                elseif ($test_fail != 0) {
                    # Some failed (light red)
                    $cols[$type] = "<span style='color:#641500;'>".$test_done."/".$test_total."</span>";
                }
                else {
                    # None failed but not complete yet (light green)
                    $cols[$type] = "<span style='color:#006400;'>".$test_done."/".$test_total."</span>";
                }

                if (array_key_exists(0, $stats['testcases'][$type]['results'])) {
                    $cols[$type] .= " <b style='color:#641500'>(".$stats['testcases'][$type]['results'][0].")</b>";
                }
            }
        }

        # Get an overall state
        if ($total_done == 0) {
            $filter_status = "untested";
        }
        elseif ($total_done == $total_total) {
            $filter_status = "tested";
        }
        else {
            $filter_status = "partial";
        }

        # Check if the product is listed for rebuild
        $rebuild = "";
        if (in_array($record->productid, $scheduled_rebuilds)) {
            $rebuild = t(" (re-building)");
        }

        # Generate the title based on the build status
        if ($record->status == 1) {
            # Rebuilding
            $title = array(
                'data' => l(
                    t("@title (disabled)$rebuild", array('@title' => $record->title)),
                    "qatracker/milestones/".$milestone->id."/builds/".$record->id."/testcases"
                ),
                'style' => "text-decoration:line-through;",
            );
        }
        elseif ($record->status == 2) {
            # Removed
            $title = array(
                'data' => l(
                    t("@title (removed)$rebuild", array('@title' => $record->title)),
                    "qatracker/milestones/".$milestone->id."/builds/".$record->id."/testcases"
                ),
                'style' => "text-decoration:line-through;",
            );
        }
        elseif ($record->status == 3) {
            # Superseded
            $title = array(
                'data' => l(
                    t("@title (superseded)$rebuild", array('@title' => $record->title)),
                    "qatracker/milestones/".$milestone->id."/builds/".$record->id."/testcases"
                ),
                'style' => "font-style:italic;",
            );
        }
        elseif ($record->status == 4) {
            $title = array(
                'data' => l(
                    t("@title (ready)$rebuild", array('@title' => $record->title)),
                    "qatracker/milestones/".$milestone->id."/builds/".$record->id."/testcases"
                ),
                'style' => 'font-weight:bold;',
            );
        }
        else {
            $title = array(
                'data' => l(
                    t("@title$rebuild", array('@title' => $record->title)),
                    "qatracker/milestones/".$milestone->id."/builds/".$record->id."/testcases"
                ),
            );
        }

        # Generate the bug column
        $bugs = "";
        foreach ($stats['bugs'] as $bug) {
            $bugs .= qatracker_bug_tooltip($bug, "left");
        }

        $data = array(
            l(theme("image", array('path' => "/modules/qatracker/misc/cdrom.png", 'alt' => t("Download"))), 'qatracker/milestones/'.$milestoneid.'/builds/'.$record->id.'/downloads', array('html' => TRUE)),
            $title,
            $cols[0],
            $cols[2],
            $cols[3],
            array('data' => $bugs, 'style' => 'text-align:center;'),
            $record->version,
        );

        if (!$admin) {
            $rows[$record->id] = array(
                'data' => $data,
                'class' => array(
                    'qatracker_filter_status_'.$filter_status
                )
            );
        }
        else {
            $rows[$record->id] = $data;
            $rows[$record->id]['#attributes'] = array('class' => array('qatracker_filter_status_'.$filter_status));
        }
        $family = $record->family;
        $familytitle = $record->familytitle;
    }
    $items[] = new_table($rows, $admin, array("qatracker_filter_family_".$family), $familytitle);

    if ($admin) {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<hr><h2>',
            '#markup' => t("Administration"),
            '#suffix' => '</h2>',
        );

        $items['status'] = array(
            '#type' => 'fieldset',
            '#title' => t('Status'),
            '#attributes' => array('class' => array('container-inline')),
        );

        $items['status']['status_select'] = array(
            '#type' => 'select',
            '#title' => t('Build status'),
            '#title_display' => 'invisible',
            '#options' => array(
                "disable" => t("Mark as disabled (prevents result reporting"),
                "testing" => t("Mark for testing"),
                "ready" => t("Mark as ready"),
                "remove" => t("Remove from the list")),
            '#required' => FALSE,
        );

        $items['status']['status_submit'] = array(
            '#type' => 'submit',
            '#value' => t('Update build status'),
            '#submit' => array('qatracker_user_builds_submit_status'),
            '#limit_validation_errors' => array(
                array('add', 'rebuilds_select'),
            ),

        );

        if (array_key_exists("rebuilds_allowed", $site->options) && $site->options['rebuilds_allowed'] == 1) {
            $items['rebuilds'] = array(
                '#type' => 'fieldset',
                '#title' => t('Rebuilds'),
                '#attributes' => array('class' => array('container-inline')),
            );

            $items['rebuilds']['rebuilds_select'] = array(
                '#type' => 'select',
                '#title' => t('Product rebuild'),
                '#title_display' => 'invisible',
                '#options' => array(
                    "rebuild" => t("Request a rebuild"),
                    "cancel" => t("Cancel rebuild request")),
                '#required' => FALSE,
            );

            $items['rebuilds']['rebuilds_submit'] = array(
                '#type' => 'submit',
                '#value' => t('Update rebuild status'),
                '#submit' => array('qatracker_user_builds_submit_rebuilds'),
                '#limit_validation_errors' => array(
                    array('add', 'status_select'),
                ),
            );
        }
    }

    return $items;
}

function qatracker_user_builds_submit_rebuilds($form, &$form_state) {
    global $user;

    $site = qatracker_get_current_site();
    $admin = qatracker_acl("administer site configuration", array("admin", "product"), $site);
    $productowner = !qatracker_acl("administer site configuration", array("admin"));
    $milestoneid = arg(2);

    if (!$admin) {
        drupal_access_denied();
        exit;
    }

    if (!array_key_exists("rebuilds_allowed", $site->options) || $site->options['rebuilds_allowed'] == 0) {
        drupal_not_found();
        exit;
    }

    # Get the seriesid
    $query = db_select('qatracker_milestone');
    $query->condition('qatracker_milestone.id', $milestoneid);
    $query->fields('qatracker_milestone', array('seriesid'));
    $seriesid = $query->execute()->fetchField();

    # Magic to extract all the selected items
    $selection = array();
    foreach ($form_state['input'] as $element) {
        if (!is_array($element)) {
            continue;
        }
        foreach ($element as $key => $value) {
            if ($key == $value) {
                $query = db_select('qatracker_build');
                $query->condition('qatracker_build.id', $key);
                $query->fields('qatracker_build', array('productid'));
                $productid = $query->execute()->fetchField();

                if ($productowner) {
                    if (qatracker_acl_product($productid)) {
                        $selection[] = $productid;
                    }
                }
                else {
                    $selection[] = $productid;
                }
            }
        }
    }

    if (count($selection) == 0) {
        return $form;
    }

    $selection = array_unique($selection);

    $query = db_select('qatracker_rebuild');
    $query->fields('qatracker_rebuild', array('productid'));
    $query->condition('qatracker_rebuild.seriesid', $seriesid);
    $query->condition('qatracker_rebuild.productid', $selection, 'IN');
    $query->condition('qatracker_rebuild.status', array(4, 5), 'NOT IN');
    $current_rebuilds = $query->execute()->fetchCol();

    switch ($form_state['input']['rebuilds_select']) {
        case 'rebuild':
            foreach ($selection as $id => $productid) {
                # Skip things that are already building
                if (in_array($productid, $current_rebuilds)) {
                    unset($current_rebuilds[$id]);
                    continue;
                }

                # Check the quota
                if (!qatracker_acl("administer site configuration", array("admin"), $site) && array_key_exists("rebuilds_rate_limit", $site->options)) {
                    $site_quota = $site->options['rebuilds_rate_limit'] ? $site->options['rebuilds_rate_limit'] : 0;

                    if ($site_quota && $site_quota > 0) {
                        $query = db_select('qatracker_rebuild');
                        $query->fields('qatracker_rebuild', array('id'));
                        $query->condition('qatracker_rebuild.seriesid', $seriesid);
                        $query->condition('qatracker_rebuild.productid', $productid);
                        $query->condition('qatracker_rebuild.requestedby', $user->uid);
                        $query->condition('qatracker_rebuild.requestedat', date("Y-m-d H:i:s", time() - 86400), '>=');
                        $query->condition('qatracker_rebuild.status', array(5), 'NOT IN');
                        $user_quota = $query->execute()->rowCount();

                        if ($user_quota >= $site_quota) {
                            watchdog("qatracker",
                                t("User reached rebuild quota: @user"),
                                array("@user" => $user->uid));
                            drupal_set_message(t("Daily rebuild quota reached for product."), "error");
                            break;
                        }
                    }
                }


                $query = db_insert('qatracker_rebuild');
                $query->fields(array(
                    'seriesid' => $seriesid,
                    'productid' => $productid,
                    'requestedby' => $user->uid,
                    'requestedat' => date("Y-m-d H:i:s"),
                    'status' => 0));
                $query->execute();
            }
            watchdog("qatracker",
                t("Scheduled products for rebuild: @products"),
                array('@products' => implode(", ", $selection))
            );
        break;

        case 'cancel':
            $query = db_update('qatracker_rebuild');
            $query->fields(array(
                'changedby' => $user->uid,
                'changedat' => date("Y-m-d H:i:s"),
                'status' => 5));
            $query->condition('qatracker_rebuild.seriesid', $seriesid);
            $query->condition('qatracker_rebuild.productid', $selection, 'IN');
            $query->condition('qatracker_rebuild.status', 0);
            $query->execute();

            watchdog("qatracker",
                t("Canceled rebuilds for: @products"),
                array('@products' => implode(", ", $selection))
            );
        break;

        default:
            drupal_not_found();
            exit;
        break;
    }
    $query->execute();

    return $form;
}

function qatracker_user_builds_submit_status($form, &$form_state) {
    $site = qatracker_get_current_site();
    $admin = qatracker_acl("administer site configuration", array("admin", "product"), $site);
    $productowner = !qatracker_acl("administer site configuration", array("admin"));
    $milestoneid=arg(2);

    if (!$admin) {
        drupal_access_denied();
        exit;
    }

    # Magic to extract all the selected items
    $selection = array();
    foreach ($form_state['input'] as $element) {
        if (!is_array($element)) {
            continue;
        }
        foreach ($element as $key => $value) {
            if ($key == $value) {
                if ($productowner) {
                    $query = db_select('qatracker_build');
                    $query->condition('qatracker_build.id', $key);
                    $query->fields('qatracker_build', array('productid'));
                    $productid = $query->execute()->fetchField();

                    if (qatracker_acl_product($productid)) {
                        $selection[] = $key;
                    }
                }
                else {
                    $selection[] = $key;
                }
            }
        }
    }

    if (count($selection) == 0) {
        return $form;
    }

    $query = db_update('qatracker_build_milestone');
    $query->condition('buildid', $selection, 'IN');
    $query->condition('milestoneid', $milestoneid);

    switch ($form_state['input']['status_select']) {
        case 'disable':
            $query->fields(array('status' => 1));
            watchdog("qatracker",
                t("Disabled builds with IDs: @builds"),
                array('@builds' => implode(", ", $selection))
            );
        break;

        case 'testing':
            $query->fields(array('status' => 0));
            watchdog("qatracker",
                t("Re-enabled builds with IDs: @builds"),
                array('@builds' => implode(", ", $selection))
            );
        break;

        case 'ready':
            $query->fields(array('status' => 4));
            watchdog("qatracker",
                t("Marked as ready builds with IDs: @builds"),
                array('@builds' => implode(", ", $selection))
            );
        break;

        case 'remove':
            $query->fields(array('status' => 2));
            watchdog("qatracker",
                t("Removed builds with IDs: @builds"),
                array('@builds' => implode(", ", $selection))
            );
        break;

        default:
            drupal_not_found();
            exit;
        break;
    }
    $query->execute();

    return $form;
}

?>
