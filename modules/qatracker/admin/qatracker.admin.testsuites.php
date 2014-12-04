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

function qatracker_admin_testsuites() {
    drupal_set_title(t("Testsuites management"));
    $site = qatracker_get_current_site();

    # Getting all the entries
    $query = db_select('qatracker_testsuite');
    $query->fields('qatracker_testsuite', array('id', 'title'));
    $query->orderBy('qatracker_testsuite.id', 'DESC');
    $query->condition('qatracker_testsuite.siteid', $site->id);
    $result = $query->execute();

    # And genering the table
    $rows = array();
    foreach ($result as $record) {
        $query = db_select('qatracker_testsuite_testcase');
        $query->fields('qatracker_testsuite_testcase', array('testcaseid'));
        $query->condition('qatracker_testsuite_testcase.testsuiteid', $record->id);
        $testcases = $query->execute();

        $rows[] = array(
            $record->id,
            $record->title,
            $testcases->rowCount(),
            l(
                t("Edit"),
                "admin/config/services/qatracker/testsuites/".$record->id."/edit",
                array(
                    'attributes' => array(
                        'class' => array('module-link', 'module-link-configure'),
                    ),
                )
            )
        );
    }

    $items[] = array(
        '#type' => 'link',
        '#prefix' => '<ul class="action-links"><li>',
        '#title' => t('Add a testsuite'),
        '#suffix' => '</li></ul>',
        '#href' => 'admin/config/services/qatracker/testsuites/add',
    );

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('ID'),
            t('Title'),
            t('Testcases'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    return $items;
}

function qatracker_admin_testsuites_edit() {
    global $qatracker_testsuite_testcase_status;

    $site = qatracker_get_current_site();
    drupal_set_title(t("Edit a testsuite"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_select('qatracker_testsuite');
        $query->condition('qatracker_testsuite.id', $args[5]);
        $query->fields('qatracker_testsuite', array('id', 'title'));
        $result = $query->execute();
        $entry = $result->fetch();
    }
    else {
        $entry = new stdClass();
        $entry->title = "";
    }

    $form = array();
    $form['qatracker_testsuite'] = array(
        '#type' => 'fieldset',
        '#title' => t('Testsuite'),
    );

    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_testsuite']['qatracker_testsuite_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->id,
            '#description' => t("ID of the testsuite"),
            '#required' => TRUE,
            '#disabled' => TRUE,
        );
    }

    $form['qatracker_testsuite']['qatracker_testsuite_title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#default_value' => $entry->title,
        '#description' => t("Name of the testsuite"),
        '#required' => TRUE,
    );

    $form['qatracker_testsuite']['actions'] = array('#type' => 'actions');
    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_testsuite']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Save'),
            '#submit' => array('qatracker_admin_testsuites_edit_submit'),
            '#limit_validation_errors' => array(
                array('add', 'qatracker_testsuite_testcase_id'),
                array('add', 'qatracker_testsuite_testcase_status'),
                array('add', 'qatracker_testsuite_testcase_weight'),
            ),
        );
    }
    else {
        $form['qatracker_testsuite']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Create'),
            '#submit' => array('qatracker_admin_testsuites_edit_submit'),
        );
    }

    $form['qatracker_testsuite']['actions']['cancel'] = array(
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/testsuites'),
    );

    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_testcases'] = array(
            '#type' => 'fieldset',
            '#title' => t('Testcases'),
        );

        # Get the list of testcase in the suite
        $query = db_select('qatracker_testsuite_testcase');
        $query->fields('qatracker_testsuite_testcase', array('testcaseid', 'status', 'weight'));
        $query->condition('qatracker_testsuite_testcase.testsuiteid', $entry->id);
        $query->orderBy('qatracker_testsuite_testcase.status', 'ASC');
        $query->orderBy('qatracker_testsuite_testcase.weight', 'ASC');
        $query->orderBy('qatracker_testsuite_testcase.testcaseid', 'DESC');
        $testsuite_testcases = $query->execute()->fetchAllAssoc("testcaseid");

        # Get the list of all testcases
        $query = db_select('qatracker_testcase');
        if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
            $query->leftJoin('qatracker_testcase_identifier', 'qatracker_testcase_identifier', 'qatracker_testcase_identifier.testcaseid=qatracker_testcase.id');
            $query->addField('qatracker_testcase_identifier', 'title', 'identifier');
        }
        else {
            $query->addField('qatracker_testcase', 'id', 'identifier');
        }

        $query->fields('qatracker_testcase', array('id', 'title'));
        $query->condition('qatracker_testcase.siteid', $site->id);
        $query->orderBy('qatracker_testcase.id', 'DESC');
        $testcases = $query->execute()->fetchAllAssoc("id");

        $select = array();
        foreach ($testcases as $id => $testcase) {
            if (array_key_exists($id, $testsuite_testcases)) {
                continue;
            }
            $select[$testcase->id] = ($testcase->identifier ? $testcase->identifier : $testcase->id)." | ".$testcase->title;
        }

        $rows = array();
        foreach ($testsuite_testcases as $testcase) {
            $testcase->testcase = $testcases[$testcase->testcaseid];

            $rows[] = array(
                $testcase->testcase->identifier ? $testcase->testcase->identifier : $testcase->testcase->id,
                $testcase->testcase->title,
                $qatracker_testsuite_testcase_status[$testcase->status],
                $testcase->weight,
                l(
                    t("Edit"),
                    "admin/config/services/qatracker/testsuites/".$entry->id."/testcase/".$testcase->testcase->id."/edit",
                    array(
                        'attributes' => array(
                            'class' => array('module-link', 'module-link-configure'),
                        ),
                    )
                )
            );
        }

        $form['qatracker_testcases']['list'] = array(
            '#theme' => 'table',
            '#header' => array(
                t('ID'),
                t('Title'),
                t('Status'),
                t('Weight'),
                t('Actions')
            ),
            '#rows' => $rows,
        );

        $form['qatracker_testcases']['add'] = array(
            '#type' => 'fieldset',
            '#title' => t('Add a testcase'),
        );

        $form['qatracker_testcases']['add']['qatracker_testsuite_testcase_id'] = array(
            '#type' => 'select',
            '#title' => t('Testcase'),
            '#options' => $select,
            '#required' => TRUE,
        );

        $form['qatracker_testcases']['add']['qatracker_testsuite_testcase_status'] = array(
            '#type' => 'select',
            '#title' => t('Status'),
            '#options' => $qatracker_testsuite_testcase_status,
            '#required' => TRUE,
        );

        $form['qatracker_testcases']['add']['qatracker_testsuite_testcase_weight'] = array(
            '#type' => 'weight',
            '#title' => t('Weight'),
            '#default_value' => 0,
            '#description' => t("Weight for ordering"),
            '#required' => TRUE,
        );

        $form['qatracker_testcases']['add']['actions'] = array('#type' => 'actions');
        $form['qatracker_testcases']['add']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Add'),
            '#submit' => array('qatracker_admin_testsuites_add_submit'),
        );
    }

    return $form;
}

function qatracker_admin_testsuites_add_submit($form, &$form_state) {
    global $user;

    $args = arg();
    $test_testsuiteid = $args[5];

    $test_id = $form['qatracker_testcases']['add']['qatracker_testsuite_testcase_id']['#value'];
    $test_status = $form['qatracker_testcases']['add']['qatracker_testsuite_testcase_status']['#value'];
    $test_weight = $form['qatracker_testcases']['add']['qatracker_testsuite_testcase_weight']['#value'];

    $query = db_insert('qatracker_testsuite_testcase');
    $query->fields(array(
        'testsuiteid' => $test_testsuiteid,
        'testcaseid' => $test_id,
        'status' => $test_status,
        'weight' => $test_weight,
    ));
    $testsuiteid = $query->execute();

    watchdog("qatracker",
        t("Added new testcase @testcaseid to testsuite @testsuiteid"),
        array('@testcaseid' => $test_id, '@testsuiteid' => $test_testsuiteid)
    );

}

function qatracker_admin_testsuites_edit_submit($form, &$form_state) {
    global $user;

    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];
    $site = qatracker_get_current_site();

    if ($action == "edit" && count($args) == 7) {
        $query = db_update('qatracker_testsuite');
        $query->condition('qatracker_testsuite.id', $args[5]);
        $query->condition('qatracker_testsuite.siteid', $site->id);
    }
    else {
        $query = db_insert('qatracker_testsuite');
    }

    $query->fields(array(
        'siteid' => $site->id,
        'title' => $form['qatracker_testsuite']['qatracker_testsuite_title']['#value'],
    ));

    $retval = $query->execute();
    if ($action == "edit") {
        $testsuiteid = $args[5];
    }
    else {
        $testsuiteid = $retval;
    }

    if ($action == "edit") {
        watchdog("qatracker",
            t("Updated testsuite with ID: @testsuiteid"),
            array('@testsuiteid' => $testsuiteid)
        );
    }
    else {
        watchdog("qatracker",
            t("Added testsuite with ID: @testsuiteid"),
            array('@testsuiteid' => $testsuiteid)
        );
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/testsuites/'.$testsuiteid.'/edit';
    return $form;
}

function qatracker_admin_testsuites_testcase_edit() {
    global $qatracker_testsuite_testcase_status;
    $site = qatracker_get_current_site();

    drupal_set_title(t("Edit a testcase in testsuite"));
    $args = arg();

    $query = db_select('qatracker_testsuite_testcase');
    if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
        $query->leftJoin('qatracker_testcase_identifier', 'qatracker_testcase_identifier', 'qatracker_testcase_identifier.testcaseid=qatracker_testsuite_testcase.testcaseid');
        $query->addField('qatracker_testcase_identifier', 'title', 'identifier');
    }
    else {
        $query->addField('qatracker_testsuite_testcase', 'testcaseid', 'identifier');
    }
    $query->condition('qatracker_testsuite_testcase.testsuiteid', $args[5]);
    $query->condition('qatracker_testsuite_testcase.testcaseid', $args[7]);
    $query->fields('qatracker_testsuite_testcase', array('status', 'weight'));
    $result = $query->execute();
    $entry = $result->fetch();

    $form = array();
    $form['testcase'] = array(
        '#type' => 'fieldset',
        '#title' => t('Testcase'),
    );

    $form['testcase']['qatracker_testsuite_testcase_testcaseid'] = array(
        '#type' => 'textfield',
        '#title' => t('ID'),
        '#default_value' => $entry->identifier ? $entry->identifier : $args[7],
        '#description' => t("ID of the testcase"),
        '#required' => FALSE,
        '#disabled' => TRUE,
    );

    $form['testcase']['qatracker_testsuite_testcase_status'] = array(
        '#type' => 'select',
        '#title' => t('Status'),
        '#default_value' => $entry->status,
        '#options' => $qatracker_testsuite_testcase_status,
        '#required' => TRUE,
    );

    $form['testcase']['qatracker_testsuite_testcase_weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight'),
        '#default_value' => $entry->weight,
        '#description' => t("Weight for ordering"),
        '#required' => TRUE,
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save'),
    );
    $form['actions']['cancel'] = array(
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/testsuites/'.$args[5].'/edit'),
    );

    return $form;
}

function qatracker_admin_testsuites_testcase_edit_submit($form, &$form_state) {
    $args = arg();

    $query = db_update('qatracker_testsuite_testcase');
    $query->condition('qatracker_testsuite_testcase.testsuiteid', $args[5]);
    $query->condition('qatracker_testsuite_testcase.testcaseid', $args[7]);

    $query->fields(array(
        'weight' => $form['testcase']['qatracker_testsuite_testcase_weight']['#value'],
        'status' => $form['testcase']['qatracker_testsuite_testcase_status']['#value'],
    ));
    $query->execute();

    watchdog("qatracker",
        t("Updated testcase @testcaseid from testsuite @testsuiteid"),
        array('@testcaseid' => $args[7], '@testsuiteid' => $args[5])
    );

    $form_state['redirect'] = 'admin/config/services/qatracker/testsuites/'.$args[5].'/edit';
    return $form;
}

?>
