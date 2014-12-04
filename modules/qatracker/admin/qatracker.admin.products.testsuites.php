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

function qatracker_admin_product_testsuites() {
    global $qatracker_testsuite_testcase_status;

    drupal_set_title(t("Linked testsuites"));
    $site = qatracker_get_current_site();

    $args = arg();
    $product_id = $args[5];
    if (!qatracker_acl_product($product_id)) {
        drupal_access_denied();
        exit;
    }

    # Getting all the entries
    $query = db_select('qatracker_testsuite_product');
    $query->addField('qatracker_testsuite', 'id', 'id');
    $query->addField('qatracker_testsuite', 'title', 'title');
    $query->addField('qatracker_milestone_series', 'id', 'seriesid');
    $query->addField('qatracker_milestone_series', 'title', 'series');
    $query->leftJoin('qatracker_testsuite', 'qatracker_testsuite', 'qatracker_testsuite.id=qatracker_testsuite_product.testsuiteid');
    $query->leftJoin('qatracker_milestone_series', 'qatracker_milestone_series', 'qatracker_milestone_series.id=qatracker_testsuite_product.milestone_seriesid');
    $query->orderBy('qatracker_milestone_series.title', 'ASC');
    $query->orderBy('qatracker_testsuite.title', 'ASC');
    $query->condition('qatracker_testsuite_product.productid', $product_id);
    $query->condition('qatracker_testsuite.siteid', $site->id);
    $result = $query->execute();

    # And genering the table
    $rows = array();
    foreach ($result as $record) {
        $rows[$record->seriesid."-".$record->id] = array(
            $record->series,
            $record->title,
        );
    }

    $items['remove'] = array(
        '#type' => 'fieldset',
        '#title' => t('Current entries'),
    );

    $items['remove']['entries'] = array(
        '#type' => 'tableselect',
        '#header' => array(
            t('Series'),
            t('Testsuite'),
        ),
        '#options' => $rows,
        '#empty' => t('None')
    );

    # Get the list of valid series
    $series_query = db_select('qatracker_milestone_series');
    $series_query->condition('qatracker_milestone_series.siteid', $site->id);
    $series_query->orderBy('qatracker_milestone_series.title', 'ASC');
    $series_query->fields('qatracker_milestone_series', array('id', 'title'));
    $qatracker_series = $series_query->execute()->fetchAllKeyed(1,0);

    # Get the list of valid testsuites
    $testsuites_query = db_select('qatracker_testsuite');
    $testsuites_query->condition('qatracker_testsuite.siteid', $site->id);
    $testsuites_query->orderBy('qatracker_testsuite.title', 'ASC');
    $testsuites_query->fields('qatracker_testsuite', array('id', 'title'));
    $qatracker_testsuites = $testsuites_query->execute()->fetchAllKeyed(1,0);

    if (count($rows) > 0) {
        $items['remove']['remove'] = array(
            '#type' => 'submit',
            '#value' => t('Remove'),
            '#submit' => array('qatracker_admin_product_testsuites_submit'),
            '#limit_validation_errors' => array(array('add', 'qatracker_testsuite_product_seriesid'), array('add', 'qatracker_testsuite_product_testsuiteid')),
        );
    }

    $items['add'] = array(
        '#type' => 'fieldset',
        '#title' => t('Add an entry'),
    );

    $items['add']['qatracker_testsuite_product_milestone_seriesid'] = array(
        '#type' => 'select',
        '#title' => 'Series',
        '#options' => $qatracker_series,
        '#description' => 'An active series for this site',
        '#required' => TRUE,
    );

    $items['add']['qatracker_testsuite_product_testsuiteid'] = array(
        '#type' => 'select',
        '#title' => 'Testsuite',
        '#options' => $qatracker_testsuites,
        '#description' => 'An active testsuite for this site',
        '#required' => TRUE,
    );

    $items['add']['add'] = array(
        '#type' => 'submit',
        '#submit' => array('qatracker_admin_product_testsuites_submit'),
        '#value' => t('Add'),
    );

    return $items;
}

function qatracker_admin_product_testsuites_submit($form, &$form_state) {
    $site = qatracker_get_current_site();
    $args = arg();
    $product_id = $args[5];
    if (!qatracker_acl_product($product_id)) {
        drupal_access_denied();
        exit;
    }

    # Magic to extract all the selected items
    $selection = array();
    foreach ($form_state['input'] as $element) {
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
        case t('Add'):
            $query = db_insert('qatracker_testsuite_product');
            $query->fields(array(
                'testsuiteid' => $form['add']['qatracker_testsuite_product_testsuiteid']['#value'],
                'productid' => $product_id,
                'milestone_seriesid' => $form['add']['qatracker_testsuite_product_milestone_seriesid']['#value'],
            ));
            $query->execute();

            watchdog("qatracker",
                t("Added testsuite/product/series mapping with IDs: @testsuite/@product/@series"),
                array('@testsuite' => $form['add']['qatracker_testsuite_product_testsuiteid']['#value'],
                        '@product' => $product_id,
                        '@series' => $form['add']['qatracker_testsuite_product_milestone_seriesid']['#value'])
            );
        break;

        case t('Remove'):
            if (count($selection) == 0) {
                return $form;
            }
            foreach ($selection as $entry) {
                $fields = explode("-", $entry);
                if (count($fields) != 2) {
                    continue;
                }

                $query = db_delete('qatracker_testsuite_product');
                $query->condition('qatracker_testsuite_product.productid', $product_id);
                $query->condition('qatracker_testsuite_product.milestone_seriesid', $fields[0]);
                $query->condition('qatracker_testsuite_product.testsuiteid', $fields[1]);
                $query->execute();

                watchdog("qatracker",
                    t("Removed testsuite/product/series mapping with IDs: @testsuite/@product/@series"),
                    array('@testsuite' => $fields[1], '@product' => $product_id, '@series' => $fields[0])
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
