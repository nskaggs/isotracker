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

function qatracker_admin_downloads() {
    global $qatracker_product_download_type;

    drupal_set_title(t("Download links management"));
    $site = qatracker_get_current_site();

    $args = arg();
    $product_id = $args[5];
    if (!qatracker_acl_product($product_id)) {
        drupal_access_denied();
        exit;
    }

    # Getting all the entries
    $query = db_select('qatracker_product_download');
    $query->fields('qatracker_product_download', array('id', 'filename', 'path', 'type'));
    $query->addField('qatracker_milestone_series', 'title', 'series');
    $query->leftJoin('qatracker_milestone_series', 'qatracker_milestone_series', 'qatracker_milestone_series.id = qatracker_product_download.seriesid');
    $query->orderBy('series', 'ASC');
    $query->orderBy('qatracker_product_download.filename', 'ASC');
    $query->orderBy('qatracker_product_download.type', 'ASC');
    $query->orderBy('qatracker_product_download.path', 'ASC');
    $query->condition('qatracker_product_download.productid', $product_id);
    $result = $query->execute();

    # And genering the table
    $rows = array();
    foreach ($result as $record) {
        $rows[] = array(
            $record->series,
            $record->filename,
            $qatracker_product_download_type[$record->type],
            l($record->path, $record->path),
            l(
                t("Edit"),
                "admin/config/services/qatracker/products/".$product_id."/downloads/".$record->id."/edit",
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
        '#title' => t('Add a download link'),
        '#suffix' => '</li></ul>',
        '#href' => 'admin/config/services/qatracker/products/'.$product_id.'/downloads/add',
    );

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('Series'),
            t('Filename'),
            t('Type'),
            t('Path'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    return $items;
}

function qatracker_admin_downloads_edit($form, &$form_state) {
    global $qatracker_product_download_type;

    drupal_set_title(t("Edit a download link"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];
    $product_id = $args[5];
    if (!qatracker_acl_product($product_id)) {
        drupal_access_denied();
        exit;
    }

    if ($action == "edit" && count($args) == 9) {
        $query = db_select('qatracker_product_download');
        $query->condition('qatracker_product_download.id', $args[7]);
        $query->fields('qatracker_product_download', array('id', 'seriesid', 'filename', 'type', 'path'));
        $result = $query->execute();
        $entry = $result->fetch();
    }
    else {
        $entry = new stdClass();
        $entry->seriesid = 0;
        $entry->filename = "";
        $entry->type = 0;
        $entry->path = "";
    }

    $series_query = db_select('qatracker_milestone_series');
    $series_query->condition('qatracker_milestone_series.siteid', qatracker_get_current_site()->id);
    $series_query->orderBy('qatracker_milestone_series.title', 'ASC');
    $series_query->fields('qatracker_milestone_series', array('id', 'title'));
    $series_result = $series_query->execute();

    $qatracker_milestone_series = array();
    $qatracker_milestone_series[0] = t('None (fallback when no series specific entry)');
    foreach ($series_result as $series_record) {
        $qatracker_milestone_series[$series_record->id] = $series_record->title;
    }

    $form = array();
    $form['qatracker_product_download'] = array(
        '#type' => 'fieldset',
        '#title' => t('Download link'),
    );
    if ($action == "edit" && count($args) == 9) {
        $form['qatracker_product_download']['qatracker_product_download_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->id,
            '#description' => t("ID of the download link"),
            '#required' => TRUE,
            '#disabled' => TRUE,
        );
    }

    $form['qatracker_product_download']['qatracker_product_download_seriesid'] = array(
        '#type' => 'select',
        '#title' => t('Series'),
        '#options' => $qatracker_milestone_series,
        '#default_value' => $entry->seriesid ? $entry->seriesid : 0,
        '#description' => t("What series this download link belongs to"),
        '#required' => TRUE,
    );

    $form['qatracker_product_download']['qatracker_product_download_filename'] = array(
        '#type' => 'textfield',
        '#title' => t('Filename'),
        '#default_value' => $entry->filename,
        '#description' => t("Filename for that link.<br />VERSION is a placeholder replaced by the build version.<br />SERIES is a placeholder replaced by the lowercase value of the milestone series."),
        '#required' => TRUE,
    );

    $form['qatracker_product_download']['qatracker_product_download_type'] = array(
        '#type' => 'select',
        '#title' => t('Type'),
        '#options' => $qatracker_product_download_type,
        '#default_value' => $entry->type,
        '#description' => t("Type of link"),
        '#required' => TRUE,
    );

    $form['qatracker_product_download']['qatracker_product_download_path'] = array(
        '#type' => 'textfield',
        '#title' => t('Path'),
        '#default_value' => $entry->path,
        '#description' => t("Full path to the target.<br />VERSION is a placeholder replaced by the build version.<br />SERIES is a placeholder replaced by the lowercase value of the milestone series."),
        '#maxlength' => 1000,
        '#required' => TRUE,
    );

    $form['actions'] = array('#type' => 'actions');
    if ($action == "edit" && count($args) == 9) {
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Save'),
        );
        $form['actions']['delete'] = array(
            '#type' => 'submit',
            '#value' => t('Delete'),
        );
    }
    else {
        $form['actions']['create'] = array(
            '#type' => 'submit',
            '#value' => t('Create'),
        );
    }

    $form['actions']['cancel'] = array(
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/products/'.$product_id.'/downloads'),
    );

    return $form;
}

function qatracker_admin_downloads_edit_submit($form, &$form_state) {
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];
    $product_id = $args[5];
    if (!qatracker_acl_product($product_id)) {
        drupal_access_denied();
        exit;
    }

    # FIXME: Would be nice avoiding matching translated strings...
    switch ($form_state['values']['op']) {
        case t('Create'):
        case t('Save'):
            if ($action == "edit" && count($args) == 9) {
                $query = db_update('qatracker_product_download');
                $query->condition('qatracker_product_download.id', $args[7]);
            }
            else {
                $query = db_insert('qatracker_product_download');
            }

            if ($form['qatracker_product_download']['qatracker_product_download_seriesid']['#value'] == 0) {
                $form['qatracker_product_download']['qatracker_product_download_seriesid']['#value'] = Null;
            }

            $query->fields(array(
                'productid' => $product_id,
                'seriesid' => $form['qatracker_product_download']['qatracker_product_download_seriesid']['#value'],
                'filename' => $form['qatracker_product_download']['qatracker_product_download_filename']['#value'],
                'type' => $form['qatracker_product_download']['qatracker_product_download_type']['#value'],
                'path' => $form['qatracker_product_download']['qatracker_product_download_path']['#value'],
            ));

            $linkid = $query->execute();

            if ($action == "edit") {
                watchdog("qatracker",
                    t("Updated download link with ID: @linkid"),
                    array('@linkid' => $args[7])
                );
            }
            else {
                watchdog("qatracker",
                    t("Added download link with ID: @linkid to product ID: @productid"),
                    array('@linkid' => $linkid, '@productid' => $product_id)
                );
            }
        break;

        case t('Delete'):
            $query = db_delete('qatracker_product_download');
            $query->condition('qatracker_product_download.id', $args[7]);
            $query->execute();
            watchdog("qatracker",
                t("Removed download link with ID: @linkid from product with ID: @productid"),
                array('@linkid' => $args[7], '@productid' => $product_id)
            );
        break;
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/products/'.$product_id.'/downloads';
    return $form;
}

?>
