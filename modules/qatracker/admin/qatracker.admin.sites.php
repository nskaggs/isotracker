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

function qatracker_admin_sites() {
    drupal_set_title(t("Sites management"));
    $items = array();

    # Create a rid => role mapping table
    $query = db_select('role');
    $query->fields('role', array('rid', 'name'));
    $mapping = $query->execute()->fetchAllKeyed();

    # Get the list of instances
    $query = db_select('qatracker_site');
    $query->fields('qatracker_site', array('id', 'subdomain', 'title', 'userrole', 'adminrole', 'testcaserole'));
    $query->orderBy('qatracker_site.id', 'ASC');
    $result = $query->execute();

    $rows = array();
    foreach ($result as $record) {
        $rows[] = array(
            $record->title,
            l($record->subdomain, "http://".$record->subdomain),
            array_key_exists($record->userrole, $mapping) ? $mapping[$record->userrole] : $record->userrole,
            array_key_exists($record->adminrole, $mapping) ? $mapping[$record->adminrole] : $record->adminrole,
            array_key_exists($record->testcaserole, $mapping) ? $mapping[$record->testcaserole] : $record->testcaserole,
            l(
                t("Edit"),
                "admin/config/services/qatracker/sites/".$record->id."/edit",
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
        '#title' => t('Add a site'),
        '#suffix' => '</li></ul>',
        '#href' => 'admin/config/services/qatracker/sites/add',
    );

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('Title'),
            t('Subdomain'),
            t('User role'),
            t('Admin role'),
            t('Testcase management role'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => t("There's currently no way of removing/disabling a site as the DB schema doesn't allow it.<br />
Manually removing a site from the DB is also to be avoided because of the cascade effect on all the other objects."),
        '#suffix' => '</p>',
    );

    return $items;
}

function qatracker_admin_sites_edit($form, &$form_state) {
    drupal_set_title(t("Edit a site"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    # Create a rid => role mapping table
    $query = db_select('role');
    $query->fields('role', array('rid', 'name'));
    $mapping = $query->execute()->fetchAllKeyed();

    if ($action == "edit" && count($args) == 7) {
        $query = db_select('qatracker_site');
        $query->condition('qatracker_site.id', $args[5]);
        $query->fields('qatracker_site', array('id', 'subdomain', 'title', 'userrole', 'adminrole', 'testcaserole'));
        $result = $query->execute();
        $entry = $result->fetch();
    }
    else {
        $entry = new stdClass();
        $entry->subdomain = "";
        $entry->title = "";
        $entry->userrole = Null;
        $entry->adminrole = Null;
        $entry->testcaserole = Null;
    }

    $form = array();

    $form['qatracker_site'] = array(
        '#type' => 'fieldset',
        '#title' => t('Site'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
    );
    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_site']['qatracker_site_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->id,
            '#description' => t("ID of the site"),
            '#required' => TRUE,
            '#disabled' => TRUE,
        );
    }

    $form['qatracker_site']['qatracker_site_subdomain'] = array(
        '#type' => 'textfield',
        '#title' => t('Subdomain'),
        '#default_value' => $entry->subdomain,
        '#description' => t("URL for this site"),
        '#required' => TRUE,
    );

    $form['qatracker_site']['qatracker_site_title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#default_value' => $entry->title,
        '#description' => t("Title of the site"),
        '#required' => TRUE,
    );

    $form['qatracker_role'] = array(
        '#type' => 'fieldset',
        '#title' => t('Permissions'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
    );

    $form['qatracker_role']['qatracker_role_userrole'] = array(
        '#type' => 'select',
        '#title' => t('User role'),
        '#default_value' => $entry->userrole,
        '#options' => $mapping,
        '#description' => t("Role used for user access control"),
        '#required' => TRUE,
    );

    $form['qatracker_role']['qatracker_role_adminrole'] = array(
        '#type' => 'select',
        '#title' => t('Admin role'),
        '#default_value' => $entry->adminrole,
        '#options' => $mapping,
        '#description' => t("Role used for admin access control"),
        '#required' => TRUE,
    );

    $form['qatracker_role']['qatracker_role_testcaserole'] = array(
        '#type' => 'select',
        '#title' => t('Testcase role'),
        '#default_value' => $entry->testcaserole,
        '#options' => $mapping,
        '#description' => t("Role used for limited admin access to testcases"),
        '#required' => TRUE,
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
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/sites'),
    );

    return $form;
}

function qatracker_admin_sites_edit_submit($form, &$form_state) {
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_update('qatracker_site');
        $query->condition('qatracker_site.id', $args[5]);
    }
    else {
        $query = db_insert('qatracker_site');
    }
    $query->fields(array(
        'subdomain' => $form['qatracker_site']['qatracker_site_subdomain']['#value'],
        'title' => $form['qatracker_site']['qatracker_site_title']['#value'],
        'userrole' => $form['qatracker_role']['qatracker_role_userrole']['#value'],
        'adminrole' => $form['qatracker_role']['qatracker_role_adminrole']['#value'],
        'testcaserole' => $form['qatracker_role']['qatracker_role_testcaserole']['#value'],
    ));
    $siteid = $query->execute();

    if ($action == "edit") {
        watchdog("qatracker",
            t("Updated site with ID: @siteid"),
            array('@siteid' => $args[5])
        );
    }
    else {
        watchdog("qatracker",
            t("Added site with ID: @siteid"),
            array('@siteid' => $siteid)
        );
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/sites';
    return $form;
}

?>
