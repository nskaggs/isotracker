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

function qatracker_admin_series() {
    global $qatracker_milestone_series_status;

    drupal_set_title(t("Series management"));
    $site = qatracker_get_current_site();

    $admin_acl = qatracker_acl('administer site configuration', array('admin'));
    $product_acl = qatracker_acl('administer site configuration', array('admin', 'product'));

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Getting all the entries
    $query = db_select('qatracker_milestone_series');
    $query->fields('qatracker_milestone_series', array('id', 'title', 'status'));
    $query->orderBy('qatracker_milestone_series.title', 'ASC');
    $query->condition('qatracker_milestone_series.siteid', $site->id);
    $result = $query->execute();

    # And generating the table
    $rows = array();
    foreach ($result as $record) {
        $options = "";
        if ($admin_acl) {
            $options .= l(
                t("Edit"),
                "admin/config/services/qatracker/series/".$record->id."/edit",
                array(
                    'attributes' => array(
                        'class' => array('module-link', 'module-link-configure'),
                    ),
                )
            );
        }
        if ($product_acl) {
            $options .= l(
                t("Manifest"),
                "admin/config/services/qatracker/series/".$record->id."/manifest",
                array(
                    'attributes' => array(
                        'class' => array('module-link', 'module-link-configure'),
                    ),
                )
            );
        }

        $rows[] = array(
            $record->title,
            $qatracker_milestone_series_status[$record->status],
            $options
        );
    }

    $items[] = array(
        '#type' => 'link',
        '#prefix' => '<ul class="action-links"><li>',
        '#title' => t('Add a series'),
        '#suffix' => '</li></ul>',
        '#href' => 'admin/config/services/qatracker/series/add',
    );

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('Title'),
            t('Status'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    return $items;
}

function qatracker_admin_series_edit($form, &$form_state) {
    global $qatracker_milestone_series_status;

    drupal_set_title(t("Edit a series"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_select('qatracker_milestone_series');
        $query->condition('qatracker_milestone_series.id', $args[5]);
        $query->fields('qatracker_milestone_series', array('id', 'title', 'status'));
        $result = $query->execute();
        $entry = $result->fetch();
    }
    else {
        $entry = new stdClass();
        $entry->title = "";
        $entry->status = 0;
    }

    $form = array();

    $form['qatracker_milestone_series'] = array(
        '#type' => 'fieldset',
        '#title' => t('Series'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
    );
    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_milestone_series']['qatracker_milestone_series_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->id,
            '#description' => t("ID of the series"),
            '#required' => TRUE,
            '#disabled' => TRUE,
        );
    }

    $form['qatracker_milestone_series']['qatracker_milestone_series_title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#default_value' => $entry->title,
        '#description' => t("Title of the series"),
        '#required' => TRUE,
    );

    $form['qatracker_milestone_series']['qatracker_milestone_series_status'] = array(
        '#type' => 'select',
        '#title' => t('Status'),
        '#options' => $qatracker_milestone_series_status,
        '#default_value' => $entry->status,
        '#description' => t("Series status"),
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
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/series'),
    );

    return $form;
}

function qatracker_admin_series_edit_submit($form, &$form_state) {
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_update('qatracker_milestone_series');
        $query->condition('qatracker_milestone_series.id', $args[5]);
    }
    else {
        $query = db_insert('qatracker_milestone_series');
    }
    $query->fields(array(
        'siteid' => qatracker_get_current_site()->id,
        'title' => $form['qatracker_milestone_series']['qatracker_milestone_series_title']['#value'],
        'status' => $form['qatracker_milestone_series']['qatracker_milestone_series_status']['#value'],
    ));
    $seriesid = $query->execute();

    if ($action == "edit") {
        watchdog("qatracker",
            t("Updated series with ID: @seriesid"),
            array('@seriesid' => $args[5])
        );
    }
    else {
        watchdog("qatracker",
            t("Added series with ID: @seriesid"),
            array('@seriesid' => $seriesid)
        );
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/series';
    return $form;
}

function qatracker_admin_series_manifest($form, &$form_state) {
    global $qatracker_milestone_series_status,
           $qatracker_milestone_series_manifest_status,
           $user;

    $site = qatracker_get_current_site();
    drupal_set_title(t("Manifest"));
    $args = arg();
    $index = count($args)-1;
    $seriesid = arg(5);

    $items = array();

    # Get the roles of the user
    $list_rids = array();
    if (!qatracker_acl("administer site configuration", array("admin"), $site)) {
        # Build a list of rids for the current user
        foreach ($user->roles as $rid => $role) {
            $list_rids[] = $rid;
        }
    }

    # Get all the products on the manifest
    $query = db_select('qatracker_milestone_series_manifest');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_milestone_series_manifest.productid');
    $query->leftJoin('qatracker_product_family', 'qatracker_product_family', 'qatracker_product_family.id = qatracker_product.familyid');
    $query->fields('qatracker_milestone_series_manifest', array('contact', 'status'));
    $query->fields('qatracker_product', array('id', 'title'));
    $query->addfield('qatracker_product_family', 'title', 'family');
    $query->orderBy('qatracker_product_family.title', 'ASC');
    $query->orderBy('qatracker_product.title', 'ASC');
    $query->condition('qatracker_milestone_series_manifest.seriesid', $seriesid);
    if ($list_rids) {
        $query->condition('qatracker_product.ownerrole', $list_rids, 'IN');
    }
    $products = $query->execute()->fetchAll();

    $items['remove'] = array(
        '#type' => 'fieldset',
        '#title' => t('Current entries'),
    );

    function new_table($rows) {
        return array(
            '#type' => 'tableselect',
            '#header' => array(
                array('data' => t('Title')),
                array('data' => t('Status')),
                array('data' => t('Contact'), 'style' => 'width:20em'),
            ),
            '#options' => $rows,
            '#empty' => t("There are no products in the manifest yet.")
        );
    }

    $rows = array();
    $family = NULL;
    $product_ids = array();
    foreach ($products as $record) {
        $product_ids[] = $record->id;
        if ($family && $family != $record->family) {
            $items['remove'][] = new_table($rows);
            $rows = array();
        }

        $rows[$record->id] = array(
            $record->title . " (".$record->id.")",
            $qatracker_milestone_series_manifest_status[$record->status],
            $record->contact,
        );
        $family = $record->family;
    }
    $items['remove'][] = new_table($rows);

    if (count($rows) > 0) {
        $items['remove']['enable'] = array(
            '#type' => 'submit',
            '#value' => t('Enable'),
            '#submit' => array('qatracker_admin_series_manifest_submit'),
            '#limit_validation_errors' => array(array('add', 'qatracker_milestone_series_manifest_productid'),
                                                array('add', 'qatracker_milestone_series_manifest_contact'),
            )
        );
        $items['remove']['disable'] = array(
            '#type' => 'submit',
            '#value' => t('Disable'),
            '#submit' => array('qatracker_admin_series_manifest_submit'),
            '#limit_validation_errors' => array(array('add', 'qatracker_milestone_series_manifest_productid'),
                                                array('add', 'qatracker_milestone_series_manifest_contact'),
            )
        );
        $items['remove']['remove'] = array(
            '#type' => 'submit',
            '#value' => t('Remove'),
            '#submit' => array('qatracker_admin_series_manifest_submit'),
            '#limit_validation_errors' => array(array('add', 'qatracker_milestone_series_manifest_productid'),
                                                array('add', 'qatracker_milestone_series_manifest_contact'),
            )
        );
    }

    # Get the list of valid products
    $query = db_select('qatracker_product');
    $query->condition('qatracker_product.siteid', $site->id);
    $query->orderBy('qatracker_product.title', 'ASC');
    $query->fields('qatracker_product', array('id', 'title'));
    $query->condition('qatracker_product.status', "0");
    if ($product_ids) {
        $query->condition('qatracker_product.id', $product_ids, 'NOT IN');
    }
    if ($list_rids) {
        $query->condition('qatracker_product.ownerrole', $list_rids, 'IN');
    }
    $query->orderBy('qatracker_product.title', 'ASC');
    $qatracker_products = $query->execute()->fetchAllKeyed(1,0);

    $items['add'] = array(
        '#type' => 'fieldset',
        '#title' => t('Add an entry'),
    );

    $items['add']['qatracker_milestone_series_manifest_productid'] = array(
        '#type' => 'select',
        '#title' => 'Product',
        '#options' => $qatracker_products,
        '#description' => 'An active product for this site',
        '#required' => TRUE,
    );

    $items['add']['qatracker_milestone_series_manifest_contact'] = array(
        '#type' => 'textfield',
        '#title' => 'Contact',
        '#description' => 'The name of who to contact for that product',
        '#required' => TRUE,
    );

    $items['add']['add'] = array(
        '#type' => 'submit',
        '#submit' => array('qatracker_admin_series_manifest_submit'),
        '#value' => t('Add'),
    );

    return $items;
}

function qatracker_admin_series_manifest_submit($form, &$form_state) {
    $site = qatracker_get_current_site();
    $args = arg();
    $seriesid = $args[5];

    # Magic to extract all the selected items
    $selection = array();
    foreach ($form_state['input'] as $element) {
        if (!is_array($element)) {
            continue;
        }
        foreach ($element as $key => $value) {
            if ($key == $value) {
                if (!qatracker_acl_product($key)) {
                    continue;
                }
                $selection[] = $key;
            }
        }
    }

    # FIXME: Would be nice avoiding matching translated strings...
    switch ($form_state['values']['op']) {
        case t('Add'):
            if (!qatracker_acl_product($form['add']['qatracker_milestone_series_manifest_productid']['#value'])) {
                drupal_access_denied();
                exit;
            }

            $query = db_insert('qatracker_milestone_series_manifest');
            $query->fields(array(
                'seriesid' => $seriesid,
                'productid' => $form['add']['qatracker_milestone_series_manifest_productid']['#value'],
                'contact' => $form['add']['qatracker_milestone_series_manifest_contact']['#value'],
            ));
            $query->execute();

            watchdog("qatracker",
                t("Added manifest entry for '@product' in '@series'"),
                array('@product' => $form['add']['qatracker_milestone_series_manifest_productid']['#value'],
                        '@series' => $seriesid)
            );
        break;

        case t('Enable'):
            if (count($selection) == 0) {
                return $form;
            }

            $query = db_update('qatracker_milestone_series_manifest');
            $query->fields(array('status' => 0));
            $query->condition('qatracker_milestone_series_manifest.productid', $selection, 'IN');
            $query->condition('qatracker_milestone_series_manifest.seriesid', $seriesid);
            $query->execute();

            foreach ($selection as $entry) {
                watchdog("qatracker",
                    t("Enabled manifest entry for '@product' in '@series'"),
                    array('@product' => $entry, '@series' => $seriesid)
                );
            }
        break;

        case t('Disable'):
            if (count($selection) == 0) {
                return $form;
            }

            $query = db_update('qatracker_milestone_series_manifest');
            $query->fields(array('status' => 1));
            $query->condition('qatracker_milestone_series_manifest.productid', $selection, 'IN');
            $query->condition('qatracker_milestone_series_manifest.seriesid', $seriesid);
            $query->execute();

            foreach ($selection as $entry) {
                watchdog("qatracker",
                    t("Disabled manifest entry for '@product' in '@series'"),
                    array('@product' => $entry, '@series' => $seriesid)
                );
            }
        break;

        case t('Remove'):
            if (count($selection) == 0) {
                return $form;
            }

            $query = db_delete('qatracker_milestone_series_manifest');
            $query->condition('qatracker_milestone_series_manifest.productid', $selection, 'IN');
            $query->condition('qatracker_milestone_series_manifest.seriesid', $seriesid);
            $query->execute();

            foreach ($selection as $entry) {
                watchdog("qatracker",
                    t("Removed manifest entry for '@product' in '@series'"),
                    array('@product' => $entry, '@series' => $seriesid)
                );
            }
        break;

        default:
            drupal_not_found();
            exit;
        break;
    }

    return $form;
}
?>
