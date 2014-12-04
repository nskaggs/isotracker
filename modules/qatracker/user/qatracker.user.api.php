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

function qatracker_user_api() {
    global $user;
    drupal_set_title(t("API"));

    if (!is_array($user->data) || !array_key_exists("qatracker_api_key", $user->data)) {
        $user->data['qatracker_api_key'] = "";
    }

    $form = array();

    $form['qatracker_api_intro'] = array(
        '#type' => 'fieldset',
        '#title' => t('Introduction'),
    );

    $form['qatracker_api_intro']['intro'] = array(
        '#type' => 'markup',
        '#markup' => t('
The testing tracker offers an XML-RPC API to access
its content and add new results.<br />

Accessing content can be done anonymously,
however adding, updating or removing results requires
authentication using your login and the API key below.<br />

The API has been developed based on demand and only covers a
limited set of features at this point.<br /><br />

It\'s currently possible to:
<ul>
    <li>List milestones</li>
    <li>List products</li>
    <li>List testcases</li>
    <li>List and add builds</li>
    <li>List, add, remove and update results</li>
    <li>List bugs</li>
</ul>

The API isn\'t considered stable at the moment, to minimize the
risk of breakage, it\'s recommended to use the provided python
module and keep it up to date.
'),
    );

    $form['qatracker_api'] = array(
        '#type' => 'fieldset',
        '#title' => t('Configuration'),
    );

    $form['qatracker_api']['qatracker_api_apikey'] = array(
        '#type' => 'textfield',
        '#title' => t('API key'),
        '#default_value' => $user->data['qatracker_api_key'],
        '#description' => t("API key for XMLRPC interface. Your username is the login."),
        '#required' => FALSE,
    );

    $form['qatracker_api']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save'),
    );

    $form['qatracker_api_module'] = array(
        '#type' => 'fieldset',
        '#title' => t('Python module'),
    );

    $form['qatracker_api_module']['module'] = array(
        '#type' => 'markup',
        '#markup' => t('
The python module can be downloaded from Launchpad.<br />
Link: !link<br />
Bzr: bzr branch lp:~ubuntu-qa-website-devel/ubuntu-qa-website/python-qatracker
', array("!link" => l(
        "https://code.launchpad.net/~ubuntu-qa-website-devel/ubuntu-qa-website/python-qatracker",
        "https://code.launchpad.net/~ubuntu-qa-website-devel/ubuntu-qa-website/python-qatracker")
        )),
    );

    $form['qatracker_api_example'] = array(
        '#type' => 'fieldset',
        '#title' => t('Example'),
    );
    $form['qatracker_api_example']['example'] = array(
        '#type' => 'markup',
        '#markup' => '
<pre>
#!/usr/bin/python3
import sys
from qatracker import QATracker

# Establish the XML-RPC connection (user and api-key are optional for read-only access)
instance = QATracker("http://tracker.example.net/xmlrpc.php", "user", "api-key")

# Get the first testing or released milestone
milestones = instance.get_milestones(["testing", "released"])

# Get a list of all products
products = instance.get_products()

if len(milestones) == 0:
    print("No testing or released milestones")
    sys.exit(1)

for milestone in milestones:
    # Print a summary of the first 5 builds for that milestone
    print("\nBuilds for %s" % milestone.title)
    for build in milestone.get_builds(["active", "re-building"])[:5]:
        product = [product for product in products if product.id == build.productid][0]
        print(" - %s => %s (%s)" % (product.title, build.version, build.status_string))

        # Print a summary of the first 2 mandatory testcases for that product
        for testcase in product.get_testcases(milestone, "mandatory")[:2]:
            print("   - %s (%s results)" % (testcase.title, len(build.get_results(testcase))))

    # Print a summary of the first 5 bugs for that milestone
    print("Bugs for %s" % milestone.title)
    for bug in milestone.get_bugs()[:5]:
        print(" - %s => seen %s time(s), first reported on \'%s\', last reported on \'%s\'" % \
            (bug.bugnumber, bug.count, bug.earliest_report, bug.latest_report))

milestones = instance.get_milestones("testing")
products = instance.get_products("active")

if len(milestones) == 0 or len(products) == 0:
    print("No testing milestone or product")
    sys.exit(1)

# The part below requires user access rights
if instance.access in (\'admin\', \'user\', None):
    builds = milestones[0].get_builds()
    if len(builds) != 0:
        product = [product for product in products if product.id == builds[0].productid][0]
        testcases = product.get_testcases(milestones[0])
        if len(testcases) != 0:
            result = builds[0].add_result(testcases[0], "in progress", "Just a test")
            print(result)
            result.hardware = "http://my-hardware-profile.com"
            result.save()
            result.delete()

# The part below requires admin access rights
if instance.access in (\'admin\', None):
    print("Adding a new build to \'%s\' for product \'%s\'" % (milestones[0].title, products[0].title))
    print(milestones[0].add_build(products[0], "test"))
</pre>
',
    );


    return $form;
}

function qatracker_user_api_submit($form, &$form_state) {
    global $user;

    # Prepare edit array
    $edit = array();
    if (is_array($user->data)) {
        $edit['data'] = $user->data;
    }
    $edit['data']['qatracker_api_key'] = $form['qatracker_api']['qatracker_api_apikey']['#value'];

    # Save the changes
    user_save($user, $edit);

    return $form;
}

?>
