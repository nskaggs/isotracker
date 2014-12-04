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

function qatracker_xmlrpc() {
    // RPC functions
    require_once("xmlrpc/qatracker.xmlrpc.builds.php");
    require_once("xmlrpc/qatracker.xmlrpc.bugs.php");
    require_once("xmlrpc/qatracker.xmlrpc.milestones.php");
    require_once("xmlrpc/qatracker.xmlrpc.products.php");
    require_once("xmlrpc/qatracker.xmlrpc.rebuilds.php");
    require_once("xmlrpc/qatracker.xmlrpc.results.php");
    require_once("xmlrpc/qatracker.xmlrpc.series.php");
    require_once("xmlrpc/qatracker.xmlrpc.testcases.php");

    return array(
        # Export: qatracker_xmlrpc_get_access()
        # ACL: public
        # Returns: public / user / admin (depending on current role
        array(
            "qatracker.get_access",
            "qatracker_xmlrpc_get_access",
            array("string"),
            t("Returns one of public, user or admin (depending on role)")
        ),

        # Export: qatracker_build_add(productid, milestoneid, version, note, notify)
        # ACL: admin
        # Returns: True if successful, False otherwise
        array(
            "qatracker.builds.add",
            "qatracker_xmlrpc_builds_add",
            array("boolean", "int", "int", "string", "string", "boolean"),
            t("Add a new build to the tracker")
        ),

        # Export: qatracker_builds_get_list(milestoneid, status)
        # ACL: public
        # Returns: list of builds
        array(
            "qatracker.builds.get_list",
            "qatracker_xmlrpc_builds_get_list",
            array("array", "int", "array"),
            t("List all the builds for a given milestone and list of status")
        ),

        # Export: qatracker_bugs_get_list(milestoneid)
        # ACL: public
        # Returns: list of bugs for given milestone (all milestones if milestoneid = 0)
        array(
            "qatracker.bugs.get_list",
            "qatracker_xmlrpc_bugs_get_list",
            array("array", "int"),
            t("List all the bugs for a given milestone, or all milestones if milestoneid = 0")
        ),

        # Export: qatracker_products_get_list(status)
        # ACL: public
        # Returns: list of products
        array(
            "qatracker.products.get_list",
            "qatracker_xmlrpc_products_get_list",
            array("array", "array"),
            t("List all the products for a given list of status")
        ),

        # Export: qatracker_milestones_get_list(status)
        # ACL: public
        # Returns: list of milestones
        array(
            "qatracker.milestones.get_list",
            "qatracker_xmlrpc_milestones_get_list",
            array("array", "array"),
            t("List all the milestones for a given list of status")
        ),

        # Export: qatracker_rebuilds_get_list(status)
        # ACL: user
        # Returns: list of rebuilds
        array(
            "qatracker.rebuilds.get_list",
            "qatracker_xmlrpc_rebuilds_get_list",
            array("array", "array"),
            t("List all the rebuilds for a given list of status")
        ),

        # Export: qatracker_rebuilds_update(rebuildid, status)
        # ACL: admin
        # Returns: ID of entry, -1 if failed
        array(
            "qatracker.rebuilds.update_status",
            "qatracker_xmlrpc_rebuilds_update_status",
            array("int", "int", "int"),
            t("Update a rebuild entry's status")
        ),

        # Export: qatracker_results_add(buildid, testcaseid, result, comment, hardware, bugs)
        # ACL: user
        # Returns: ID of result, -1 if failed
        array(
            "qatracker.results.add",
            "qatracker_xmlrpc_results_add",
            array("int", "int", "int", "int", "string", "string", "struct"),
            t("Submit a new result")
        ),

        # Export: qatracker_results_delete(resultid)
        # ACL: user
        # Returns: True if successful, False otherwise
        array(
            "qatracker.results.delete",
            "qatracker_xmlrpc_results_delete",
            array("boolean", "int"),
            t("Remove a result")
        ),

        # Export: qatracker_results_get_list(buildid, testcaseid, status)
        # ACL: public
        # Returns: list of results
        array(
            "qatracker.results.get_list",
            "qatracker_xmlrpc_results_get_list",
            array("array", "int", "int", "array"),
            t("List all the results for a given build, testcase and list of status")
        ),

        # Export: qatracker_results_update(resultid, result, comment, hardware, bugs)
        # ACL: user
        # Returns: True if successful, False otherwise
        array(
            "qatracker.results.update",
            "qatracker_xmlrpc_results_update",
            array("boolean", "int", "int", "string", "string", "struct"),
            t("Update an existing result")
        ),

        # Export: qatracker_series_get_list()
        # ACL: public
        # Returns: list of series
        array(
            "qatracker.series.get_list",
            "qatracker_xmlrpc_series_get_list",
            array("array", "array"),
            t("List all the available series for given status")
        ),

        # Export: qatracker_series_get_manifest(seriesid)
        # ACL: public
        # Returns: list of products in the manifest for the series
        array(
            "qatracker.series.get_manifest",
            "qatracker_xmlrpc_series_get_manifest",
            array("array", "int", "array"),
            t("List all the products in the manifest for a given series and status")
        ),

        # Export: qatracker_testcases_get_list(productid, seriesid, status)
        # ACL: public
        # Returns: list of testcases
        array(
            "qatracker.testcases.get_list",
            "qatracker_xmlrpc_testcases_get_list",
            array("array", "int", "int", "array"),
            t("List all the testcases for a given product, series and list of status")
        ),
    );
}

function qatracker_xmlrpc_get_access() {
    global $user;

    # Check that we're using a valid instance
    $site = qatracker_get_current_site();
    if (!$site) {
        return "invalid";
    }

    if (qatracker_xmlrpc_security($site->adminrole)) {
        return "admin";
    }

    if (qatracker_xmlrpc_security($site->userrole)) {
        return "user";
    }

    return "public";
}

function qatracker_xmlrpc_security($wanted_role) {
    global $user;

    # Check that we indeed have the HTTP headers
    if (!array_key_exists("PHP_AUTH_USER", $_SERVER) || !array_key_exists("PHP_AUTH_PW", $_SERVER)) {
        return False;
    }

    # Load the user
    $user = user_load_by_name($_SERVER['PHP_AUTH_USER']);
    if (!$user) {
        return False;
    }

    # Make sure the user has an API key in their profile
    if (!is_array($user->data) || !array_key_exists("qatracker_api_key", $user->data)) {
        return False;
    }

    # Check that the key is valid
    if ($user->data['qatracker_api_key'] != $_SERVER['PHP_AUTH_PW']) {
        return False;
    }

    # Check if they have the needed role
    foreach ($user->roles as $rid => $role) {
        if ($rid == $wanted_role) {
            return True;
        }
    }

    # If not, decline
    return False;
}
?>
