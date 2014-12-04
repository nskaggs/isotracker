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

function qatracker_user_products_downloads() {
    global $qatracker_product_download_type;

    # FIXME: Turn off caching for now as it's a fairly trivial page to render
    drupal_page_is_cacheable(FALSE);

    # Parse the URL
    $milestoneid=arg(2);
    $buildid=arg(4);

    $site = qatracker_get_current_site();
    $items = qatracker_site_header($site);

    # Return on invalid website
    if (!$site) {
        return $items;
    }

    # Fetch the build
    $query = db_select('qatracker_build_milestone');
    $query->fields('qatracker_build', array('productid', 'version', 'available'));
    $query->addField('qatracker_product', 'title', 'product');
    $query->addField('qatracker_milestone_series', 'title', 'milestone_series');
    $query->addField('qatracker_milestone_series', 'id', 'milestone_seriesid');
    $query->leftJoin('qatracker_build', 'qatracker_build', 'qatracker_build.id = qatracker_build_milestone.buildid');
    $query->leftJoin('qatracker_product', 'qatracker_product', 'qatracker_product.id = qatracker_build.productid');
    $query->rightJoin('qatracker_milestone', 'qatracker_milestone', 'qatracker_milestone.id = qatracker_build_milestone.milestoneid');
    $query->rightJoin('qatracker_milestone_series', 'qatracker_milestone_series', 'qatracker_milestone_series.id = qatracker_milestone.seriesid');
    $query->condition('qatracker_build_milestone.buildid', $buildid);
    $query->condition('qatracker_build_milestone.milestoneid', $milestoneid);
    $result = $query->execute();
    $build = $result->fetch();

    if (!$build) {
        drupal_not_found();
        exit;
    }

    drupal_set_title(t("Download links for !product", array('!product' => $build->product)));

    # Show warning for unavailable files
    if ($build->available == 0) {
        $items[] = array(
            '#theme' => "table",
            '#header' => array(t("Build status")),
            '#rows' => array(array(
                        t("During the last scan, this build couldn't be found on the server, the links below may no longer work!"),
            )),
        );
    }

    # Fetch download links
    $query = db_select('qatracker_product_download');
    $query->fields('qatracker_product_download', array('filename', 'type', 'path', 'seriesid'));
    $query->condition('qatracker_product_download.productid', $build->productid);
    $query->condition(db_or()->condition('qatracker_product_download.seriesid', $build->milestone_seriesid)->condition('qatracker_product_download.seriesid', NULL));
    $query->orderBy('qatracker_product_download.filename', 'ASC');
    $query->orderBy('qatracker_product_download.type', 'ASC');
    $query->orderBy('qatracker_product_download.seriesid', 'ASC');
    $result = $query->execute()->fetchAll();

    function new_table($rows, $filename) {
        if (!$filename) {
            $filename = "";
        }
        $header = array(
            array('data' => t($filename), 'colspan' => '2'),
        );

        return array(
            '#theme' => "table",
            '#header' => $header,
            '#rows' => $rows,
        );
    }

    $skip_null = FALSE;
    foreach ($result as $record) {
        if ($record->seriesid !== NULL) {
            $skip_null = TRUE;
            break;
        }
    }

    $last_filename = NULL;
    $rows = NULL;
    foreach ($result as $record) {
        if ($skip_null == TRUE && $record->seriesid === NULL) {
            continue;
        }

        # Substitute VERSION for build->version
        $filename = str_replace("VERSION", $build->version, $record->filename);
        $path = str_replace("VERSION", $build->version, $record->path);

        # Substitute SERIES for milestone_series->title
        $filename = str_replace("SERIES", strtolower($build->milestone_series), $filename);
        $path = str_replace("SERIES", strtolower($build->milestone_series), $path);

        # Group by filename
        if ($last_filename !== Null && $last_filename != $filename) {
            $items[] = new_table($rows, $last_filename);
            $rows = array();
        }

        switch ($record->type) {
            case 1:
                # Rsync link
                $path = "rsync -tzhhP ".$path;
            break;

            case 2:
                # Zsync link
                $path = "zsync ".$path;
            break;

            case 5:
                # Just a comment
                $path = $path;
            break;

            default:
                $path = l($path, $path);
            break;
        }

        $rows[] = array(
            array('data' => $qatracker_product_download_type[$record->type], 'style' => 'width:8em'),
            $path,
        );
        $last_filename = $filename;
    }
    $items[] = new_table($rows, $last_filename);

    return $items;
}

?>
