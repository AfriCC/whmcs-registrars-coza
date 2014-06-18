<?php

/**
 * This file is part of the whmcs-registrars-coza library.
 *
 * (c) Gunter Grodotzki <gunter@afri.cc>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace COZA;

use Exception;

if (!defined('WHMCS')) die('This file cannot be accessed directly');

class Factory
{
    private static $client;

    /**
     * connect to EPP server (OTE if requested)
     * @param array $params
     * @return \AfriCC\EPP\Client
     */
    public static function build(array $params)
    {
        $epp_client = new \AfriCC\EPP\Client([
            'host'       => ((!empty($params['OTE']) && $params['OTE'] === 'on') ? 'regphase3.dnservices.co.za' : 'epp.coza.net.za'),
            'port'       => 3121,
            'username'   => self::getRegistrarId($params),
            'password'   => ((!empty($params['OTE']) && $params['OTE'] === 'on') ? $params['TestPassword'] : $params['TestPassword']),
            'services'   => [
                'urn:ietf:params:xml:ns:domain-1.0',
                'urn:ietf:params:xml:ns:contact-1.0'
            ],
            'ssl'        => true,
            'local_cert' => ((!empty($params['OTE']) && $params['OTE'] === 'on') ? null : $params['Certificate']),
        ]);

        self::$client = $epp_client;
        return $epp_client;
    }

    /**
     * create contact if it does not exist yet in EPP
     * @param \AfriCC\EPP\Client $epp_client
     * @param string $client_id
     * @param array $params
     * @throws Exception
     * @return boolean
     */
    public static function createContactIfNotExists(\AfriCC\EPP\Client $epp_client, $client_id, array $params)
    {
        // probe contact-id
        $frame = new \AfriCC\EPP\Frame\Command\Info\Contact;
        $frame->setId($client_id);

        $response = $epp_client->request($frame);
        unset($frame);
        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            throw new Exception('COZA/ContactInfo: unable to get response');
        }

        // contact does not exist
        if ($response->code() === 2303) {
            $frame = new \AfriCC\EPP\Frame\Command\Create\Contact;
            $frame->setId($client_id);
            $frame->setName($params['fullname']);
            $frame->setOrganization($params['companyname']);
            $frame->addStreet($params['address1']);
            if (!empty($params['address2'])) {
                $frame->addStreet($params['address2']);
            }
            $frame->setCity($params['city']);
            $frame->setPostalCode($params['postcode']);
            $frame->setCountryCode($params['countrycode']);
            $frame->setVoice($params['phonenumberformatted']);
            $frame->setEmail($params['email']);
            $frame->setAuthInfo();

            $cre_response = $epp_client->request($frame);
            unset($frame);
            if (!($cre_response instanceof \AfriCC\EPP\Frame\Response)) {
                throw new Exception('COZA/ContactCreate: unable to get response');
            }

            if (!$cre_response->success()) {
                throw new Exception(sprintf('COZA/ContactCreate: %s (%d)', $cre_response->message(), $cre_response->code()));
            }
        }

        return true;
    }

    /**
     * update a contact if it exists
     * @param \AfriCC\EPP\Client $epp_client
     * @param int $contact_id
     * @param array $params
     */
    public static function updateContactIfExists(\AfriCC\EPP\Client $epp_client, $contact_id, array $params)
    {
        // probe contact-id
        $frame = new \AfriCC\EPP\Frame\Command\Info\Contact;
        $frame->setId($contact_id);

        $response = $epp_client->request($frame);
        unset($frame);
        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            throw new Exception('COZA/ContactInfo: unable to get response');
        }

        // contact does not exist, so skip
        if ($response->code() === 2303) {
            return;
        }

        $frame = new \AfriCC\EPP\Frame\Command\Update\Contact;
        $frame->setId($contact_id);

        $frame->changeName($params['fullname']);
        $frame->changeOrganization($params['companyname']);
        $frame->changeAddStreet($params['address1']);
        $frame->changeAddStreet($params['address2']);
        $frame->changeCity($params['city']);
        $frame->changePostalCode($params['postcode']);
        $frame->changeCountryCode($params['countrycode']);
        $frame->changeVoice($params['phonenumberformatted']);
        $frame->changeEmail($params['email']);

        $upd_response = $epp_client->request($frame);
        unset($frame);
        if (!($upd_response instanceof \AfriCC\EPP\Frame\Response)) {
            throw new Exception('COZA/ContactUpdate: unable to get response');
        }

        if (!$upd_response->success()) {
            throw new Exception(sprintf('COZA/ContactUpdate: %s (%d)', $upd_response->message(), $upd_response->code()));
        }

        return true;
    }

    /**
     * get domain name out of params-array, e.g. replace "co.za" with test-zone
     * if OTE activated
     * @param array $params
     * @return string
     */
    public static function getDomain(array $params)
    {
        return sprintf('%s.%s', $params['sld'], ((!empty($params['OTE']) && $params['OTE'] === 'on') ? 'test.dnservices.co.za' : $params['tld']));
    }

    /**
     * get the current registrar id
     * @param array $params
     * @return string
     */
    public static function getRegistrarId(array $params)
    {
        if (!empty($params['OTE']) && $params['OTE'] === 'on') {
            return $params['TestUsername'];
        } else {
            return $params['Username'];
        }
    }

    /**
     * returns a consistent contact handle
     * @param array $params
     * @param int $user_id
     * @param int $contact_id
     */
    public static function getContactHandle(array $params, $user_id, $contact_id = 0)
    {
        $handle = '';

        if (!empty($params['ContactPrefix'])) {
            $handle .= $params['ContactPrefix'];
        }

        if ($contact_id === 0) {
            $handle .= 'U' . $user_id;
        } else {
            $handle .= 'C' . $contact_id;
        }

        return mb_strtoupper($handle, 'UTF-8');
    }

    /**
     * resolves the registry contact handle into user-id + contact-id
     * @param array $params
     * @param string $handle
     */
    public static function resolveContactHandle(array $params, $handle)
    {
        $prefix = '';
        if (!empty($params['ContactPrefix'])) {
            $prefix = preg_quote($params['ContactPrefix'], '/');
        }

        if (preg_match('/^' . $prefix . '(u|c)([0-9]+)$/i', $handle, $matches)) {
            if (strtoupper($matches[1]) === 'U') {
                return ['user_id' => (int) $matches[2], 'contact_id' => 0];
            } else {

                // get userid via DB
                $result = select_query('tblcontacts', 'userid', ['id' => (int) $matches[2]]);
                if ($result === false || mysql_num_rows($result) !== 1) {
                    unset($result);
                    return false;
                }
                $data = mysql_fetch_array($result);

                return ['user_id' => (int) $data['userid'], 'contact_id' => (int) $matches[2]];
            }
        }

        return false;
    }
}
