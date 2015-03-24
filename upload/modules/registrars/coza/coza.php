<?php if (!defined('WHMCS')) die('This file cannot be accessed directly');

/**
 * This file is part of the whmcs-registrars-coza library.
 *
 * (c) Gunter Grodotzki <gunter@afri.cc>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

require_once ROOTDIR . '/includes/classes/AfriCC/autoload.php';
require_once ROOTDIR . '/modules/registrars/coza/Factory.php';
require_once ROOTDIR . '/includes/clientfunctions.php';

function coza_getConfigArray()
{
    $config_array = [
        'FriendlyName'  => [
            'Type'          => 'System',
            'Value'         => 'CO.ZA',
        ],
        'Description'   => [
            'Type'          => 'System',
            'Value'         => '<a href="https://github.com/AfriCC/whmcs-registrars-coza">GitHub</a> | <a href="https://www.registry.net.za">Registry</a>',
        ],
        'Username'      => [
            'FriendlyName'  => 'EPP Username',
            'Type'          => 'text',
            'Default'       => '',
            'Size'          => 64,
            'Description'   => '',
        ],
        'Password'      => [
            'FriendlyName'  => 'EPP Password',
            'Type'          => 'password',
            'Default'       => '',
            'Size'          => 64,
            'Description'   => '',
        ],
        'Certificate'   => [
            'FriendlyName'  => 'SSL Certificate',
            'Type'          => 'text',
            'Default'       => '',
            'Size'          => 64,
            'Description'   => 'Path to local .pem certificate (not required for OT&E)',
        ],
        'ContactPrefix' => [
            'FriendlyName'  => 'Contact Prefix',
            'Type'          => 'text',
            'Default'       => '',
            'Size'          => 64,
            'Description'   => 'Prefix when creating Contact Handles',
        ],
        'OTE'           => [
            'FriendlyName'  => 'OT&amp;E',
            'Type'          => 'yesno',
            'Default'       => false,
            'Description'   => 'Enable Test Server',
        ],
        'TestUsername'  => [
            'FriendlyName'  => 'OT&amp;E Username',
            'Type'          => 'text',
            'Default'       => '',
            'Size'          => 64,
            'Description'   => '',
        ],
        'TestPassword'  => [
            'FriendlyName'  => 'OT&amp;E Password',
            'Type'          => 'password',
            'Default'       => '',
            'Size'          => 64,
            'Description'   => '',
        ],
    ];

    return $config_array;
}

function coza_GetNameservers($params)
{
    $values = [];

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        $frame = new \AfriCC\EPP\Frame\Command\Info\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/GetNameservers: unable to fetch nameservers'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/GetNameservers: %s (%d)', $response->message(), $response->code())];
        }

        $data = $response->data();
        if (empty($data) || !is_array($data) || empty($data['infData']['ns']['hostAttr']) || !is_array($data['infData']['ns']['hostAttr'])) {
            unset($epp_client);
            return ['error' => 'COZA/GetNameservers: unable to parse nameservers'];
        }

        foreach ($data['infData']['ns']['hostAttr'] as $i => $host_attr) {
            if (!empty($params['OTE']) && $params['OTE'] === 'on') {
                $host_attr['hostName'] = str_replace('test.dnservices.co.za', 'co.za', $host_attr['hostName']);
            }
            $values['ns' . ($i + 1)] = $host_attr['hostName'];
        }

        unset($epp_client);
        return $values;

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/GetNameservers: ' . $e->getMessage()];
    }
}

function coza_SaveNameservers($params)
{
    $old_records = $glue_records = $ns_add = $ns_rem = [];
    $epp_client  = \COZA\Factory::build($params);
    $domain      = \COZA\Factory::getDomain($params);

    try {
        $epp_client->connect();

        // get a list of nameservers first, we need to ignore glue records
        $frame = new \AfriCC\EPP\Frame\Command\Info\Domain;
        $frame->setDomain($domain);

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/SaveNameservers: unable to fetch response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/SaveNameservers: %s (%d)', $response->message(), $response->code())];
        }

        $data = $response->data();
        if (empty($data) || !is_array($data) || empty($data['infData']['ns']['hostAttr']) || !is_array($data['infData']['ns']['hostAttr'])) {
            unset($epp_client);
            return ['error' => 'COZA/SaveNameservers: unable to parse response'];
        }

        foreach ($data['infData']['ns']['hostAttr'] as $host_attr) {
            if (!empty($params['OTE']) && $params['OTE'] === 'on') {
                $host_attr['hostName'] = str_replace('test.dnservices.co.za', 'co.za', $host_attr['hostName']);
            }
            
            // only if it is a glue record within this domain
            if (!empty($host_attr['hostAddr']) && preg_match('/' . preg_quote($domain, '/') . '$/i', $host_attr['hostName'])) {
                $glue_records[$host_attr['hostName']] = true;
            } else {
                $old_records[$host_attr['hostName']] = true;
            }
        }

        // diff
        for ($i = 1; $i <= 5; ++$i) {
            // skip empty ns
            if (empty($params['ns' . $i])) {
                continue;
            }
            // skip glue records
            // the reason for this is, that we need to force the user to use the actual
            // glue record api (not using it will cause an error here anyway)
            elseif (isset($glue_records[$params['ns' . $i]])) {
                continue;
            }
            // skip if already exists
            elseif (isset($old_records[$params['ns' . $i]])) {
                unset($old_records[$params['ns' . $i]]);
                continue;
            }
            // else add
            else {
                $ns_add[] = $params['ns' . $i];
            }
        }

        $ns_rem = array_diff(array_keys($old_records), $ns_add);

        if (empty($ns_add) && empty($ns_rem)) {
            unset($epp_client);
            return ['error' => 'COZA/SaveNameservers: no changes made'];
        }

        $frame = new \AfriCC\EPP\Frame\Command\Update\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));
        if (!empty($ns_add)) {
            foreach ($ns_add as $add) {
                $frame->addHostAttr($add);
            }
        }
        if (!empty($ns_rem)) {
            foreach ($ns_rem as $rem) {
                $frame->removeHostAttr($rem);
            }
        }

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/SaveNameservers: unable to fetch response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/SaveNameservers: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);
        return ['status' => $response->message()];

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/SaveNameservers: ' . $e->getMessage()];
    }
}

function coza_RegisterNameserver($params)
{
    // see RegisterDomain()
    // OH INTERESTING little shit fucks, here they do NOT validate/sanitize
    // inputs, so we can support IPv6 and round-robin entries...
    if (empty($params['ipaddress']) || !is_array($params['ipaddress'])) {
        return ['error' => 'Please provide at least one valid IP address'];
    }
    $ips = [];
    foreach ($params['ipaddress'] as $ip) {
        if (empty($ip)) {
            continue;
        }
        $ips[] = $ip;
    }
    if (empty($ips)) {
        return ['error' => 'Please provide at least one valid IP address'];
    }

    // modify TLD if we are on OTE
    if (!empty($params['OTE']) && $params['OTE'] === 'on') {
        $params['nameserver'] = preg_replace('/' . preg_quote($params['domainname'], '/') . '$/i', '', $params['nameserver']);
        $params['nameserver'] .= $params['sld'] . '.test.dnservices.co.za';
    }

    // lets create the frame first and therfor use the validation of the library
    try {
        $frame = new \AfriCC\EPP\Frame\Command\Update\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));
        $frame->addHostAttr($params['nameserver'], $ips);
    } catch(Exception $e) {
        return ['error' => 'COZA/RegisterNameserver: ' . $e->getMessage()];
    }

    // currently we are not using the "host" mapping, meaning glue records are
    // bound to the actual domain which I think is ok for now, as you usually
    // would not create glue records and not use them directly...
    $epp_client = \COZA\Factory::build($params);
    try {
        $epp_client->connect();

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/RegisterNameserver: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/RegisterNameserver: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);
        return ['status' => $response->message()];
    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/RegisterNameserver: ' . $e->getMessage()];
    }
}

function coza_ModifyNameserver($params)
{
    return ['error' => 'Please use "Create a Glue Record" and "Delete a Glue Record"'];
}

function coza_DeleteNameserver($params)
{
    if (!empty($params['OTE']) && $params['OTE'] === 'on') {
        $params['nameserver'] = preg_replace('/(\.co\.za)$/i', '.test.dnservices.co.za', $params['nameserver']);
    }

    // lets create the frame first and therfor use the validation of the library
    try {
        $frame = new \AfriCC\EPP\Frame\Command\Update\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));
        $frame->removeHostAttr($params['nameserver']);
    } catch(Exception $e) {
        return ['error' => 'COZA/DeleteNameserver: ' . $e->getMessage()];
    }

    $epp_client = \COZA\Factory::build($params);
    try {
        $epp_client->connect();

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/DeleteNameserver: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/DeleteNameserver: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);
        return ['status' => $response->message()];
    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/DeleteNameserver: ' . $e->getMessage()];
    }
}

function coza_RegisterDomain($params)
{
    // unfortunately the sub-contactid is not given directly, so we will have to
    // fetch it by ourselfs
    // funny enough tbldomains only contains the user-id but not the (optional)
    // (sub-) account contact-id
    // on domain renewals a new orderid will be created, but we will use the
    // origin as reference domainid <> userid <> contactid
    $result = select_query('tblorders', 'contactid', ['tbldomains.id' => (int) $params['domainid']], null, null, null, 'tbldomains ON tblorders.id = tbldomains.orderid');
    if ($result === false || mysql_num_rows($result) !== 1) {
        // this should only happen on forged POST-request
        return ['error' => 'COZA/RegisterDomain: unknown contact'];
    }
    $data = mysql_fetch_array($result);

    // generate reusable contact-handle
    $contact_handle = \COZA\Factory::getContactHandle($params, (int) $params['userid'], (int) $data['contactid']);

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        try {
            \COZA\Factory::createContactIfNotExists($epp_client, $contact_handle, $params);
        } catch (Exception $e) {
            unset($epp_client);
            return ['error' => $e->getMessage()];
        }

        // create domain
        $frame = new \AfriCC\EPP\Frame\Command\Create\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));

        for ($i = 1; $i <= 5; ++$i) {
            if (!empty($params['ns' . $i])) {
                // unfortunately WHMCS does not support glue records on the
                // registration page (gets replaced by default nameserver, as it
                // might be using some fancy input validator/sanitizer - but
                // who the hell would know, as those fuckers encoded that crap
                // they call software, most probably to not embarrass themselves
                $frame->addHostAttr($params['ns' . $i]);
            }
        }

        $frame->setRegistrant($contact_handle);
        // currently coza only allows this static auth-code, most probably
        // they did not implement yet transfer-codes...
        $frame->setAuthInfo('coza');

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/RegisterDomain: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/RegisterDomain: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);
        return ['status' => $response->message()];

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/RegisterDomain: ' . $e->getMessage()];
    }
}

function coza_TransferDomain($params)
{
    // we will only do a transfer request in here. contact-update/ns-update
    // will be done at TransferSync
    // Also note:
    // 1) no transfer codes are currently needed by CO.ZA
    // 2) Approve/Deny are done via email by the registrant which is the
    //    safest solution as it does not involve the registrar verifying the
    //    request, so we will not implement "Ack/Nack" Admin buttons...

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        $frame = new \AfriCC\EPP\Frame\Command\Transfer\Domain;
        $frame->setOperation('request');
        $frame->setDomain(\COZA\Factory::getDomain($params));

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/TransferDomain: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/TransferDomain: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);
        return ['status' => $response->message()];

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/TransferDomain: ' . $e->getMessage()];
    }
}

function coza_RenewDomain($params)
{
    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        // first we need to get the current expiration date of the domain
        $frame = new \AfriCC\EPP\Frame\Command\Info\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params), 'none');

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/RenewDomain: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/RenewDomain: %s (%d)', $response->message(), $response->code())];
        }

        $data = $response->data();
        if (empty($data['infData']['exDate'])) {
            unset($epp_client);
            return ['error' => 'COZA/RenewDomain: unable to parse response'];
        }

        // CO.ZA currently does not allow the setting of periods, so by default
        // it will always renew for a year.
        $frame = new \AfriCC\EPP\Frame\Command\Renew\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));
        $frame->setCurrentExpirationDate(date('Y-m-d', strtotime($data['infData']['exDate'])));

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/RenewDomain: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/RenewDomain: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);
        return ['status' => $response->message()];

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/RenewDomain: ' . $e->getMessage()];
    }
}

function coza_GetContactDetails($params)
{
    // get current domain registrant
    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        // create domain
        $frame = new \AfriCC\EPP\Frame\Command\Info\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params), 'none');

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/GetContactDetails: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/GetContactDetails: %s (%d)', $response->message(), $response->code())];
        }

        $data = $response->data();
        if (empty($data['infData']['registrant'])) {
            unset($epp_client);
            return ['error' => 'COZA/GetContactDetails: unable to parse response'];
        }

        unset($epp_client);

        $contact = \COZA\Factory::resolveContactHandle($params, $data['infData']['registrant']);
        if ($contact === false) {
            // legacy contacts
            return ['Registrant' => ['user_id' => 0, 'contact_id' => 0]];
        }

        return ['Registrant' => ['user_id' => $contact['user_id'], 'contact_id' => $contact['contact_id']]];

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => 'COZA/GetContactDetails: ' . $e->getMessage()];
    }
}

function coza_SaveContactDetails($params)
{
    // get contact details
    if (empty($_POST['sel']['Registrant']) || !preg_match('/^(c|u)(\d+)$/i', $_POST['sel']['Registrant'], $matches)) {
        // should actually only happen if someone tries to forge the POST request
        return ['error' => 'COZA/SaveContactDetails: no contact selected'];
    }

    if ($matches[1] === 'u') {
        $user_id = (int) $matches[2];
        $contact_id = 0;
    } else {
        $contact_id = (int) $matches[2];
        // get user id from contact-id
        $result = select_query('tblcontacts', 'userid', ['id' => $contact_id]);
        if ($result === false || mysql_num_rows($result) !== 1) {
            // should actually only happen if someone tries to forge the POST request
            return ['error' => 'COZA/SaveContactDetails: no contact selected'];
        }
        $data = mysql_fetch_array($result);
        $user_id = (int) $data['userid'];
    }

    // now lets verify if user-id is the current owner of the domain
    // again, this should actually only happen if someone is trying to forge
    // the POST request
    $result = select_query('tbldomains', 'userid', ['id' => $params['domainid']]);
    if ($result === false || mysql_num_rows($result) !== 1) {
        return ['error' => 'COZA/SaveContactDetails: no contact selected'];
    }
    $data = mysql_fetch_array($result);
    $data['userid'] = (int) $data['userid'];
    if ($data['userid'] !== $user_id) {
        return ['error' => 'COZA/SaveContactDetails: no contact selected'];
    }

    // I know we are theoretically wasting a mysql-query here, but it would be
    // a PINTA to simulate the same output while doing our own sql-query
    // also we can not rely on the POST, as that can be forged.
    $contact = getClientsDetails($user_id, $contact_id);

    // generate reusable client-id
    $contact_handle = \COZA\Factory::getContactHandle($params, $user_id, $contact_id);

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        // lets check if contact already exists at the registrar, if not create
        try {
            \COZA\Factory::createContactIfNotExists($epp_client, $contact_handle, $contact);
        } catch (Exception $e) {
            unset($epp_client);
            return ['error' => $e->getMessage()];
        }

        // update domain contact
        $frame = new \AfriCC\EPP\Frame\Command\Update\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));
        $frame->changeRegistrant($contact_handle);

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/SaveContactDetails: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/SaveContactDetails: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);

        // we could update the ordertable here to reflect the new data - but
        // lets do that in future. Currently it would mean to hack the admin
        // pages as well

        return ['status' => $response->message()];

    } catch (Exception $e) {
        unset($epp_client);
        return ['error' => sprintf('COZA/SaveContactDetails: %s', $e->getMessage())];
    }
}

function coza_RequestDelete($params)
{
    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        $frame = new \AfriCC\EPP\Frame\Command\Delete\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/RequestDelete: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/RequestDelete: %s (%d)', $response->message(), $response->code())];
        }

        unset($epp_client);
        return ['status' => $response->message()];

    } catch (Exception $e) {
        unset($epp_client);
        return ['error' => sprintf('COZA/RequestDelete: %s', $e->getMessage())];
    }
}

function coza_TransferSync($params)
{
    // currently we can only figure out if a transfer was rejected, by reading
    // the poll messages. Until I implemented some log-parser and hooks, it is
    // up to the admin to read the poll messages and do manual action on failed
    // transfer requests.

    // https://www.registry.net.za/content.php?wiki=1&contentid=25&title=Transfer%20Cleanup

    // get our consistent contact-id by getting it from the tblorders <> tbldomains
    $result = select_query(
        'tblorders',
        'tblorders.userid, tblorders.contactid, tblorders.nameservers',
        ['tbldomains.id' => (int) $params['domainid']],
        null,
        null,
        null,
        'tbldomains ON tblorders.id = tbldomains.orderid'
    );

    if ($result === false || mysql_num_rows($result) !== 1) {
        // this should only happen on forged POST-request
        return ['error' => 'COZA/TransferSync: unknown order'];
    }
    $data = mysql_fetch_array($result);

    $user_id = (int) $data['userid'];
    $contact_id = (int) $data['contactid'];
    $nameservers = explode(',', $data['nameservers']);
    $nameservers = array_flip($nameservers);
    $contact_handle = \COZA\Factory::getContactHandle($params, $user_id, $contact_id);

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        // verify if domain is ours
        $frame = new \AfriCC\EPP\Frame\Command\Info\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/TransferSync: unable to get response'];
        }

        // permanent fail, domain is available
        // @todo register domain for the client
        if ($response->code() === 2303) {
            unset($epp_client);
            return ['failed' => true, 'reason' => $response->message()];
        }

        // other reasons
        if (!$response->success()) {
            unset($epp_client);
            return ['error' => sprintf('COZA/TransferSync: %s (%d)', $response->message(), $response->code())];
        }

        $data = $response->data();
        if (empty($data['infData']['clID']) || empty($data['infData']['exDate'])) {
            unset($epp_client);
            return ['error' => 'COZA/TransferSync: unable to parse response'];
        }

        // transfer not yet completed (tempfail)
        if ($data['infData']['clID'] !== \COZA\Factory::getRegistrarId($params)) {
            unset($epp_client);

            return ['error' => 'COZA/TransferSync: transfer not yet completed'];
        }

        // @todo if the transfer was rejected, the status should be anything else
        // OTHER than "pendingTransfer" AND have losing registrar as clID
        // meaning the transfer was then rejected - in this case we also need
        // to return a permfail

        $contact = getClientsDetails($user_id, $contact_id);

        // create contact if not exists
        try {
            \COZA\Factory::createContactIfNotExists($epp_client, $contact_handle, $contact);
        } catch (Exception $e) {
            unset($epp_client);
            return ['error' => $e->getMessage()];
        }

        // prepare domain update
        $frame = new \AfriCC\EPP\Frame\Command\Update\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params));

        // override nameservers
        $ns_add = $ns_rem = [];
        if (!empty($data['infData']['ns']['hostAttr']) && is_array($data['infData']['ns']['hostAttr'])) {
            foreach ($data['infData']['ns']['hostAttr'] as $host_attr) {
                if (!isset($nameservers[$host_attr['hostName']])) {
                    $ns_rem[] = $host_attr['hostName'];
                } else {
                    $ns_add[] = $host_attr['hostName'];
                    unset($nameservers[$host_attr['hostName']]);
                }
            }
        }

        $ns_add = array_merge($ns_add, array_keys($nameservers));

        if (!empty($ns_add)) {
            foreach ($ns_add as $host) {
                $frame->addHostAttr($host);
            }
        }

        if (!empty($ns_rem)) {
            foreach ($ns_rem as $host) {
                $frame->removeHostAttr($host);
            }
        }

        // apply new contact
        if ($data['infData']['registrant'] !== $contact_handle) {
            $frame->changeRegistrant($contact_handle);
        }

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/TransferSync: unable to get response'];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['error' => 'COZA/TransferSync: ' . $response->message()];
        }

        // delete old contact
        if ($data['infData']['registrant'] !== $contact_handle) {
            // we can not delete contact handles right away, as it takes 5 days
            // until the old contact was replaced by the new contact. So lets
            // put in a queue and let a cronjob handle it
            insert_query('mod_coza_contact_deletequeue', [
                'next_due' => date('Y-m-d H:i:s', strtotime('+6 day')),
                'contact_handle' => $data['infData']['registrant'],
                'deleted' => 0,
            ]);
        }

        unset($epp_client);
        return ['completed' => true, 'expirydate' => date('Y-m-d', strtotime($data['infData']['exDate']))];

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => sprintf('COZA/TransferSync: %s', $e->getMessage())];
    }
}

function coza_Sync($params)
{
    // ok here is another WHMCS fuckup. We can only do the following:
    // inactive => active
    // active => expired
    // e.g. it is not possible to mark domains that were transfered out as such.
    // we will do manual sql queries to achieve that. In future we might actually
    // delete the domain completely out of the DB instead, but currently I'd
    // like to know which domains were tagged as deleted and manually remove
    // them out of the DB if needed...

    // @todo check if domain is set to expire / donotrenew
    // https://www.registry.net.za/content.php?wiki=1&contentid=19&title=EPP%20Domain%20Extensions#autorenew_command

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        // get domain info
        $frame = new \AfriCC\EPP\Frame\Command\Info\Domain;
        $frame->setDomain(\COZA\Factory::getDomain($params), 'none');

        $response = $epp_client->request($frame);
        unset($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['error' => 'COZA/Sync: unable to get response'];
        }

        // domain does not exist
        // @todo figure out if domain is still wanted or not
        if ($response->code() === 2303) {
            unset($epp_client);
            return ['error' => 'COZA/Sync: domain is not registered'];
        }

        // generic error
        if (!$response->success()) {
            unset($epp_client);
            return ['error' => 'COZA/Sync: ' . $response->message()];
        }

        $data = $response->data();
        if (empty($data['infData']['clID']) || empty($data['infData']['exDate'])) {
            unset($epp_client);
            return ['error' => 'COZA/Sync: unable to parse response'];
        }

        // lets check if domain still belongs to us, if not it was either
        // transferred out or never belonged to us in first place
        if ($data['infData']['clID'] !== \COZA\Factory::getRegistrarId($params)) {
            // as stated above we will have to do a manual sql query
            update_query('tbldomains', ['status' => 'Cancelled'], ['id' => (int) $params['domainid']]);
            unset($epp_client);
            return ['active' => false];
        }

        // check if expired...
        if (!empty($data['infData']['status'])) {
            if (!is_array($data['infData']['status'])) {
                $data['infData']['status'] = [$data['infData']['status']];
            }

            // https://www.registry.net.za/content2.php?contentid=55
            foreach ($data['infData']['status'] as $status) {
                if ($status === 'inactive' || $status === 'pendingDelete') {
                    unset($epp_client);
                    return ['expired' => true];
                }
            }
        }

        // else all fine, lets set to active and the new expiration date
        unset($epp_client);
        return ['active' => true, 'expirydate' => date('Y-m-d', strtotime($data['infData']['exDate']))];

    } catch(Exception $e) {
        unset($epp_client);
        return ['error' => sprintf('COZA/TransferSync: %s', $e->getMessage())];
    }
}
