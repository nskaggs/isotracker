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

function qatracker_menu() {
    $items = array();

    # User pages
    $items['qatracker'] = array(
        'title' => t("Testing tracker"),
        'description' => t("Testing tracker"),
        'file' => 'user/qatracker.user.milestones.php',
        'page callback' => 'drupal_get_form',
        'page arguments' => array('qatracker_user_milestones'),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -10);

    $items['qatracker/milestones/%/history'] = array(
        'title' => t("Build history"),
        'file' => 'user/qatracker.user.builds.php',
        'page arguments' => array('qatracker_user_builds'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/milestones/%/builds'] = array(
        'title' => t("Builds"),
        'file' => 'user/qatracker.user.builds.php',
        'page arguments' => array('qatracker_user_builds'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/milestones/%/builds/%/buginstructions'] = array(
        'title' => t("Bug reporting instructions"),
        'file' => 'user/qatracker.user.products.buginstructions.php',
        'page arguments' => array('qatracker_user_products_buginstructions'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/milestones/%/builds/%/downloads'] = array(
        'title' => t("Downloads"),
        'file' => 'user/qatracker.user.products.downloads.php',
        'page arguments' => array('qatracker_user_products_downloads'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/milestones/%/builds/%/testcases'] = array(
        'title' => t("Testcases"),
        'file' => 'user/qatracker.user.testcases.php',
        'page arguments' => array('qatracker_user_testcases'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/milestones/%/builds/%/testcases/%/results'] = array(
        'title' => t("Results"),
        'file' => 'user/qatracker.user.results.php',
        'page arguments' => array('qatracker_user_results'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    # NOTE: Because of MENU_MAX_PARTS being 9, we can't bind /edit although
    # we use it in the URLs for consistency.
    # This will only be a problem when we need something else than /edit
    $items['qatracker/milestones/%/builds/%/testcases/%/results/%'] = array(
        'title' => t("Edit result"),
        'file' => 'user/qatracker.user.results.php',
        'page arguments' => array('qatracker_user_results_edit', 'edit'),
        'access callback' => 'qatracker_acl',
        'access arguments' => array('administer site configuration', array('user')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/subscription'] = array(
        'title' => t("Subscriptions"),
        'file' => 'user/qatracker.user.subscriptions.php',
        'description' => t("Subscriptions to testcases"),
        'page arguments' => array("qatracker_user_subscriptions"),
        'access callback' => 'qatracker_acl',
        'access arguments' => array('administer site configuration', array('user')),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -9);

    $items['qatracker/api'] = array(
        'title' => t("API"),
        'description' => t("API"),
        'file' => 'user/qatracker.user.api.php',
        'page arguments' => array('qatracker_user_api'),
        'access callback' => 'qatracker_acl',
        'access arguments' => array('administer site configuration', array('user')),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -8);

    $items['qatracker/series/%/manifest'] = array(
        'title' => t("Product manifest"),
        'file' => 'user/qatracker.user.series.php',
        'page arguments' => array('qatracker_user_series_manifest'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/series/%/testsuites'] = array(
        'title' => t("Product manifest"),
        'file' => 'user/qatracker.user.series.php',
        'page arguments' => array('qatracker_user_series_testsuites'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/testcases/%/info'] = array(
        'title' => t("Testcase details"),
        'file' => 'user/qatracker.user.testcases.php',
        'page arguments' => array('qatracker_user_testcases_info'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['qatracker/testcases/%/revisions/%/info'] = array(
        'title' => t("Testcase revision"),
        'file' => 'user/qatracker.user.testcases.php',
        'page arguments' => array('qatracker_user_testcases_info'),
        'access arguments' => array("access content"),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    # Reports
    $items['qatracker/reports'] = array(
        'title' => t("Reports"),
        'description' => t("Reports"),
        'file' => 'report/qatracker.report.summary.php',
        'page arguments' => array("qatracker_report"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -10);

    $items['qatracker/reports/defects'] = array(
        'title' => t("Defects Summary"),
        'description' => t("Defects Summary"),
        'file' => 'report/qatracker.report.defects.php',
        'page arguments' => array("qatracker_report_defects"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -10);

    $items['qatracker/reports/defects/opened'] = array(
        'title' => t("Open Defects"),
        'description' => t("Testing report"),
        'file' => 'report/qatracker.report.defects.php',
        'page arguments' => array("qatracker_report_defects"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -10);

    $items['qatracker/reports/defects/closed'] = array(
        'title' => t("Closed Defects"),
        'description' => t("Testing report"),
        'file' => 'report/qatracker.report.defects.php',
        'page arguments' => array("qatracker_report_defects"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -9);

    $items['qatracker/reports/subscriptions'] = array(
        'title' => t("Testcase subscriptions"),
        'description' => t("Testcase subscriptions"),
        'file' => 'report/qatracker.report.subscriptions.php',
        'page arguments' => array("qatracker_report_subscriptions"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -9);

    $items['qatracker/reports/subscriptions/%/%'] = array(
        'title' => t("Testcase subscriptions"),
        'description' => t("Testcase subscriptions"),
        'file' => 'report/qatracker.report.subscriptions.php',
        'page arguments' => array("qatracker_report_subscriptions_testcase"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -9);

    $items['qatracker/reports/testers'] = array(
        'title' => t("Top testers (current milestones)"),
        'description' => t("Top testers (current milestones)"),
        'file' => 'report/qatracker.report.testers.php',
        'page arguments' => array("qatracker_report_testers"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -8);

    $items['qatracker/reports/testers/top20'] = array(
        'title' => t("Top 20 (of all time)"),
        'description' => t("Top 20 (of all time)"),
        'file' => 'report/qatracker.report.testers.php',
        'page arguments' => array("qatracker_report_testers"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -8);

    $items['qatracker/reports/bugs'] = array(
        'title' => t("Search by bug number"),
        'description' => t("Search for results for a given bugnumber"),
        'file' => 'report/qatracker.report.bugs.php',
        'page arguments' => array("qatracker_report_bugs"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -7);

    $items['qatracker/reports/bugs/%'] = array(
        'title' => t("List of results for bugnumber"),
        'description' => t("List of results for a given bugnumber"),
        'file' => 'report/qatracker.report.bugs.php',
        'page arguments' => array("qatracker_report_bugs_bugnumber"),
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -8);

    # Link to the admin interface from the user interface
    $items['qatracker/admin'] = array(
        'title' => t('Administration'),
        'description' => t('Settings for the testing tracker module.'),
        'type' => MENU_NORMAL_ITEM,
        'page callback' => 'drupal_goto',
        'page arguments' => array('admin/config/services/qatracker'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product', 'testcase')),
    );

    # Admin pages
    $items['admin/config/services/qatracker'] = array(
        'title' => t('Testing tracker configuration'),
        'description' => t('Settings for the testing tracker module.'),
        'file' => 'admin/qatracker.admin.summary.php',
        'type' => MENU_NORMAL_ITEM,
        'page callback' => 'drupal_get_form',
        'page arguments' => array('qatracker_admin'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product', 'testcase')),
    );

    $items['admin/config/services/qatracker/summary'] = array(
        'title' => t('Summary'),
        'file' => 'admin/qatracker.admin.summary.php',
        'type' => MENU_DEFAULT_LOCAL_TASK,
        'weight' => 0,
    );

    $items['admin/config/services/qatracker/sites'] = array(
        'title' => t('Sites'),
        'description' => t('Create and modify testing tracker instances'),
        'file' => 'admin/qatracker.admin.sites.php',
        'page arguments' => array('qatracker_admin_sites'),
        'access callback' => "user_access",
        'access arguments' => array('administer site configuration'),
        'type' => MENU_LOCAL_TASK,
        'weight' => 1,
    );

    $items['admin/config/services/qatracker/sites/add'] = array(
        'title' => t("Add a site"),
        'file' => 'admin/qatracker.admin.sites.php',
        'page arguments' => array('qatracker_admin_sites_edit', "add"),
        'access callback' => "user_access",
        'access arguments' => array('administer site configuration'),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/sites/%/edit'] = array(
        'title' => t("Edit a site"),
        'file' => 'admin/qatracker.admin.sites.php',
        'page arguments' => array('qatracker_admin_sites_edit', "edit"),
        'access callback' => "user_access",
        'access arguments' => array('administer site configuration'),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/families'] = array(
        'title' => t('Product families'),
        'file' => 'admin/qatracker.admin.families.php',
        'description' => t('Create and modify product families'),
        'page arguments' => array('qatracker_admin_families'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_LOCAL_TASK,
        'weight' => 2,
    );

    $items['admin/config/services/qatracker/families/add'] = array(
        'title' => t("Add a product family"),
        'file' => 'admin/qatracker.admin.families.php',
        'page arguments' => array('qatracker_admin_families_edit', "add"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/families/%/edit'] = array(
        'title' => t("Edit a product family"),
        'file' => 'admin/qatracker.admin.families.php',
        'page arguments' => array('qatracker_admin_families_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/products'] = array(
        'title' => t('Products'),
        'description' => t('Create and modify products'),
        'file' => 'admin/qatracker.admin.products.php',
        'page arguments' => array('qatracker_admin_products'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_LOCAL_TASK,
        'weight' => 3,
    );

    $items['admin/config/services/qatracker/products/add'] = array(
        'title' => t("Add a product"),
        'file' => 'admin/qatracker.admin.products.php',
        'page arguments' => array('qatracker_admin_products_edit', "add"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/products/%/edit'] = array(
        'title' => t("Edit a product"),
        'file' => 'admin/qatracker.admin.products.php',
        'page arguments' => array('qatracker_admin_products_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/products/%/downloads'] = array(
        'title' => t("Downloads"),
        'file' => 'admin/qatracker.admin.products.downloads.php',
        'page arguments' => array('qatracker_admin_downloads'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/products/%/downloads/add'] = array(
        'title' => t("Add a link"),
        'file' => 'admin/qatracker.admin.products.downloads.php',
        'page arguments' => array('qatracker_admin_downloads_edit', "add"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/products/%/downloads/%/edit'] = array(
        'title' => t("Edit a link"),
        'file' => 'admin/qatracker.admin.products.downloads.php',
        'page arguments' => array('qatracker_admin_downloads_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/products/%/testsuites'] = array(
        'title' => t("Testsuites"),
        'file' => 'admin/qatracker.admin.products.testsuites.php',
        'page arguments' => array('qatracker_admin_product_testsuites'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/testcases'] = array(
        'title' => t("Testcases"),
        'description' => t('Create and modify testcases'),
        'file' => 'admin/qatracker.admin.testcases.php',
        'page arguments' => array('qatracker_admin_testcases'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'testcase')),
        'type' => MENU_LOCAL_TASK,
        'weight' => 4,
    );

    $items['admin/config/services/qatracker/testcases/add'] = array(
        'title' => t("Add a testcase"),
        'file' => 'admin/qatracker.admin.testcases.php',
        'page arguments' => array('qatracker_admin_testcases_edit', "add"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'testcase')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/testcases/%/edit'] = array(
        'title' => t("Edit a testcase"),
        'file' => 'admin/qatracker.admin.testcases.php',
        'page arguments' => array('qatracker_admin_testcases_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'testcase')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/testsuites'] = array(
        'title' => t("Testsuites"),
        'description' => t('Create and modify testsuitess'),
        'file' => 'admin/qatracker.admin.testsuites.php',
        'page arguments' => array('qatracker_admin_testsuites'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'testcase')),
        'type' => MENU_LOCAL_TASK,
        'weight' => 5,
    );

    $items['admin/config/services/qatracker/testsuites/add'] = array(
        'title' => t("Add a testsuite"),
        'file' => 'admin/qatracker.admin.testsuites.php',
        'page arguments' => array('qatracker_admin_testsuites_edit', "add"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'testcase')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/testsuites/%/edit'] = array(
        'title' => t("Edit a testsuite"),
        'file' => 'admin/qatracker.admin.testsuites.php',
        'page arguments' => array('qatracker_admin_testsuites_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'testcase')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/testsuites/%/testcase/%/edit'] = array(
        'title' => t("Edit a testcase in a testsuite"),
        'file' => 'admin/qatracker.admin.testsuites.php',
        'page arguments' => array('qatracker_admin_testsuites_testcase_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'testcase')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/series'] = array(
        'title' => t('Series'),
        'description' => t('Create and modify series'),
        'file' => 'admin/qatracker.admin.series.php',
        'page arguments' => array('qatracker_admin_series'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_LOCAL_TASK,
        'weight' => 6,
    );

    $items['admin/config/services/qatracker/series/add'] = array(
        'title' => t("Add a series"),
        'file' => 'admin/qatracker.admin.series.php',
        'page arguments' => array('qatracker_admin_series_edit', "add"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/series/%/edit'] = array(
        'title' => t("Edit a series"),
        'file' => 'admin/qatracker.admin.series.php',
        'page arguments' => array('qatracker_admin_series_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/series/%/manifest'] = array(
        'title' => t("Edit the series product manifest"),
        'file' => 'admin/qatracker.admin.series.php',
        'page arguments' => array('qatracker_admin_series_manifest'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/milestones'] = array(
        'title' => t('Milestones'),
        'description' => t('Create and modify milestones'),
        'file' => 'admin/qatracker.admin.milestones.php',
        'page arguments' => array('qatracker_admin_milestones'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_LOCAL_TASK,
        'weight' => 7,
    );

    $items['admin/config/services/qatracker/milestones/add'] = array(
        'title' => t("Add a milestone"),
        'file' => 'admin/qatracker.admin.milestones.php',
        'page arguments' => array('qatracker_admin_milestones_edit', "add"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/milestones/%/edit'] = array(
        'title' => t("Edit a milestone"),
        'file' => 'admin/qatracker.admin.milestones.php',
        'page arguments' => array('qatracker_admin_milestones_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    $items['admin/config/services/qatracker/builds'] = array(
        'title' => t('Builds'),
        'description' => t('Create and modify builds'),
        'file' => 'admin/qatracker.admin.builds.php',
        'page arguments' => array('qatracker_admin_builds'),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_LOCAL_TASK,
        'weight' => 8,
    );

    $items['admin/config/services/qatracker/builds/%/%/edit'] = array(
        'title' => t("Edit a build"),
        'file' => 'admin/qatracker.admin.builds.php',
        'page arguments' => array('qatracker_admin_builds_edit', "edit"),
        'access callback' => "qatracker_acl",
        'access arguments' => array('administer site configuration', array('admin', 'product')),
        'type' => MENU_VISIBLE_IN_BREADCRUMB,
    );

    return $items;
}

function qatracker_mail($key, &$message, $params) {
    $site = qatracker_get_current_site();

    $data['user'] = $params['account'];
    $options['language'] = $message['language'];
    user_mail_tokens($variables, $data, $options);

    $langcode = $message['language']->language;

    switch($key) {
        case 'builds_new':
            $message['subject'] = t(
                '!site: New build notification',
                array('!site' => $site->title),
                array('langcode' => $langcode)
            );

            $note = $params['build']->note;
            if (!$params['build']->note) {
                $note = t("None");
            }

            $testcases = "";
            foreach ($params['testcases'] as $testcase) {
                $testcases.= "\n - ".$testcase;
            }

            $message['body'][] = t(
                "A new build of !product is ready for testing!
Version: !version
Link: !build_link

Testcases:!testcase_list

Build notes:
!build_note

-- 
You are receiving this e-mail because you subscribed to this product
on the !site testing tracker.

You can change your subscription options at: !subscription_link",
                array(
                    '!product' => $params['product']->title,
                    '!version' => $params['build']->version,
                    '!build_link' => url("qatracker/milestones/".$params['milestone']->id."/builds/".$params['build']->id."/testcases", array("absolute" => TRUE)),
                    '!testcase_list' => $testcases,
                    '!build_note' => $note,
                    '!site' => $site->title,
                    '!subscription_link' => url("qatracker/subscription", array("absolute" => TRUE)),
                ),
                array('langcode' => $langcode)
            );
        break;

        case 'builds_bad':
            $message['subject'] = t('Notification from !site', $variables, array('langcode' => $langcode));
            $message['body'][] = t("Dear !username\n\nThere is new content available on the site.", $variables, array('langcode' => $langcode));
        break;

        case 'builds_remove':
            $message['subject'] = t('Notification from !site', $variables, array('langcode' => $langcode));
            $message['body'][] = t("Dear !username\n\nThere is new content available on the site.", $variables, array('langcode' => $langcode));
        break;
    }
}

?>
