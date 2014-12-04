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

function qatracker_user_subscriptions() {
    global $user;
    drupal_set_title(t("Subscriptions"));

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    $query = db_select("qatracker_user_subscription");
    $query->addField("qatracker_product", "title", "product");
    $query->addField("qatracker_testcase", "title", "testcase");
    $query->addField("qatracker_testcase", "id", "testcaseid");
    $query->addField("qatracker_product", "id", "productid");
    $query->leftJoin("qatracker_product", "qatracker_product", "qatracker_product.id = qatracker_user_subscription.productid");
    $query->leftJoin("qatracker_testcase", "qatracker_testcase", "qatracker_testcase.id = qatracker_user_subscription.testcaseid");
    $query->condition("qatracker_user_subscription.userid", $user->uid);
    $query->condition("qatracker_product.status", "1", "<>");
    $query->orderBy("qatracker_product.title", "ASC");
    $query->orderBy("qatracker_testcase.title", "ASC");
    $result = $query->execute();

    $rows = array();

    foreach ($result as $entry) {
        $rows[$entry->productid."-".$entry->testcaseid] = array(
                $entry->product,
                l($entry->testcase, "qatracker/testcases/".$entry->testcaseid."/info"),
        );
    }

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<br /><br /><div>',
        '#markup' => t("Here's the list of the various product/testcase combinations you're currently subscribed to:"),
        '#suffix' => '</div>',
    );

    $items[] = array(
        '#type' => 'tableselect',
        '#header' => array(t('Product'), t('Testcase')),
        '#options' => $rows,
        '#empty' => t("None"),
    );

    $items[] = array(
        '#type' => 'submit',
        '#value' => t("Unsubscribe"),
    );

    return $items;
}


function qatracker_user_subscriptions_submit($form, &$form_state) {
    global $user;

    # Magic to extract all the selected items
    $selection = array();
    foreach ($form_state['values'] as $element) {
        if (!is_array($element)) {
            continue;
        }
        foreach ($element as $key => $value) {
            if ($key == $value) {
                $selection[] = $key;
            }
        }
    }

    foreach ($selection as $entry) {
        $fields = explode("-", $entry);
        if (count($fields) != 2) {
            continue;
        }

        $productid = $fields[0];
        $testcaseid = $fields[1];

        $query = db_delete("qatracker_user_subscription");
        $query->condition("qatracker_user_subscription.productid", $productid);
        $query->condition("qatracker_user_subscription.testcaseid", $testcaseid);
        $query->condition("qatracker_user_subscription.userid", $user->uid);
        $query->execute();
    }

    return $form;
}

?>
