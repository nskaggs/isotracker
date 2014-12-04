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

function qatracker_user_testcases() {
    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    global $qatracker_testsuite_testcase_status;

    # Parse the URL
    $milestoneid=arg(2);
    $buildid=arg(4);

    # Fetch details on the build
    $query = db_select('qatracker_build');
    $query->addField('qatracker_build', 'version', 'build_version');
    $query->addField('qatracker_build_milestone', 'status', 'build_status');
    $query->addField('qatracker_build_milestone', 'note', 'build_note');
    $query->addField('qatracker_milestone', 'title', 'milestone_title');
    $query->addField('qatracker_milestone', 'status', 'milestone_status');
    $query->addField('qatracker_milestone', 'seriesid', 'milestone_seriesid');
    $query->addField('qatracker_product', 'id', 'product_id');
    $query->addField('qatracker_product', 'title', 'product_title');
    $query->addField('qatracker_product', 'type', 'product_type');
    $query->addField('qatracker_product', 'buginstruction', 'product_buginstruction');
    $query->leftJoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_build.id');
    $query->leftJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->condition('qatracker_build.id', $buildid);
    $result = $query->execute();
    $build = $result->fetch();

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Check that the build indeed exists
    if (!$build) {
        drupal_not_found();
        exit;
    }

    # Check if we are in read-only mode
    $admin = qatracker_acl("administer site configuration", array("user"), $site);
    $readonly = False;
    if ($build->milestone_status != 0 || $build->build_status == 2 || $build->build_status == 3) {
        $readonly = True;
        drupal_set_title(t("Testcases for !product in !milestone (archived)", array(
            "!product" => $build->product_title,
            "!milestone" => $build->milestone_title,
        )));
    }
    elseif ($build->build_status == 1) {
        $readonly = True;
        drupal_set_title(t("Testcases for !product in !milestone (rebuilding)", array(
            "!product" => $build->product_title,
            "!milestone" => $build->milestone_title,
        )));
    }
    elseif ($build->build_status == 4) {
        drupal_set_title(t("Testcases for !product in !milestone (ready)", array(
            "!product" => $build->product_title,
            "!milestone" => $build->milestone_title,
        )));
    }
    else {
        $admin = qatracker_acl("administer site configuration", array("user"), $site);
        drupal_set_title(t("Testcases for !product in !milestone", array(
            "!product" => $build->product_title,
            "!milestone" => $build->milestone_title,
        )));
    }

    # Link to downloads
    switch ($build->product_type) {
        case 1:
            $link_alt = t("Instructions");
            $link_text = t("Link to the installation instructions");
        break;

        default:
            $link_alt = t("Download");
            $link_text = t("Link to the download information");
        break;
    }

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<br /><br />',
        '#markup' => l(
            theme(
                "image",
                array(
                    'path' => "/modules/qatracker/misc/cdrom.png",
                    'alt' => $link_alt,
                )
            ) . $link_text,
            'qatracker/milestones/'.$milestoneid.'/builds/'.$buildid.'/downloads',
            array('html' => TRUE)
        ),
    );

    # Link to bug instruction
    if ($build->product_buginstruction) {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br />',
            '#markup' => l(
                theme(
                    "image",
                    array(
                        'path' => "/modules/qatracker/misc/bug.png",
                        'alt' => "Bug instructions",
                    )
                ) . t("Link to bug reporting instructions"),
                'qatracker/milestones/'.$milestoneid.'/builds/'.$buildid.'/buginstructions',
                array('html' => TRUE)
            ),
        );
    }

    # Getting all the entries
    $query = db_select('qatracker_testsuite_product');
    $query->leftjoin('qatracker_testsuite_testcase', 'qatracker_testsuite_testcase', 'qatracker_testsuite_testcase.testsuiteid = qatracker_testsuite_product.testsuiteid');
    $query->leftjoin('qatracker_testsuite', 'qatracker_testsuite', 'qatracker_testsuite.id = qatracker_testsuite_product.testsuiteid');
    $query->leftjoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_testsuite_testcase.testcaseid');
    $query->fields('qatracker_testcase', array('id', 'title', 'link'));
    $query->fields('qatracker_testsuite_testcase', array('status'));
    $query->addField('qatracker_testsuite', 'title', 'suite');
    $query->condition('qatracker_testsuite_product.productid', $build->product_id);
    $query->condition('qatracker_testsuite_product.milestone_seriesid', $build->milestone_seriesid);
    $query->condition('qatracker_testsuite_testcase.status', array(0, 2, 3), "IN");
    $query->orderBy('qatracker_testsuite.title', 'ASC');
    $query->orderBy('qatracker_testsuite_testcase.status', 'ASC');
    $query->orderBy('qatracker_testsuite_testcase.weight', 'ASC');
    $query->orderBy('qatracker_testcase.title', 'ASC');
    $result = $query->execute();

    # And generating the table
    $rows = array();
    $suite = Null;

    function new_table($rows, $admin, $suite) {
        global $qatracker_testsuite_testcase_status;

        $header = array(
            array('date' => '', 'style' => 'width:0'),
            array('data' => t('@suite', array("@suite" => ucfirst($suite)))),
            array('data' => t('Type'), 'style' => 'width:8em; text-align:center;'),
            array('data' => t('Passed'), 'style' => 'width:5em; text-align:center;'),
            array('data' => t('Failed'), 'style' => 'width:5em; text-align:center;'),
            array('data' => t('Running'), 'style' => 'width:5em; text-align:center;'),
            array('data' => t('Bugs'), 'style' => 'width:10em; text-align:center;'),
        );

        if ($admin) {
            return array(
                '#type' => "tableselect",
                '#header' => $header,
                '#options' => $rows,
                '#empty' => t('No testcase available')
            );
        }
        else {
            return array(
                '#theme' => "table",
                '#header' => $header,
                '#rows' => $rows,
            );
        }
    }

    foreach ($result as $record) {
        # Group by testcase type by creating a new table everytime the type changes
        if ($suite !== Null && $suite != $record->suite) {
            $items[] = new_table($rows, $admin, $suite);
            $rows = array();
        }

        # Get the stats for this build
        $stats = qatracker_build_stats($build->milestone_seriesid, $buildid, $build->product_id, $record->id);

        $test_fail = "-";
        if (array_key_exists(0, $stats['testcases'][$record->status]['results'])) {
            $test_fail = $stats['testcases'][$record->status]['results'][0];
        }

        $test_pass = "-";
        if (array_key_exists(1, $stats['testcases'][$record->status]['results'])) {
            $test_pass = $stats['testcases'][$record->status]['results'][1];
        }

        $test_running = "-";
        if (array_key_exists(2, $stats['testcases'][$record->status]['results'])) {
            $test_running = $stats['testcases'][$record->status]['results'][2];
        }

        # Generate the bug column
        $bugs = "";
        foreach ($stats['bugs'] as $bug) {
            $bugs .= qatracker_bug_tooltip($bug, "left");
        }

        # FIXME: Icon needs to be changed to work when Drupal is installed in a sub-directory
        $rows[$record->id] = array(
            l(theme("image", array('path' => "/modules/qatracker/misc/test.png", 'alt' => $record->title)), "qatracker/testcases/".$record->id."/info", array('html' => TRUE)),
            l($record->title, "qatracker/milestones/".arg(2)."/builds/".arg(4)."/testcases/".$record->id."/results"),
            array('data' => ucfirst($qatracker_testsuite_testcase_status[$record->status]), 'style' => 'text-align:center;'),
            array('data' => $test_pass, 'style' => 'text-align:center;'),
            array('data' => $test_fail, 'style' => 'text-align:center;'),
            array('data' => $test_running, 'style' => 'text-align:center;'),
            array('data' => $bugs, 'style' => 'text-align:center;'),
        );
        $suite = $record->suite;
    }
    $items[] = new_table($rows, $admin, $suite);

    if ($admin) {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<hr><h2>',
            '#markup' => t("Actions"),
            '#suffix' => '</h2>',
        );
        $items['actions'] = array(
            '#type' => 'actions',
        );
        if (!$readonly) {
            $items['actions']['passed'] = array(
                '#type' => 'submit',
                '#value' => t('Passed with no bugs'),
            );
        }
        $items['actions']['subscribe'] = array(
            '#type' => 'submit',
            '#value' => t('Subscribe'),
        );
        $items['actions']['unsubscribe'] = array(
            '#type' => 'submit',
            '#value' => t('Unsubscribe'),
        );
    }

    return $items;
}

function qatracker_user_testcases_submit($form, &$form_state) {
    global $user;

    $site = qatracker_get_current_site();
    $admin = qatracker_acl("administer site configuration", array("user"), $site);

    if (!$admin) {
        drupal_access_denied();
        exit;
    }

    # Parse the URL
    $milestoneid=arg(2);
    $buildid=arg(4);

    # Grab the productid and check that the build isn't read-only
    $query = db_select('qatracker_build_milestone');
    $query->leftjoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_build_milestone.buildid');
    $query->fields('qatracker_build', array('productid'));
    $query->condition('qatracker_build_milestone.buildid', $buildid);
    $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
    $query->condition('qatracker_build_milestone.status', array(0, 4), 'IN');
    $productid=$query->execute()->fetchField();

    $readonly = True;
    if ($productid) {
        $readonly = False;
    }

    # Magic to extract all the selected items
    $selection = array();
    foreach ($form_state['values'] as $element) {
        if (!is_array($element)) {
            continue;
        }
        foreach ($element as $key => $value) {
            if ($key == $value) {
                $selection[] = $key;
            }
        }
    }

    # FIXME: Would be nice avoiding matching translated strings...
    switch ($form_state['values']['op']) {
        case t('Passed with no bugs'):
            if ($readonly) {
                return $form;
            }

            foreach ($selection as $testcase) {
                # Check for any existing result
                $query = db_select('qatracker_result');
                $query->fields('qatracker_result', array('result'));
                $query->condition('reporterid', $user->uid);
                $query->condition('testcaseid', $testcase);
                $query->condition('buildid', $buildid);
                $query->condition('status', 0);
                $result = $query->execute();
                $rowcount = $result->rowCount();

                # If none, add a new one as passed
                if ($rowcount == 0) {
                    $query = db_insert('qatracker_result');
                    $query->fields(array(
                        'buildid' => $buildid,
                        'testcaseid' => $testcase,
                        'reporterid' => $user->uid,
                        'date' => date("Y-m-d H:i:s"),
                        'result' => 1,
                    ));
                    $query->execute();
                    continue;
                }

                # If already one in-progress, mark it as passed
                if ($rowcount == 1 && $result->fetch()->result == 2) {
                    $query = db_update('qatracker_result');
                    $query->fields(array(
                        'lastchange' => date("Y-m-d H:i:s"),
                        'changedby' => $user->uid,
                        'result' => 1,
                    ));
                    $query->condition('reporterid', $user->uid);
                    $query->condition('testcaseid', $testcase);
                    $query->condition('buildid', $buildid);
                    $query->condition('status', 0);
                    $query->execute();
                    continue;
                }
            }
        break;

        case t('Subscribe'):
            if (count($selection) == 0) {
                return $form;
            }

            # Check if we're already subscribed, if not, add it
            $query = db_select('qatracker_user_subscription');
            $query->fields('qatracker_user_subscription', array('testcaseid'));
            $query->condition('userid', $user->uid);
            $query->condition('productid', $productid);
            $query->condition('testcaseid', $selection, 'IN');
            $result = $query->execute();

            # Get the list of testcases that the user didn't subscribe to yet
            $missing = array_diff($selection, $result->fetchCol());

            # Subscribe to them
            foreach ($missing as $testcase) {
                $query = db_insert('qatracker_user_subscription');
                $query->fields(array(
                    'userid' => $user->uid,
                    'productid' => $productid,
                    'testcaseid' => $testcase,
                ));
                $query->execute();
            }

            drupal_set_message(t("Subscription options updated"));
        break;

        case t('Unsubscribe'):
            if (count($selection) == 0) {
                return $form;
            }

            # Delete any subscription (no need to check if it exists)
            $query = db_delete('qatracker_user_subscription');
            $query->condition('userid', $user->uid);
            $query->condition('productid', $productid);
            $query->condition('testcaseid', $selection, 'IN');
            $query->execute();

            drupal_set_message(t("Subscription options updated"));
        break;

        default:
            drupal_not_found();
            exit;
        break;
    }

    return $form;
}

function qatracker_user_testcases_info() {
    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    global $qatracker_testsuite_testcase_status;

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Parse the URL
    $testcaseid = arg(2);
    $revisionid = arg(4);

    # Return a 404 on invalid testcaseid
    if (!is_numeric($testcaseid)) {
        drupal_not_found();
        exit;
    }

    # Fetch details on the testcase
    $query = db_select('qatracker_testcase');
    if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
        $query->leftJoin('qatracker_testcase_identifier', 'qatracker_testcase_identifier', 'qatracker_testcase_identifier.testcaseid=qatracker_testcase.id');
        $query->addField('qatracker_testcase_identifier', 'title', 'identifier');
    }
    else {
        $query->addField('qatracker_testcase', 'id', 'identifier');
    }
    $query->fields('qatracker_testcase', array('id', 'title', 'link'));
    $query->condition('qatracker_testcase.id', $testcaseid);
    $testcase = $query->execute()->fetch();

    if (!$testcase) {
        drupal_not_found();
        exit;
    }


    if ($revisionid) {
        drupal_set_title(t("Details for revision !revision of testcase: !testcase", array(
            "!revision" => $revisionid,
            "!testcase" => ucfirst($testcase->title),
        )));
    }
    else {
        drupal_set_title(t("Details for testcase: !testcase", array(
            "!testcase" => ucfirst($testcase->title),
        )));
    }

    # Get the list of testsuites for this testcase
    $query = db_select('qatracker_testsuite_testcase');
    $query->fields('qatracker_testsuite', array('title'));
    $query->leftJoin('qatracker_testsuite', 'qatracker_testsuite', 'qatracker_testsuite.id=qatracker_testsuite_testcase.testsuiteid');
    $query->condition('qatracker_testsuite_testcase.testcaseid', $testcase->id);
    $testsuites=$query->execute()->fetchCol();
    if (count($testsuites) == 0) {
        $testsuites = array(t("None"));
    }

    # Get all the revisions of the testcase
    $query = db_select('qatracker_testcase_revision');
    $query->leftJoin('users', 'users', 'users.uid=qatracker_testcase_revision.createdby');
    $query->addField('users', 'name', 'createdby_name');
    $query->fields('qatracker_testcase_revision', array('id', 'text', 'createdby', 'createdat'));
    $query->condition('qatracker_testcase_revision.testcaseid', $testcase->id);
    $query->orderBy('qatracker_testcase_revision.createdat', 'DESC');
    $revisions = $query->execute()->fetchAll();

    if (count($revisions) == 0) {
        $latest = t("None (legacy testcase)");
    }
    else {
        $latest = t("!id (!date by !user)",
                    array(
                        "!id" => $revisions[0]->id,
                        "!date" => format_date(strtotime($revisions[0]->createdat), 'short'),
                        "!user" => $revisions[0]->createdby_name,
                    )
                  );
    }

    # Bug report
    if (array_key_exists("testcase_bug_url", $site->options) && $site->options["testcase_bug_url"]) {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<br /><br />',
            '#markup' => l(
                theme(
                    "image",
                    array(
                        'path' => "/modules/qatracker/misc/bug.png",
                        'alt' => t("Bug")
                    )
                ) . t("Report a bug against the content of this testcase"),
                $site->options['testcase_bug_url'],
                array('html' => TRUE)
            ),
        );
    }

    # Render testcase overview table
    $items[] = array(
        '#theme' => "table",
        '#rows' => array(
            array(
                array('data' => t("ID"), 'style' => 'width:12em;color:white;font-weight:bold;background-color:#757575;'),
                $testcase->identifier ? $testcase->identifier : $testcase->id,
            ),
            array(
                array('data' => t("Title"), 'style' => 'width:12em;color:white;font-weight:bold;background-color:#757575;'),
                $testcase->title,
            ),
            array(
                array('data' => t("Link"), 'style' => 'width:12em;color:white;font-weight:bold;background-color:#757575;'),
                $testcase->link ? l($testcase->link, $testcase->link) : t("No link provided"),
            ),
            array(
                array('data' => t("Part of testsuites"), 'style' => 'width:12em;color:white;font-weight:bold;background-color:#757575;'),
                implode("<br />", $testsuites),
            ),
        ),
    );

    $items[] = array(
        "#type" => "markup",
        "#markup" => "<hr />",
    );

    if (!$revisionid && $revisions) {
        $revisionid = $revisions[0]->id;
    }

    foreach ($revisions as $revision) {
        if ($revision->id == $revisionid) {
            $items[] = array(
                '#theme' => "table",
                '#rows' => array(
                    array(
                        array('data' => t("Revision"), 'style' => 'width:12em;color:white;font-weight:bold;background-color:#757575;'),
                        t("Written on the !date by !user",
                            array(
                                "!id" => $revision->id,
                                "!date" => format_date(strtotime($revision->createdat), 'short'),
                                "!user" => $revision->createdby_name,
                            )
                          ),
                    ),
                    array(
                        array('data' => t("Text"), 'style' => 'width:12em;color:white;font-weight:bold;background-color:#757575;vertical-align:top;'),
                        check_markup($revision->text, "filtered_html"),
                    ),
                ),
            );
        }
        else {
            $items[] = array(
                '#theme' => "table",
                '#rows' => array(
                    array(
                        array('data' => t("Revision"), 'style' => 'width:12em;color:white;font-weight:bold;background-color:#757575;'),
                        l(
                            t("Written on the !date by !user",
                                array(
                                    "!id" => $revision->id,
                                    "!date" => format_date(strtotime($revision->createdat), 'short'),
                                    "!user" => $revision->createdby_name,
                                )
                              ),
                            "qatracker/testcases/".$testcaseid."/revisions/".$revision->id."/info"
                        ),
                    ),
                ),
            );
        }
    }

    return $items;
}
?>
