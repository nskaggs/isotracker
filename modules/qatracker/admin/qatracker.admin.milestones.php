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

function qatracker_admin_milestones() {
    global $qatracker_milestone_status, $qatracker_milestone_notify, $qatracker_milestone_autofill;

    drupal_set_title(t("Filters management"));
    $site = qatracker_get_current_site();

    # Standard header
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Getting all the entries
    $query = db_select('qatracker_milestone');
    $query->fields('qatracker_milestone', array('id', 'title', 'notify', 'autofill', 'status'));
    $query->addField('qatracker_milestone_series', 'title', 'series');
    $query->leftJoin('qatracker_milestone_series', 'qatracker_milestone_series', 'qatracker_milestone_series.id = qatracker_milestone.seriesid');
    $query->orderBy('qatracker_milestone.status', 'ASC');
    $query->orderBy('qatracker_milestone.id', 'DESC');
    $query->condition('qatracker_milestone.siteid', $site->id);
    $result = $query->execute();

    # And generating the table
    $rows = array();
    foreach ($result as $record) {
        $rows[] = array(
            $record->title,
            $record->series,
            $qatracker_milestone_status[$record->status],
            $qatracker_milestone_notify[$record->notify],
            $qatracker_milestone_autofill[$record->autofill],
            l(
                t("Edit"),
                "admin/config/services/qatracker/milestones/".$record->id."/edit",
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
        '#title' => t('Add a milestone'),
        '#suffix' => '</li></ul>',
        '#href' => 'admin/config/services/qatracker/milestones/add',
    );

    $items[] = array(
        '#theme' => 'table',
        '#header' => array(
            t('Title'),
            t('Series'),
            t('Status'),
            t('Notifications'),
            t('Based on manifest'),
            t('Actions')
        ),
        '#rows' => $rows,
    );

    return $items;
}

function qatracker_admin_milestones_edit($form, &$form_state) {
    global $qatracker_milestone_status;

    drupal_set_title(t("Edit a milestone"));
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_select('qatracker_milestone');
        $query->condition('qatracker_milestone.id', $args[5]);
        $query->fields('qatracker_milestone', array('id', 'title', 'status', 'notify', 'autofill', 'seriesid'));
        $result = $query->execute();
        $entry = $result->fetch();
    }
    else {
        $entry = new stdClass();
        $entry->title = "";
        $entry->status = 0;
        $entry->notify = 1;
        $entry->autofill = 1;
        $entry->seriesid = NULL;
    }

    $series_query = db_select('qatracker_milestone_series');
    $series_query->condition('qatracker_milestone_series.siteid', qatracker_get_current_site()->id);
    $series_query->orderBy('qatracker_milestone_series.title', 'ASC');
    $series_query->fields('qatracker_milestone_series', array('id', 'title'));
    $series_result = $series_query->execute();

    $qatracker_milestone_series = array();
    foreach ($series_result as $series_record) {
        $qatracker_milestone_series[$series_record->id] = $series_record->title;
    }

    $form = array();
    $form['qatracker_milestone'] = array(
        '#type' => 'fieldset',
        '#title' => t('Filter'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
    );
    if ($action == "edit" && count($args) == 7) {
        $form['qatracker_milestone']['qatracker_milestone_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ID'),
            '#default_value' => $entry->id,
            '#description' => t("ID of the filter"),
            '#required' => TRUE,
            '#disabled' => TRUE,
        );
    }

    $form['qatracker_milestone']['qatracker_milestone_title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#default_value' => $entry->title,
        '#description' => t("Title of the milestone"),
        '#required' => TRUE,
    );

    $form['qatracker_milestone']['qatracker_milestone_seriesid'] = array(
        '#type' => 'select',
        '#title' => t('Series'),
        '#options' => $qatracker_milestone_series,
        '#default_value' => $entry->seriesid,
        '#description' => t("What series this milestone belongs to"),
        '#required' => TRUE,
    );

    $form['qatracker_milestone']['qatracker_milestone_notify'] = array(
        '#type' => 'checkbox',
        '#title' => t('E-mail notifications'),
        '#default_value' => $entry->notify,
        '#description' => t("Whether or not to send notifications by e-mail to subscribers"),
    );

    $form['qatracker_milestone']['qatracker_milestone_autofill'] = array(
        '#type' => 'checkbox',
        '#title' => t('Automatically publish builds listed in the series manifest'),
        '#default_value' => $entry->autofill,
        '#description' => t("If selected, any product listed in the milestone manifest will automatically be added to this milestone while the milestone is marked as 'testing'."),
    );

    $form['qatracker_milestone']['qatracker_milestone_status'] = array(
        '#type' => 'select',
        '#title' => t('Status'),
        '#options' => $qatracker_milestone_status,
        '#default_value' => $entry->status,
        '#description' => t("Milestone status"),
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
        '#markup' => l(t('Cancel'), 'admin/config/services/qatracker/milestones'),
    );

    return $form;
}

function qatracker_admin_milestones_edit_submit($form, &$form_state) {
    $args = arg();
    $index = count($args)-1;
    $action = $args[$index];

    if ($action == "edit" && count($args) == 7) {
        $query = db_update('qatracker_milestone');
        $query->condition('qatracker_milestone.id', $args[5]);
    }
    else {
        $query = db_insert('qatracker_milestone');
    }

    $query->fields(array(
        'siteid' => qatracker_get_current_site()->id,
        'seriesid' => $form['qatracker_milestone']['qatracker_milestone_seriesid']['#value'],
        'title' => $form['qatracker_milestone']['qatracker_milestone_title']['#value'],
        'notify' => $form['qatracker_milestone']['qatracker_milestone_notify']['#value'],
        'autofill' => $form['qatracker_milestone']['qatracker_milestone_autofill']['#value'],
        'status' => $form['qatracker_milestone']['qatracker_milestone_status']['#value'],
    ));
    $milestoneid = $query->execute();

    if ($action == "edit") {
        watchdog("qatracker",
            t("Updated milestone with ID: @milestoneid"),
            array('@milestoneid' => $args[5])
        );
    }
    else {
        watchdog("qatracker",
            t("Added milestone with ID: @milestoneid"),
            array('@milestoneid' => $milestoneid)
        );
    }

    $form_state['redirect'] = 'admin/config/services/qatracker/milestones';
    return $form;
}

?>
