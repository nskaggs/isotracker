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

function qatracker_admin_products() {
    global $qatracker_product_status, $qatracker_product_type, $user;

    drupal_set_title(t("Products management"));
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Create a rid => role mapping table
    $query = db_select('role');
    $query->fields('role', array('rid', 'name'));
    $mapping = $query->execute()->fetchAllKeyed();

    $list_rids = array();
    if (!qatracker_acl("administer site configuration", array("admin"), $site)) {
        # Build a list of rids for the current user
        foreach ($user->roles as $entry) {
            $rid = array_search($entry, $mapping);
            if ($rid) {
                $list_rids[] = $rid;
            }
        }
    }

    # Getting all the entries
    $query = db_select('qatracker_product');
    $query->fields('qatracker_product', array('id', 'title', 'type', 'status', 'ownerrole'));
    $query->leftjoin('qatracker_product_family', 'qatracker_product_family', 'qatracker_product.familyid = qatracker_product_family.id');
    $query->addField('qatracker_product_family', 'title', 'family');
    $query->orderBy('qatracker_product.status', 'ASC');
    $query->orderBy('qatracker_product.title', 'ASC');
    $query->condition('qatracker_product.siteid', $site->id);
    if ($list_rids) {
        $query->condition('qatracker_product.ownerrole', $list_rids, 'IN');
    }
    $result = $query->execute();

    # And generating the table
    $rows = array();
    foreach ($result as $record) {
        $rows[] = array(
            $record->title,
            $record->family,
            array_key_exists($record->ownerrole, $mapping) ? $mapping[$record->ownerrole] : $record->ownerrole,
            $qatracker_product_type[$record->type],
            $qatracker_product_status[$record->status],
            l(
                t("Downloads"),
                "admin/config/services/qatracker/products/".$record->id."/downloads",
                array(
                    'attributes' => array(
                        'class' => array('module-link', 'module-link-configure'),
                    ),
                )
            )." ".l(
                t("Linked testsuites"),
                "admin/config/services/qatracker/products/".$record->id."/testsuites",
                array(
                    'attributes' => array(
                        'class' => array('module-link', 'module-link-configure'),
                    ),
                )
            )." ".l(
                t("Edit"),
                "admin/config/services/qatracker/products/".$record->id."/edit",
                array(
                    'attributes' => array(
                        'class' => array('module-link', 'module-link-configure'),
                    ),
                )
            )
        );
    }

    if (!$list_rids) {
        $items[] = array(
            '#type' => 'link',
            '#prefix' => '<ul class="action-links"><li>',
            '#title' => t('Add a product'),
            '#suffix' => '</li></ul>',
            '#href' => 'admin/config/services/qatracker/products/add',
        );
    }

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('Title'),
            t('Family'),
            t('Owner'),
            t('Type'),
            t('Status'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    return $items;
}

function qatracker_admin_products_edit($form, &$form_state) {
    global $qatracker_product_status, $qatracker_product_type;

    # Create a rid => role mapping table
    $query = db_select('role');
    $query->fields('role', array('rid', 'name'));
    $query->orderBy('role.rid', 'ASC');
    $mapping = array(t("None"));
    $mapping = array_merge($mapping, $query->execute()->fetchAllKeyed());

    drupal_set_title(t("Edit a product"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        if (!qatracker_acl_product($args[5])) {
            drupal_access_denied();
            exit;
        }

        $query = db_select('qatracker_product');
        $query->condition('qatracker_product.id', $args[5]);
        $query->fields('qatracker_product', array('id', 'familyid', 'title', 'type', 'status', 'ownerrole', 'buginstruction'));
        $result = $query->execute();
        $entry = $result->fetch();
    }
    else {
        $entry = new stdClass();
        $entry->title = "";
        $entry->familyid = 0;
        $entry->type = 0;
        $entry->status = 0;
        $entry->ownerrole = Null;
        $entry->buginstruction = "";
    }

    $family_query = db_select('qatracker_product_family');
    $family_query->condition('qatracker_product_family.siteid', qatracker_get_current_site()->id);
    $family_query->orderBy('qatracker_product_family.title', 'ASC');
    $family_query->fields('qatracker_product_family', array('id', 'title'));
    $family_result = $family_query->execute();

    $qatracker_product_families = array();
    $qatracker_product_families[0] = t('None');
    foreach ($family_result as $family_record) {
        $qatracker_product_families[$family_record->id] = $family_record->title;
    }

    $form = array();
    $form['qatracker_product'] = array(
        '#type' => 'fieldset',
        '#title' => t('Product'),
    );
    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_product']['qatracker_product_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->id,
            '#description' => t("ID of the product"),
            '#required' => TRUE,
            '#disabled' => TRUE,
        );
    }

    $form['qatracker_product']['qatracker_product_title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#default_value' => $entry->title,
        '#description' => t("Name of the product"),
        '#required' => TRUE,
    );

    $form['qatracker_product']['qatracker_product_familyid'] = array(
        '#type' => 'select',
        '#title' => t('Family'),
        '#options' => $qatracker_product_families,
        '#default_value' => $entry->familyid,
        '#description' => t("Family of the product"),
        '#required' => FALSE,
    );

    $form['qatracker_product']['qatracker_product_type'] = array(
        '#type' => 'select',
        '#title' => t('Type'),
        '#options' => $qatracker_product_type,
        '#default_value' => $entry->type,
        '#description' => t("Product type"),
        '#required' => TRUE,
    );

    $form['qatracker_product']['qatracker_product_status'] = array(
        '#type' => 'select',
        '#title' => t('Status'),
        '#options' => $qatracker_product_status,
        '#default_value' => $entry->status,
        '#description' => t("Product status"),
        '#required' => TRUE,
    );

    $form['qatracker_product']['qatracker_product_ownerrole'] = array(
        '#type' => 'select',
        '#title' => t('Owner'),
        '#options' => $mapping,
        '#default_value' => $entry->ownerrole,
        '#description' => t("Owner role (will have access to edit product, linked testsuites, linked downloads and builds."),
        '#required' => FALSE,
    );

    $form['qatracker_product']['qatracker_product_buginstruction'] = array(
        '#type' => 'textarea',
        '#title' => t('Bug reporting instructions'),
        '#default_value' => $entry->buginstruction,
        '#description' => t("How to report a bug against this product."),
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
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/products'),
    );

    return $form;
}

function qatracker_admin_products_edit_submit($form, &$form_state) {
    global $user;

    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        if (!qatracker_acl_product($args[5])) {
            drupal_access_denied();
            exit;
        }

        $query = db_update('qatracker_product');
        $query->condition('qatracker_product.id', $args[5]);
    }
    else {
        $query = db_insert('qatracker_product');
    }

    if ($form['qatracker_product']['qatracker_product_familyid']['#value'] == 0) {
        $form['qatracker_product']['qatracker_product_familyid']['#value'] = Null;
    }
    if ($form['qatracker_product']['qatracker_product_ownerrole']['#value'] == 0) {
        $form['qatracker_product']['qatracker_product_ownerrole']['#value'] = Null;
    }

    $query->fields(array(
        'siteid' => qatracker_get_current_site()->id,
        'familyid' => $form['qatracker_product']['qatracker_product_familyid']['#value'],
        'title' => $form['qatracker_product']['qatracker_product_title']['#value'],
        'type' => $form['qatracker_product']['qatracker_product_type']['#value'],
        'status' => $form['qatracker_product']['qatracker_product_status']['#value'],
        'ownerrole' => $form['qatracker_product']['qatracker_product_ownerrole']['#value'],
        'buginstruction' => $form['qatracker_product']['qatracker_product_buginstruction']['#value'],
    ));

    $productid = $query->execute();

    if ($action == "edit") {
        watchdog("qatracker",
            t("Updated product with ID: @productid"),
            array('@productid' => $args[5])
        );
    }
    else {
        watchdog("qatracker",
            t("Added product with ID: @productid"),
            array('@productid' => $productid)
        );
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/products';
    return $form;
}

?>
