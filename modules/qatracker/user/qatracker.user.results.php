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

function qatracker_user_results() {
    global $qatracker_result_result, $user;

    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Parse the URL
    $milestoneid=arg(2);
    $buildid=arg(4);
    $testcaseid=arg(6);

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
    $query->addField('qatracker_product_family', 'title', 'product_family');
    $query->leftJoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_build.id');
    $query->leftJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->leftJoin('qatracker_product_family', 'qatracker_product_family', 'qatracker_product_family.id = qatracker_product.familyid');
    $query->condition('qatracker_build.id', $buildid);
    $result = $query->execute();
    $build = $result->fetch();

    # Check that the build indeed exists
    if (!$build) {
        drupal_not_found();
        exit;
    }

    # Fetch details on the testcase
    $query = db_select('qatracker_testsuite_testcase');
    $query->leftJoin('qatracker_testcase', 'qatracker_testcase', 'qatracker_testcase.id = qatracker_testsuite_testcase.testcaseid');
    $query->leftJoin('qatracker_testsuite_product', 'qatracker_testsuite_product', 'qatracker_testsuite_product.testsuiteid = qatracker_testsuite_testcase.testsuiteid');
    $query->fields('qatracker_testcase', array('id', 'title', 'link'));
    $query->fields('qatracker_testsuite_testcase', array('status'));
    $query->condition('qatracker_testsuite_testcase.testcaseid', $testcaseid);
    $query->condition('qatracker_testsuite_product.milestone_seriesid', $build->milestone_seriesid);
    $query->condition('qatracker_testsuite_product.productid', $build->product_id);
    $result = $query->execute();
    $testcase = NULL;

    # When multiple match, prefer those that aren't archived.
    foreach ($result as $record) {
        if (!$testcase) {
            $testcase = $record;
        }
        elseif ($record->status != 1) {
            $testcase = $record;
            break;
        }
    }

    # Check that the testcase indeed exists
    if (!$testcase) {
        drupal_not_found();
        exit;
    }

    # Check if we are in read-only mode
    $admin = qatracker_acl("administer site configuration", array("admin", "product"), $site);
    if ($build->milestone_status != 0 || $build->build_status == 2 || $build->build_status == 3 || $testcase->status == 1) {
        $admin_acl = False;
        $user_acl = False;
        drupal_set_title(t("!testcase in !product for !milestone (archived)", array(
            "!testcase" => ucfirst($testcase->title),
            "!product" => $build->product_title,
            "!milestone" => $build->milestone_title,
        )));
    }
    elseif ($build->build_status == 1) {
        $admin_acl = False;
        $user_acl = False;
        drupal_set_title(t("!testcase in !product for !milestone (rebuilding)", array(
            "!testcase" => ucfirst($testcase->title),
            "!product" => $build->product_title,
            "!milestone" => $build->milestone_title,
        )));
    }
    elseif ($build->build_status == 4) {
        $admin_acl = qatracker_acl_product($build->product_id);
        $user_acl = qatracker_acl("administer site configuration", array("user"), $site);
        drupal_set_title(t("!testcase in !product for !milestone (ready)", array(
            "!testcase" => ucfirst($testcase->title),
            "!product" => $build->product_title,
            "!milestone" => $build->milestone_title,
        )));
    }
    else {
        $admin_acl = qatracker_acl_product($build->product_id);
        $user_acl = qatracker_acl("administer site configuration", array("user"), $site);
        drupal_set_title(t("!testcase in !product for !milestone", array(
            "!testcase" => ucfirst($testcase->title),
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

    # Link to testcase
    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<br />',
        '#markup' => l(
            theme(
                "image",
                array(
                    'path' => "/modules/qatracker/misc/test.png",
                    'alt' => $testcase->title
                )
            ) . t("Detailed information on the testcase"),
            "qatracker/testcases/".$testcase->id."/info",
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

    # Show the current testcase
    $query = db_select('qatracker_testcase_revision');
    $query->fields('qatracker_testcase_revision', array('text'));
    $query->condition('qatracker_testcase_revision.testcaseid', $testcase->id);
    $query->orderBy('qatracker_testcase_revision.id', 'DESC');
    $current_revision = $query->execute()->fetchField();

    # Replace placeholders
    $current_revision = str_replace("FAMILY", $build->product_family ? $build->product_family : $build->product_title , $current_revision);

    $items['testcase'] = array(
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#title' => t("Testcase"),
    );

    if ($current_revision) {
        $items['testcase'][] = array(
            '#type' => 'markup',
            '#prefix' => '<div style="padding:0.5em;background-color:#e4e4e4;">',
            '#markup' => check_markup($current_revision, "filtered_html"),
            '#suffix' => '</div>',
        );
    }
    else {
        if ($testcase->link) {
            $markup = t("This is a legacy testcase, content is only available here: !link", array('!link' => l($testcase->link, $testcase->link)));
        }
        else {
            $markup = t("This is a legacy testcase without an associated link, please refer to the testcase title.");
        }
        $items['testcase'][] = array(
            '#type' => 'markup',
            '#prefix' => '<div style="padding:0.5em;background-color:#e4e4e4;">',
            '#markup' => $markup,
            '#suffix' => '</div>',
        );
    }

    # Getting all the entries
    $query = db_select('qatracker_result');
    $query->fields('qatracker_result', array('id', 'reporterid', 'date', 'result', 'comment', 'hardware', 'lastchange', 'status', 'testcaseid', 'revisionid'));
    $query->addExpression('greatest(qatracker_result.date, qatracker_result.lastchange)', 'last_touched');
    $query->orderBy('qatracker_result.result', 'ASC');
    $query->orderBy('last_touched', 'DESC');
    $query->condition('qatracker_result.buildid', $buildid);
    $query->condition('qatracker_result.testcaseid', $testcaseid);
    $query->condition('qatracker_result.status', array(0, 1), 'IN');
    $result = $query->execute();

    # And generating the table
    $rows = array();
    $status = Null;

    function new_table($rows, $type) {
        global $qatracker_testsuite_testcase_status;

        $header = array(
            array('data' => '', 'style' => 'width:0'),
            array('data' => t('Reporter'), 'style' => 'width:10em'),
            array('data' => t('Last update'), 'style' => 'width:10em'),
            array('data' => t('Machine'), 'style' => 'width:5em;text-align:center;'),
            array('data' => t('Bugs'), 'style' => 'width:10em;text-align:center;'),
            array('data' => t('Comment'), 'style' => 'width:auto'),
            array('data' => '', 'style' => 'width:5em'),
        );

        return array(
            '#theme' => "table",
            '#header' => $header,
            '#rows' => $rows,
        );
    }

    $user_result = Null;

    foreach ($result as $record) {
        # Skip removed results unless we're an admin
        if (!$admin && $record->status == 1) {
            continue;
        }

        # Check if it's one of our results
        if ($record->status != 1 && $record->reporterid == $user->uid) {
            $user_result = $record;
        }

        # Group by result by creating a new table everytime the result changes
        if ($status !== Null && $status != $record->result) {
            $items[] = new_table($rows, $status);
            $rows = array();
        }

        # Get bugs stats (FIXME: copy/paste from qatracker_build_stats, should be moved to separate function)
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
        $query->condition('qatracker_bug.resultid', $record->id);
        $query->orderBy('qatracker_bug.bugnumber', 'ASC');
        $record->bugs = $query->execute()->fetchAll();
        $record->critical_bugs = array();
        $record->regular_bugs = array();

        $bugs = "";
        foreach ($record->bugs as $bug) {
            $bugs .= qatracker_bug_tooltip($bug, "left");

            if ($record->reporterid == $user->uid) {
                if ($bug->maximportance == 1) {
                    $record->critical_bugs[] = $bug->originalbug;
                }
                else {
                    $record->regular_bugs[] = $bug->originalbug;
                }
            }
        }

        # Get data about the user
        $account = user_load($record->reporterid);
        if (!$account) {
            # If we can't figure out who it's, just load anonymous
            $account = user_load(0);
        }

        $icon = theme(
            "image",
            array(
                'path' => "/modules/qatracker/misc/result-".$record->result.".png",
                'alt' => t($qatracker_result_result[$record->result]),
                'title' => t($qatracker_result_result[$record->result])
            )
        );

        # Hardware profile
        $hardware = "";
        if ($record->hardware) {
            $hardware = l(theme(
                "image",
                array(
                    'path' => "/modules/qatracker/misc/machine.png",
                    'alt' => $qatracker_result_result[$record->result]
                )
            ), $record->hardware, array('html' => TRUE));
        }

        # Links
        $links = array();
        if ($record->revisionid) {
            $links[] = l(theme(
                "image",
                array(
                    'path' => "/modules/qatracker/misc/test.png",
                    'alt' => t('Link to the testcase revision')
                )
            ),
            "qatracker/testcases/".$record->testcaseid."/revisions/".$record->revisionid."/info",
            array('html' => TRUE));
        }

        if ($record->reporterid == $user->uid || $admin_acl) {
            $links[] = l(theme(
                "image",
                array(
                    'path' => "/modules/qatracker/misc/edit.png",
                    'alt' => t('Edit result')
                )
            ),
            "qatracker/milestones/".arg(2)."/builds/".arg(4)."/testcases/".arg(6)."/results/".$record->id."/edit",
            array('html' => TRUE));
        }

        # FIXME: Integrate with SSO to get a link to the profile instead of
        # hardcoding Launchpad
        $data = array(
            $icon,
            l($account->name, "http://launchpad.net/~".$account->name),
            format_date(strtotime($record->last_touched), 'short'),
            array('data' => $hardware, 'style' => 'text-align:center;'),
            array('data' => $bugs, 'style' => 'text-align:center;'),
            check_markup($record->comment),
            array('data' => implode(" ", $links), 'style' => 'text-align:center;'),
        );

        if ($record->status == 1) {
            $rows[$record->id] = array('data' => $data, 'style' => 'text-decoration:line-through;');
        }
        else {
            $rows[$record->id] = $data;
        }
        $status = $record->result;
    }
    $items[] = new_table($rows, $status);

    if ($user_acl) {
        $items = array_merge($items, qatracker_user_results_form());
    }
    elseif ($user->uid == 0) {
        $items['result'] = array(
            '#type' => 'fieldset',
            '#title' => 'Add a test result',
            '#attributes' => array('id' => array('add_result')),
        );
        $items['result'][] = array(
            '#type' => 'markup',
            '#prefix' => '<br /><div style="text-align:center;font-weight:bold;">',
            '#markup' => t("You need to be logged in to submit your test results."),
            '#suffix' => '</div>',
        );
    }

    return $items;
}

function qatracker_user_results_validate($form, &$form_state) {
    return qatracker_user_results_form_validate($form, $form_state);
}

function qatracker_user_results_submit($form, &$form_state) {
    return qatracker_user_results_form_submit($form, $form_state);
}

function qatracker_user_results_edit($form, &$form_state) {
    global $user;

    $resultid = arg(8);

    $query = db_select('qatracker_result');
    $query->fields('qatracker_result', array('id', 'reporterid', 'date', 'result', 'comment', 'hardware', 'lastchange', 'status'));
    $query->leftJoin('qatracker_build', 'qatracker_build', 'qatracker_build.id=qatracker_result.buildid');
    $query->addField('qatracker_build', 'productid', 'productid');
    $query->addExpression('greatest(qatracker_result.date, qatracker_result.lastchange)', 'last_touched');
    $query->condition('qatracker_result.id', $resultid);
    $record = $query->execute()->fetch();

    if ($record->reporterid != $user->uid && !qatracker_acl_product($record->productid)) {
        drupal_access_denied();
        exit;
    }

    $query = db_select('qatracker_bug');
    $query->fields('qatracker_bug', array('bugnumber', 'bugimportance'));
    $query->condition('qatracker_bug.resultid', $record->id);
    $record->bugs = $query->execute()->fetchAll();
    $record->critical_bugs = array();
    $record->regular_bugs = array();

    foreach ($record->bugs as $bug) {
        if ($bug->bugimportance == 1) {
            $record->critical_bugs[] = $bug->bugnumber;
        }
        else {
            $record->regular_bugs[] = $bug->bugnumber;
        }
    }

    return qatracker_user_results_form($record);
}

function qatracker_user_results_edit_validate($form, &$form_state) {
    return qatracker_user_results_form_validate($form, $form_state);
}

function qatracker_user_results_edit_submit($form, &$form_state) {
    $form_state['redirect'] = 'qatracker/milestones/'.arg(2).'/builds/'.arg(4).'/testcases/'.arg(6).'/results/';
    return qatracker_user_results_form_submit($form, $form_state);
}

function qatracker_user_results_form($user_result = Null) {
    global $qatracker_result_result;
    $form = array();
    $milestoneid=arg(2);
    $testcaseid=arg(6);

    # Get bugs stats (FIXME: copy/paste from qatracker_build_stats, should be moved to separate function)
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
    $query->leftjoin('qatracker_result', 'qatracker_result', 'qatracker_result.id = qatracker_bug.resultid');
    $query->leftjoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_result.buildid');
    $query->leftjoin('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build_milestone.buildid = qatracker_result.buildid');
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
    $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
    $query->condition('qatracker_result.testcaseid', $testcaseid);
    $query->orderBy('qatracker_bug.bugnumber', 'ASC');
    $record->bugs = $query->execute()->fetchAll();
    $record->critical_bugs = array();
    $record->regular_bugs = array();

    $bugs = "";
    foreach ($record->bugs as $bug) {
        $bugs .= qatracker_bug_tooltip($bug, "left");
    }

    if (!$bugs) {
        $bugs = t("None");
    }

    $form['old_bugs'] = array(
        '#type' => 'fieldset',
        '#title' => 'Bugs',
    );
    $form['old_bugs'][] = array(
        '#type' => 'markup',
        '#prefix' => '<h2>'.t("Bugs to look for").'</h2>',
        '#markup' => $bugs,
        '#suffix' => '<br /><small>'.t('List of bugs that were previously reported for this testcase.').'</small>',
    );

    if ($user_result) {
        $form['qatracker_result'] = array(
            '#type' => 'fieldset',
            '#title' => 'Test result',
        );
    }
    else {
        $form['qatracker_result'] = array(
            '#type' => 'fieldset',
            '#title' => 'Add a test result',
            '#attributes' => array('id' => array('add_result')),
        );

        # Build an empty object
        $user_result = new stdClass();
        $user_result->id = Null;
        $user_result->result = 0;
        $user_result->comment = "";
        $user_result->hardware = "";
        $user_result->critical_bugs = array();
        $user_result->regular_bugs = array();
    }

    $form['qatracker_result']['result'] = array(
        '#type' => 'radios',
        '#title' => t('Result'),
        '#required' => True,
        '#default_value' => $user_result->result,
        '#options' => $qatracker_result_result,
    );

    $form['qatracker_result']['critical_bugs'] = array(
        '#type' => 'textfield',
        '#title' => t('Critical bugs'),
        '#description' => t('Comma separated list of bug numbers preventing you from passing the testcase'),
        '#default_value' => implode(", ", $user_result->critical_bugs),
    );

    $form['qatracker_result']['regular_bugs'] = array(
        '#type' => 'textfield',
        '#title' => t('Bugs'),
        '#description' => t('Comma separated list of bug numbers discovered while going through the testcase'),
        '#default_value' => implode(", ", $user_result->regular_bugs),
    );

    $form['qatracker_result']['hardware'] = array(
        '#type' => 'textfield',
        '#title' => t('Hardware profile'),
        '#description' => t('URL to the hardware profile'),
        '#default_value' => $user_result->hardware,
        '#maxlength' => 1000,
    );

    $form['qatracker_result']['comment'] = array(
        '#type' => 'textarea',
        '#title' => t('Comment'),
        '#default_value' => $user_result->comment,
    );
    $form['qatracker_result']['actions'] = array(
        '#type' => 'actions',
    );

    if ($user_result->id) {
        $form['qatracker_result']['actions']['update-result'] = array(
            '#type' => 'submit',
            '#value' => t('Update result'),
        );
        $form['qatracker_result']['actions']['delete-result'] = array(
            '#type' => 'submit',
            '#value' => t('Delete result'),
        );
    }
    else {
        $form['qatracker_result']['actions']['new-result'] = array(
            '#type' => 'submit',
            '#value' => t('Submit result'),
        );
    }

    return $form;
}

function qatracker_user_results_form_validate($form, &$form_state) {
    $result = $form['qatracker_result']['result']['#value'];
    $critical_bugs = explode(',', $form['qatracker_result']['critical_bugs']['#value']);
    $regular_bugs = explode(',', $form['qatracker_result']['regular_bugs']['#value']);
    $bugs = array();

    # Prepare our bug list
    foreach ($regular_bugs as $bug) {
        $bugnumber = trim($bug);
        if (ctype_digit($bugnumber)) {
            $bugs[$bugnumber] = 0;
        }
        elseif ($bugnumber) {
            form_set_error('regular_bugs', t("Invalid bug list, bug numbers must be made of digits and separate by commas."));
            break;
        }
    }
    foreach ($critical_bugs as $bug) {
        $bugnumber = trim($bug);
        if (ctype_digit($bugnumber)) {
            $bugs[$bugnumber] = 1;
        }
        elseif ($bugnumber) {
            form_set_error('critical_bugs', t("Invalid bug list, bug numbers must be made of digits and separate by commas."));
            break;
        }
    }


    if ($result == 0 && count($bugs) == 0) {
        form_set_error('critical_bugs', t("Every failure needs to have at least one bug associated with it."));
    }
}

function qatracker_user_results_form_submit($form, &$form_state) {
    global $user;

    $site = qatracker_get_current_site();
    $user_acl = qatracker_acl("administer site configuration", array("user"), $site);
    $admin_acl = qatracker_acl("administer site configuration", array("admin"), $site);

    if (!$user_acl) {
        drupal_access_denied();
        exit;
    }

    # Parse the URL
    $buildid = arg(4);
    $testcaseid = arg(6);
    $resultid = arg(8);

    # Grab the latest revision of the testcase
    $query = db_select('qatracker_testcase_revision');
    $query->fields('qatracker_testcase_revision', array('id'));
    $query->condition('qatracker_testcase_revision.testcaseid', $testcaseid);
    $query->orderBy('qatracker_testcase_revision.id', 'DESC');
    $revisionid = $query->execute()->fetchField();
    if (!$revisionid) {
        $revisionid = NULL;
    }

    # Make sure the user is the owner
    if ($resultid && !$admin_acl) {
        $query = db_select('qatracker_result');
        $query->leftJoin('qatracker_build', 'qatracker_build', 'qatracker_build.id=qatracker_result.buildid');
        $query->fields('qatracker_result', array('reporterid'));
        $query->addField('qatracker_build', 'productid', 'productid');
        $query->condition('qatracker_result.id', $resultid);
        $record = $query->execute()->fetch();

        if ($record->reporterid != $user->uid && !qatracker_acl_product($record->productid)) {
            drupal_access_denied();
            exit;
        }
    }

    # FIXME: Would be nice avoiding matching translated strings...
    switch ($form_state['values']['op']) {
        case t('Submit result'):
            $query = db_insert('qatracker_result');
            $query->fields(array(
                'reporterid' => $user->uid,
                'buildid' => $buildid,
                'testcaseid' => $testcaseid,
                'revisionid' => $revisionid,
                'date' => date("Y-m-d H:i:s"),
                'result' => $form['qatracker_result']['result']['#value'],
                'comment' => $form['qatracker_result']['comment']['#value'],
                'hardware' => $form['qatracker_result']['hardware']['#value'],
                'status' => 0,
            ));

            $resultid = $query->execute();
        break;

        case t('Update result'):
            if ($resultid) {
                $query = db_update('qatracker_result');
                $query->condition('qatracker_result.id', $resultid);
                $query->fields(array(
                    'revisionid' => $revisionid,
                    'lastchange' => date("Y-m-d H:i:s"),
                    'changedby' => $user->uid,
                    'result' => $form['qatracker_result']['result']['#value'],
                    'comment' => $form['qatracker_result']['comment']['#value'],
                    'hardware' => $form['qatracker_result']['hardware']['#value'],
                    'status' => 0,
                ));
                $query->execute();
            }
        break;

        case t('Delete result'):
            if ($resultid) {
                $query = db_update('qatracker_result');
                $query->condition('id', $resultid);
                $query->fields(array(
                    'status' => 1,
                ));
                $query->execute();
            }
        break;

        default:
            drupal_not_found();
            exit;
        break;
    }

    # Update the bugs (FIXME: do something a bit more clever :))
    $critical_bugs = explode(',', $form['qatracker_result']['critical_bugs']['#value']);
    $regular_bugs = explode(',', $form['qatracker_result']['regular_bugs']['#value']);

    # Wipe all the existing bugs
    $query = db_delete('qatracker_bug');
    $query->condition('resultid', $resultid);
    $query->execute();

    $bugs = array();

    # Prepare our bug list
    foreach ($regular_bugs as $bug) {
        $bugnumber = trim($bug);
        if (ctype_digit($bugnumber)) {
            $bugs[$bugnumber] = 0;
        }
    }
    foreach ($critical_bugs as $bug) {
        $bugnumber = trim($bug);
        if (ctype_digit($bugnumber)) {
            $bugs[$bugnumber] = 1;
        }
    }

    # Add them all
    foreach ($bugs as $bugnumber => $bugimportance) {
        $query = db_insert('qatracker_bug');
        $query->fields(array(
            'resultid' => $resultid,
            'bugnumber' => $bugnumber,
            'bugimportance' => $bugimportance,
        ));
        $query->execute();
    }

    return $form;
}

?>
