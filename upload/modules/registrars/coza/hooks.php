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
//require_once ROOTDIR . '/includes/clientfunctions.php';
//require_once ROOTDIR . '/includes/registrarfunctions.php';


// everytime a client updates himself or a sub-account, we need to send EPP
// calls as well, or contacts <> domain-whois will be out-of-sync
// we can ignore contact/client-add, as we can "lazy-create" them once they are
// being assigned to a domain
// also contact/client-delete we have to ignore for now, as we whould have to do
// checks prior what domains are still assigned on that contact...
add_hook('ContactEdit', 1, 'hook_coza_contact_update');
add_hook('ClientEdit', 1, 'hook_coza_client_update');
// BUT there is more... as usual, WHMCS decides not to allowe return values
// so we can't really ensure that the changes went to the Registrar.
// @todo implement a "RefreshContact" Admin-button, in case a user complaints.

/**
 * @link http://docs.whmcs.com/Hooks:ContactEdit
 * @param array $vars
 */
function hook_coza_contact_update($vars) {

    $params = getRegistrarConfigOptions('coza');
    $contact = getClientsDetails($vars['userid'], $vars['contactid']);

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        try {
            \COZA\Factory::updateContactIfExists(
                $epp_client,
                \COZA\Factory::getContactHandle($params, (int) $vars['userid'], (int) $vars['contactid']),
                $contact
            );
        } catch (Exception $e) {
            unset($epp_client);
            logActivity($e->getMessage(), $vars['userid']);
            return;
        }

        unset($epp_client);
        return;

    } catch (Exception $e) {
        unset($epp_client);
        logActivity('COZA/ContactUpdate: ' . $e->getMessage(), $vars['userid']);
        return;
    }
}

/**
 * @link http://docs.whmcs.com/Hooks:ClientEdit
 * @param array $vars
 */
function hook_coza_client_update($vars) {

    $params = getRegistrarConfigOptions('coza');
    $contact = getClientsDetails($vars['userid'], 0);

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        try {
            \COZA\Factory::updateContactIfExists(
                $epp_client,
                \COZA\Factory::getContactHandle($params, (int) $vars['userid']),
                $contact
            );
        } catch (Exception $e) {
            unset($epp_client);
            logActivity($e->getMessage(), $vars['userid']);
            return;
        }

        unset($epp_client);
        return;

    } catch (Exception $e) {
        unset($epp_client);
        logActivity('COZA/ContactUpdate: ' . $e->getMessage(), $vars['userid']);
        return;
    }
}
