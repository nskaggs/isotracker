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

function qatracker_admin() {
    $site = qatracker_get_current_site();

    # Standard header
    $form = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $form;
    }

    if (!qatracker_acl('administer site configuration', array('admin'), $site)) {
        # Only a testcase administrator, don't show any option here
        $form[] =array(
            '#type' => 'markup',
            '#prefix' => "<p>",
            '#markup' => t("Please select one of the administration tabs."),
            '#suffix' => "</p>",
        );
        return $form;
    }

    $form['frontpage'] = array(
        '#type' => 'fieldset',
        '#title' => t('Front page'),
        '#collapsible' => FALSE,
        '#collapsed' => FALSE,
    );

    $form['frontpage']['noticeboard'] = array(
        '#type' => 'textarea',
        '#title' => t('Notice board'),
        '#default_value' => array_key_exists("noticeboard", $site->options) ? $site->options['noticeboard'] : "",
        '#description' => t("HTML notice shown on the front page"),
        '#required' => FALSE,
    );

    $form['rebuilds'] = array(
        '#type' => 'fieldset',
        '#title' => t('Rebuilds'),
        '#collapsible' => FALSE,
        '#collapsed' => FALSE,
    );

    $form['rebuilds']['rebuilds_allowed'] = array(
        '#type' => 'checkbox',
        '#title' => t('Allow rebuilds'),
        '#default_value' => array_key_exists("rebuilds_allowed", $site->options) ? $site->options['rebuilds_allowed'] : "",
        '#description' => t("Make it possible for admins and product owners to request rebuilds"),
        '#required' => FALSE,
    );

    $form['rebuilds']['rebuilds_rate_limit'] = array(
        '#type' => 'textfield',
        '#title' => t('Daily limit'),
        '#default_value' => array_key_exists("rebuilds_rate_limit", $site->options) ? $site->options['rebuilds_rate_limit'] : "0",
        '#description' => t("Number of rebuilds a product owner (non-admin) can request per day. (0 for unlimited)"),
        '#required' => FALSE,
    );

    $form['testcases'] = array(
        '#type' => 'fieldset',
        '#title' => t('Testcases'),
        '#collapsible' => FALSE,
        '#collapsed' => FALSE,
    );

    $form['testcases']['testcase_string_id'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use string as testcase ID'),
        '#default_value' => array_key_exists("testcase_string_id", $site->options) ? $site->options['testcase_string_id'] : "",
        '#description' => t("Use a string as the testcase ID instead of the database row number"),
        '#required' => FALSE,
    );

    $form['testcases']['testcase_template'] = array(
        '#type' => 'textarea',
        '#title' => t('Default template for new testcases'),
        '#default_value' => array_key_exists("testcase_template", $site->options) ? $site->options['testcase_template'] : "",
        '#description' => t("This text will be used as a template for any new testcase"),
        '#required' => FALSE,
    );

    $form['testcases']['testcase_bug_url'] = array(
        '#type' => 'textfield',
        '#title' => t('URL for testcase bug reporting'),
        '#default_value' => array_key_exists("testcase_bug_url", $site->options) ? $site->options['testcase_bug_url'] : "",
        '#description' => t("URL of the bug tracker to use for reporting bugs in testcase content"),
        '#required' => FALSE,
    );

    $form['bugs'] = array(
        '#type' => 'fieldset',
        '#title' => t('Bugs'),
        '#collapsible' => FALSE,
        '#collapsed' => FALSE,
    );

    $form['bugs']['bug_tag'] = array(
        '#type' => 'textfield',
        '#title' => t('Bug tag'),
        '#default_value' => array_key_exists("bug_tag", $site->options) ? $site->options['bug_tag'] : "",
        '#description' => t("Tag used by the Launchpad integration script"),
        '#required' => FALSE,
    );

    $form['bugs']['bug_comment'] = array(
        '#type' => 'textarea',
        '#title' => t('Bug comment'),
        '#default_value' => array_key_exists("bug_comment", $site->options) ? $site->options['bug_comment'] : "",
        '#description' => t("Comment that will be posted to the bug report (text only).<br />@results will be replaced by the URL to the list of results for the bug."),
        '#required' => FALSE,
    );

    $form[] = array(
        '#type' => 'submit',
        '#value' => t('Save changes'),
    );

    return $form;
}

function qatracker_admin_submit($form, &$form_state) {
    $site = qatracker_get_current_site();
    $options = array();
    $options['noticeboard'] = $form['frontpage']['noticeboard']['#value'];
    $options['testcase_string_id'] = $form['testcases']['testcase_string_id']['#value'];
    $options['testcase_template'] = $form['testcases']['testcase_template']['#value'];
    $options['testcase_bug_url'] = $form['testcases']['testcase_bug_url']['#value'];
    $options['bug_tag'] = $form['bugs']['bug_tag']['#value'];
    $options['bug_comment'] = $form['bugs']['bug_comment']['#value'];
    $options['rebuilds_allowed'] = $form['rebuilds']['rebuilds_allowed']['#value'];
    $options['rebuilds_rate_limit'] = $form['rebuilds']['rebuilds_rate_limit']['#value'];

    foreach ($options as $key => $value) {
        if (array_key_exists($key, $site->options)) {
            $query = db_update('qatracker_site_setting');
            $query->condition('qatracker_site_setting.siteid', $site->id);
            $query->condition('qatracker_site_setting.option', $key);
        }
        else {
            $query = db_insert('qatracker_site_setting');
        }

        $query->fields(array(
            'siteid' => $site->id,
            'option' => $key,
            'value' => $value,
        ));
        $result = $query->execute();

    }
    watchdog("qatracker",
        t("Updated noticeboard, bug tag and bug comment for site ID: @siteid"),
        array('@siteid' => $site->id)
    );

    return $form;
}
?>
