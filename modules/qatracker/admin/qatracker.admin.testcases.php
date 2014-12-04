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

function qatracker_admin_testcases() {
    drupal_set_title(t("Testcases management"));
    $site = qatracker_get_current_site();

    # Getting all the entries
    $query = db_select('qatracker_testcase');
    if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
        $query->leftJoin('qatracker_testcase_identifier', 'qatracker_testcase_identifier', 'qatracker_testcase_identifier.testcaseid=qatracker_testcase.id');
        $query->addField('qatracker_testcase_identifier', 'title', 'identifier');
    }
    else {
        $query->addField('qatracker_testcase', 'id', 'identifier');
    }
    $query->fields('qatracker_testcase', array('id', 'title'));
    $query->orderBy('qatracker_testcase.id', 'DESC');
    $query->condition('qatracker_testcase.siteid', $site->id);
    $result = $query->execute();

    # And genering the table
    $rows = array();
    foreach ($result as $record) {
        $query = db_select('qatracker_testsuite_testcase');
        $query->fields('qatracker_testsuite', array('id', 'title'));
        $query->leftJoin('qatracker_testsuite', 'qatracker_testsuite', 'qatracker_testsuite.id=qatracker_testsuite_testcase.testsuiteid');
        $query->condition('qatracker_testsuite_testcase.testcaseid', $record->id);
        $testsuites = $query->execute();

        $suites = array();
        foreach ($testsuites as $testsuite) {
            $suites[] = l($testsuite->title, "admin/config/services/qatracker/testsuites/".$testsuite->id."/edit");
        }

        $rows[] = array(
            $record->identifier ? $record->identifier : $record->id,
            $record->title,
            implode($suites, "<br />"),
            l(
                t("Edit"),
                "admin/config/services/qatracker/testcases/".$record->id."/edit",
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
        '#title' => t('Add a testcase'),
        '#suffix' => '</li></ul>',
        '#href' => 'admin/config/services/qatracker/testcases/add',
    );

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('ID'),
            t('Title'),
            t('Suites'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    return $items;
}

function qatracker_admin_testcases_edit($form, &$form_state) {
    $site = qatracker_get_current_site();

    drupal_set_title(t("Edit a testcase"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
        $db_id = FALSE;
    }
    else {
        $db_id = TRUE;
    }

    if ($action == "edit" && count($args) == 7) {
        $query = db_select('qatracker_testcase');
        if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
            $query->leftJoin('qatracker_testcase_identifier', 'qatracker_testcase_identifier', 'qatracker_testcase_identifier.testcaseid=qatracker_testcase.id');
            $query->addField('qatracker_testcase_identifier', 'title', 'identifier');
        }
        else {
            $query->addField('qatracker_testcase', 'id', 'identifier');
        }
        $query->condition('qatracker_testcase.id', $args[5]);
        $query->fields('qatracker_testcase', array('id', 'title', 'link'));
        $result = $query->execute();
        $entry = $result->fetch();

        $query = db_select('qatracker_testcase_revision');
        $query->fields('qatracker_testcase_revision', array('text'));
        $query->condition('qatracker_testcase_revision.testcaseid', $args[5]);
        $query->orderBy('qatracker_testcase_revision.id', 'DESC');
        $current_revision = $query->execute()->fetchField();
    }
    else {
        $entry = new stdClass();
        $entry->identifier = "";
        $entry->id = "";
        $entry->title = "";
        $entry->link = "";
        $current_revision = array_key_exists("testcase_template", $site->options) ? $site->options['testcase_template'] : "";
    }

    $form = array();
    $form['qatracker_testcase'] = array(
        '#type' => 'fieldset',
        '#title' => t('Testcase'),
    );
    if (($action == "edit" && count($args) == 7) || $db_id == FALSE) {
        $form['qatracker_testcase']['qatracker_testcase_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->identifier ? $entry->identifier : $entry->id,
            '#description' => t("ID of the testcase"),
            '#required' => $db_id,
            '#disabled' => $db_id,
        );
    }

    $form['qatracker_testcase']['qatracker_testcase_title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#default_value' => $entry->title,
        '#description' => t("Name of the testcase"),
        '#required' => TRUE,
    );

    $form['qatracker_testcase']['qatracker_testcase_link'] = array(
        '#type' => 'textfield',
        '#title' => t('Link'),
        '#default_value' => $entry->link,
        '#description' => t("Link to additional information"),
        '#maxlength' => 1000,
        '#required' => FALSE,
    );

    $form['qatracker_testcase_revision']['qatracker_testcase_revision_text'] = array(
        '#type' => 'textarea',
        '#title' => t('Testcase'),
        '#default_value' => $current_revision,
        '#description' => t("Testcase text, some html is allowed. FAMILY is a placeholder replaced by the product family or the product name if it doesn't have a family."),
        '#required' => FALSE,
    );

    $form['actions'] = array('#type' => 'actions');
    if ($action == "edit" && count($args) == 7) {
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Save'),
        );
    }
    else {
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Create'),
        );
    }

    $form['actions']['cancel'] = array(
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/testcases'),
    );

    return $form;
}

function qatracker_admin_testcases_edit_validate($form, &$form_state) {
    $site = qatracker_get_current_site();
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
        $identifier = $form['qatracker_testcase']['qatracker_testcase_id']['#value'];
        if (!$identifier) {
            return;
        }

        $query = db_select('qatracker_testcase_identifier');
        $query->fields('qatracker_testcase_identifier', array('testcaseid'));
        $query->condition('qatracker_testcase_identifier.title', $identifier);
        $query->condition('qatracker_testcase_identifier.siteid', $site->id);

        if ($action == "edit" && count($args) == 7) {
            $query->condition('qatracker_testcase_identifier.testcaseid', $args[5], '<>');
        }
        $result = $query->execute()->fetch();

        if ($result) {
            form_set_error('qatracker_testcase_id', t("The ID you selected isn't unique."));
        }
    }
}

function qatracker_admin_testcases_edit_submit($form, &$form_state) {
    global $user;

    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];
    $site = qatracker_get_current_site();

    if ($action == "edit" && count($args) == 7) {
        $query = db_update('qatracker_testcase');
        $query->condition('qatracker_testcase.id', $args[5]);
        $query->condition('qatracker_testcase.siteid', $site->id);
    }
    else {
        $query = db_insert('qatracker_testcase');
    }

    $query->fields(array(
        'siteid' => $site->id,
        'title' => $form['qatracker_testcase']['qatracker_testcase_title']['#value'],
        'link' => $form['qatracker_testcase']['qatracker_testcase_link']['#value'],
    ));

    $retval = $query->execute();
    if ($action == "edit") {
        $testcaseid = $args[5];
    }
    else {
        $testcaseid = $retval;
    }

    # Add a new revision if needed
    $query = db_select('qatracker_testcase_revision');
    $query->fields('qatracker_testcase_revision', array('text'));
    $query->condition('qatracker_testcase_revision.testcaseid', $testcaseid);
    $query->orderBy('qatracker_testcase_revision.id', 'DESC');
    $current_revision = $query->execute()->fetchField();

    $new_revision = $form['qatracker_testcase_revision']['qatracker_testcase_revision_text']['#value'];
    if ($new_revision != $current_revision) {
        $query = db_insert('qatracker_testcase_revision');
        $query->fields(array(
            'text' => $new_revision,
            'testcaseid' => $testcaseid,
            'createdby' => $user->uid,
            'createdat' => date("Y-m-d H:i:s"),
        ));
        $query->execute();
    }

    if (array_key_exists("testcase_string_id", $site->options) && $site->options['testcase_string_id'] == 1) {
        # Remove any existing mapping
        $query = db_delete('qatracker_testcase_identifier');
        $query->condition('qatracker_testcase_identifier.testcaseid', $testcaseid);
        $query->condition('qatracker_testcase_identifier.siteid', $site->id);
        $query->execute();

        $identifier = $form['qatracker_testcase']['qatracker_testcase_id']['#value'];

        # Add the new mapping if needed
        if ($identifier && $testcaseid != $identifier) {
            $query = db_insert('qatracker_testcase_identifier');
            $query->fields(array(
                'testcaseid' => $testcaseid,
                'title' => $identifier,
                'siteid' => $site->id,
            ));
            $query->execute();
        }
    }

    if ($action == "edit") {
        watchdog("qatracker",
            t("Updated testcase with ID: @testcaseid"),
            array('@testcaseid' => $testcaseid)
        );
    }
    else {
        watchdog("qatracker",
            t("Added testcase with ID: @testcaseid"),
            array('@testcaseid' => $testcaseid)
        );
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/testcases';
    return $form;
}

?>
