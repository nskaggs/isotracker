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

function qatracker_admin_families() {
    drupal_set_title(t("Product families management"));
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Getting all the entries
    $query = db_select('qatracker_product_family');
    $query->fields('qatracker_product_family', array('id', 'title', 'weight'));
    $query->leftjoin('qatracker_product', 'qatracker_product', 'qatracker_product_family.id = qatracker_product.familyid');
    $query->addExpression('COUNT(qatracker_product.id)', 'product_count');
    $query->groupBy('qatracker_product_family.id, qatracker_product_family.title, qatracker_product_family.weight');
    $query->orderBy('qatracker_product_family.weight', 'ASC');
    $query->orderBy('qatracker_product_family.title', 'ASC');
    $query->condition('qatracker_product_family.siteid', $site->id);
    $result = $query->execute();

    # And generating the table
    $rows = array();
    foreach ($result as $record) {
        $rows[] = array(
            $record->title,
            $record->product_count,
            l(
                t("Edit"),
                "admin/config/services/qatracker/families/".$record->id."/edit",
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
        '#title' => t('Add product family'),
        '#suffix' => '</li></ul>',
        '#href' => 'admin/config/services/qatracker/families/add',
    );

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('Name'),
            t('Products'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    return $items;
}

function qatracker_admin_families_edit($form, &$form_state) {
    drupal_set_title(t("Edit a product family"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_select('qatracker_product_family');
        $query->condition('qatracker_product_family.id', $args[5]);
        $query->fields('qatracker_product_family', array('id', 'title', 'weight'));
        $result = $query->execute();
        $entry = $result->fetch();
    }
    else {
        $entry = new stdClass();
        $entry->title = "";
        $entry->weight = 0;
    }

    $form = array();

    $form['qatracker_product_family'] = array(
        '#type' => 'fieldset',
        '#title' => t('Product family'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
    );
    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_product_family']['qatracker_product_family_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->id,
            '#description' => t("ID of the family"),
            '#required' => TRUE,
            '#disabled' => TRUE,
        );
    }

    $form['qatracker_product_family']['qatracker_product_family_title'] = array(
        '#type' => 'textfield',
        '#title' => t('Name'),
        '#default_value' => $entry->title,
        '#description' => t("Name of the product family"),
        '#required' => TRUE,
    );

    $form['qatracker_product_family']['qatracker_product_family_weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight'),
        '#default_value' => $entry->weight,
        '#description' => t("Weight for ordering"),
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
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/families'),
    );

    return $form;
}

function qatracker_admin_families_edit_submit($form, &$form_state) {
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_update('qatracker_product_family');
        $query->condition('qatracker_product_family.id', $args[5]);
    }
    else {
        $query = db_insert('qatracker_product_family');
    }
    $query->fields(array(
        'siteid' => qatracker_get_current_site()->id,
        'title' => $form['qatracker_product_family']['qatracker_product_family_title']['#value'],
        'weight' => $form['qatracker_product_family']['qatracker_product_family_weight']['#value'],
    ));
    $familyid = $query->execute();

    if ($action == "edit") {
        watchdog("qatracker",
            t("Updated product family with ID: @familyid"),
            array('@familyid' => $args[5])
        );
    }
    else {
        watchdog("qatracker",
            t("Added product family with ID: @familyid"),
            array('@familyid' => $familyid)
        );
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/families';
    return $form;
}

?>
