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

function qatracker_admin_builds() {
    global $user;

    drupal_set_title(t("Add builds"));
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    $list_rids = array();
    if (!qatracker_acl("administer site configuration", array("admin"), $site)) {
        # Build a list of rids for the current user
        foreach ($user->roles as $rid => $role) {
            $list_rids[] = $rid;
        }
    }

    # Getting all the entries
    $query = db_select('qatracker_product');
    $query->fields('qatracker_product', array('id', 'title', 'status'));
    $query->addField('qatracker_product_family', 'title', 'family');
    $query->leftjoin('qatracker_product_family', 'qatracker_product_family', 'qatracker_product.familyid = qatracker_product_family.id');
    $query->orderBy('qatracker_product_family.title', 'ASC');
    $query->orderBy('qatracker_product.title', 'ASC');
    $query->condition('qatracker_product.siteid', $site->id);
    $query->condition('qatracker_product.status', 0);
    if ($list_rids) {
        $query->condition('qatracker_product.ownerrole', $list_rids, 'IN');
    }
    $result = $query->execute();

    # And generating the table
    $rows = array();
    $family = Null;

    function new_table($rows) {
        return array(
            '#type' => 'tableselect',
            '#header' => array(
                array('data' => t('Title')),
                array('data' => t('Version'), 'style' => 'width:20em'),
            ),
            '#options' => $rows,
            '#empty' => t('No build available')
        );
    }

    foreach ($result as $record) {
        if ($family && $family != $record->family) {
            $items[] = new_table($rows);
            $rows = array();
        }

        # Get the last version number for that product in any active milestone (possibly more than one result)
        $build_query = db_select('qatracker_build');
        $build_query->fields('qatracker_build', array('id', 'version'));
        $build_query->fields('qatracker_milestone', array('title'));
        $build_query->fields('qatracker_build_milestone', array('milestoneid'));
        $build_query->join('qatracker_build_milestone', 'qatracker_build_milestone', 'qatracker_build.id = qatracker_build_milestone.buildid');
        $build_query->join('qatracker_milestone', 'qatracker_milestone', 'qatracker_build_milestone.milestoneid = qatracker_milestone.id');
        $build_query->condition('qatracker_build.productid', $record->id);
        $build_query->condition('qatracker_milestone.status', '0');
        $build_query->condition('qatracker_build_milestone.status', array(0, 1, 4), 'IN');
        $build_result = $build_query->execute();

        $versions = array();
        foreach ($build_result as $build_record) {
            if ($build_record->version && $build_record->title) {
                $versions[] = $build_record->title.": ".l($build_record->version, "admin/config/services/qatracker/builds/".$build_record->id."/".$build_record->milestoneid."/edit");
            }
        }

        $rows[$record->id] = array(
            $record->title,
            implode("<br />", $versions),
        );
        $family = $record->family;
    }
    $items[] = new_table($rows);

    # Get the list of valid milestones
    $milestone_query = db_select('qatracker_milestone');
    $milestone_query->condition('qatracker_milestone.siteid', $site->id);
    $milestone_query->condition('qatracker_milestone.status', 0);
    $milestone_query->orderBy('qatracker_milestone.id', 'ASC');
    $milestone_query->fields('qatracker_milestone', array('id', 'title'));
    $milestone_result = $milestone_query->execute();

    $qatracker_milestones = array();
    foreach ($milestone_result as $milestone_record) {
        $qatracker_milestones[$milestone_record->id] = $milestone_record->title;
    }

    $items['qatracker_build_version'] = array(
        '#type' => 'textfield',
        '#title' => 'Version number',
        '#description' => 'Version string for the added builds',
        '#required' => TRUE,
    );

    $items['qatracker_build_note'] = array(
        '#type' => 'textarea',
        '#title' => 'Notes',
        '#description' => 'Notes attached to this build, will be sent to subscribers',
        '#required' => FALSE,
    );

    $items['qatracker_build_milestone'] = array(
        '#type' => 'select',
        '#title' => 'Milestone',
        '#options' => $qatracker_milestones,
        '#description' => 'An active milestone for this site',
        '#required' => TRUE,
    );

    $items[] = array(
        '#type' => 'submit',
        '#value' => t('Add these builds to the tracker'),
    );

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => 'Using an already existing version number will simply make it the current one, not create it again.<br />
Use that if you need to revert to an older build or made a mistake.',
        '#suffix' => '</p>',
    );

    return $items;
}

function qatracker_admin_builds_submit($form, &$form_state) {
    global $user;
    $products = array();
    $productowner = qatracker_acl("administer site configuration", array("product"));

    # Magic to extract all the selected products
    foreach ($form as $element) {
        if (!is_array($element)) {
            continue;
        }
        foreach ($element as $key => $table) {
            if (!is_array($table)) {
                continue;
            }
            if (array_key_exists("#checked", $table) && $table['#checked'] == "1") {
                if (!$productowner || qatracker_acl_product($key)) {
                    $products[] = $key;
                }
            }
        }
    }

    # Also extract the version and milestoneid
    $version = $form['qatracker_build_version']['#value'];
    $note = $form['qatracker_build_note']['#value'];
    $milestoneid = $form['qatracker_build_milestone']['#value'];

    # And finally post the builds
    qatracker_builds_add($products, $milestoneid, $version, $note, True, $user->uid);

    $form_state['redirect'] = 'admin/config/services/qatracker/builds';
    return $form;
}

function qatracker_admin_builds_edit($form, &$form_state) {
    drupal_set_title(t("Edit a build"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 8) {
        $query = db_select('qatracker_build_milestone');
        $query->leftjoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_build_milestone.buildid');
        $query->fields('qatracker_build_milestone', array('note', 'milestoneid'));
        $query->fields('qatracker_build', array('id', 'productid'));
        $query->condition('qatracker_build_milestone.buildid', $args[5]);
        $query->condition('qatracker_build_milestone.milestoneid', $args[6]);
        $result = $query->execute();
        $entry = $result->fetch();

        if (!qatracker_acl_product($entry->productid)) {
            drupal_access_denied();
            exit;
        }
    }
    else {
        drupal_not_found();
        exit;
    }

    $form = array();

    $form['qatracker_build'] = array(
        '#type' => 'fieldset',
        '#title' => t('Build'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
    );

    $form['qatracker_build']['qatracker_build_id'] = array(
        '#type' => 'textfield',
        '#title' => t('ID'),
        '#default_value' => $entry->id,
        '#description' => t("ID of the build"),
        '#required' => TRUE,
        '#disabled' => TRUE,
    );

    $form['qatracker_build']['qatracker_build_milestoneid'] = array(
        '#type' => 'textfield',
        '#title' => t('Milestone ID'),
        '#default_value' => $entry->milestoneid,
        '#description' => t("Milestone ID of the build"),
        '#required' => TRUE,
        '#disabled' => TRUE,
    );

    $form['qatracker_build']['qatracker_build_note'] = array(
        '#type' => 'textarea',
        '#title' => t('Note'),
        '#default_value' => $entry->note,
        '#description' => t("Note this build"),
        '#required' => FALSE,
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save'),
    );

    $form['actions']['cancel'] = array(
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/builds'),
    );

    return $form;
}

function qatracker_admin_builds_edit_submit($form, &$form_state) {
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 8) {
        if (!qatracker_acl("administer site configuration", array("admin"))) {
            $query = db_select('qatracker_build');
            $query->condition('qatracker_build.id', $args[5]);
            $query->fields('qatracker_build', array('productid'));
            $productid = $query->execute()->fetchField();

            if (!qatracker_acl_product($productid)) {
                drupal_access_denied();
                exit;
            }
        }

        $query = db_update('qatracker_build_milestone');
        $query->condition('qatracker_build_milestone.buildid', $args[5]);
        $query->condition('qatracker_build_milestone.milestoneid', $args[6]);
    }
    else {
        drupal_not_found();
        exit;
    }

    $query->fields(array(
        'note' => $form['qatracker_build']['qatracker_build_note']['#value'],
    ));
    $result = $query->execute();
    watchdog("qatracker", t("Updated build note for build ID: @buildid"), array('@buildid' => $args[5]));

    $form_state['redirect'] = 'admin/config/services/qatracker/builds';
    return $form;
}

?>
