<?php
/*
Copyright (C) 2008-2011 Stephane Graber <stgraber@ubuntu.com>

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

function qapkgstatus_menu() {
    $items = array();

    $items['qapkgstatus'] = array(
        'title' => t("Package status"),
        'description' => t("Package status"),
        'page callback' => "qapkgstatus_main",
        'access arguments' => array("access content"),
        'type' => MENU_NORMAL_ITEM,
        'weight' => -10);

    $items['admin/config/services/qapkgstatus'] = array(
        'title' => t('Package status configuration'),
        'description' => t('Settings for the package status module.'),
        'page callback' => 'drupal_get_form',
        'page arguments' => array('qapkgstatus_admin'),
        'access arguments' => array('administer site configuration'),
        'type' => MENU_NORMAL_ITEM,
    );

    return $items;
}

function qapkgstatus_main() {
    $args = arg();

    if (count($args) == 2) {
        return qapkgstatus_pkgstat($args[1]);
    }
    else {
        return qapkgstatus_introduction();
    }
}

function qapkgstatus_pkgstat($package) {
    drupal_set_title(t("Package status for @package", array("@package" => $package)));

    $input=qapkgstatus_getxml($package);
    if ($input == -1) {
        drupal_not_found();
        exit;
    }

    # Show all the nice graphs
    $items = array();
    foreach ($input->graphs->graph as $graph) {
        $url=htmlentities(trim($graph->url));
        $src=trim($graph->src);
        $attributes=$graph->attributes();
        $name=trim($attributes['name']);
        $items[] = array(
            '#type' => 'markup',
            '#markup' => l(theme("image", array('path' => $src, 'alt' => $name)), $url, array('html' => TRUE)),
        );
    }

    # Generate the footer
    $attributes=$input->attributes();
    $date=trim($attributes['timestamp']);
    $url=trim($attributes['xml']);
    $items[] = array(
        '#type' => 'markup',
        '#prefix' => "<br /><br /><i>",
        '#markup' => t("Last updated at @date !link.", array('@date' => $date, '!link' => l(t("Source XML file"), $url))),
        '#suffix' => "</i>",
    );

    return $items;
}

function qapkgstatus_introduction() {
    drupal_set_title(t("Package status"));
    $items = array();

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<h1>',
        '#markup' => t("Welcome to package status"),
        '#suffix' => '</h1>',
    );

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => t('Package status pages are intended to help package maintainers,
developers, and other interested parties measure the current state of a
package.'),
        '#suffix' => '</p>',
    );

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => t('They also help those who want to contribute distinguish
useful entry points for getting involved.'),
        '#suffix' => '</p>',
    );

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => t('Please choose a category then a package in the menu
at the left of the page.'),
        '#suffix' => '</p>',
    );

    return $items;
}

function qapkgstatus_admin() {
    $form = array();
    $form['qapkgstatus_general'] = array(
        '#type' => 'fieldset',
        '#title' => t('General'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
    );
    $form['qapkgstatus_general']['qapkgstatus_source'] = array(
        '#type' => 'textfield',
        '#title' => t('URL to the source of the .xml files.'),
        '#default_value' => variable_get('qapkgstatus_source', ''),
        '#description' => t("This URL will be queried from the cron job. A file called 'category.xml' must exist at that URL containing the list of packages."),
        '#required' => TRUE,
    );
    return system_settings_form($form);
}

?>
