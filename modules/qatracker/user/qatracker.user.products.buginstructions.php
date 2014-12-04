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

function qatracker_user_products_buginstructions() {
    global $qatracker_product_download_type;

    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    # Parse the URL
    $buildid=arg(4);

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Fetch download links
    $query = db_select('qatracker_build');
    $query->fields('qatracker_product', array('title', 'buginstruction'));
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->condition('qatracker_build.id', $buildid);
    $result = $query->execute()->fetch();

    # Make sure that we indeed have some links to show
    drupal_set_title(t("Bug reporting instructions for !product", array('!product' => $result->title)));
    if (!$result || !$result->buginstruction) {
        $items[] = array(
            '#type' => 'markup',
            '#prefix' => '<p>',
            '#markup' => t("This product doesn't have detailed bug reporting instructions yet."),
            '#suffix' => '</p>',
        );
        return $items;
    }

    $items[] = array(
        '#type' => 'markup',
        '#prefix' => '<br /><br /><div style="padding:0.5em;background-color:#e4e4e4;">',
        '#markup' => check_markup($result->buginstruction, "filtered_html"),
        '#suffix' => '</div>',
    );

    return $items;
}

?>
