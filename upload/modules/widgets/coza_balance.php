<?php if (!defined('WHMCS')) die('This file cannot be accessed directly');

/**
 * This file is part of the whmcs-registrars-coza library.
 *
 * (c) Gunter Grodotzki <gunter@afri.cc>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

add_hook('AdminHomeWidgets', 1, 'widget_coza_balance');

function widget_coza_balance($vars)
{
    require_once ROOTDIR . '/includes/registrarfunctions.php';
    require_once ROOTDIR . '/includes/classes/AfriCC/autoload.php';
    require_once ROOTDIR . '/modules/registrars/coza/Factory.php';

    $params = getRegistrarConfigOptions('coza');

    $title   = 'CO.ZA Balance';
    $content = '<p align ="center" class="textblack"><strong>%s</strong></p>';

    $epp_client = \COZA\Factory::build($params);

    try {
        $epp_client->connect();

        $frame = new \AfriCC\EPP\Extension\COZA\Info\CozaContact;
        $frame->setId(((!empty($params['OTE']) && $params['OTE'] === 'on') ? $params['TestUsername'] : $params['Username']));
        $frame->requestBalance();

        $response = $epp_client->request($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            return ['title' => $title, 'content' => sprintf($content, 'ERROR: no response')];
        }

        if (!$response->success()) {
            unset($epp_client);
            return ['title' => $title, 'content' => sprintf($content, sprintf('ERROR (%d): %s', $response->code(), $response->message()))];
        }

        $data = $response->data();
        if (empty($data) || !is_array($data) || empty($data['infData']['balance'])) {
            unset($epp_client);
            return ['title' => $title, 'content' => sprintf($content, 'ERROR: empty response')];
        }

        unset($epp_client);
        return ['title' => $title, 'content' => sprintf($content, 'Current registrar balance is R ' . $data['infData']['balance'])];

    } catch(Exception $e) {
        unset($epp_client);
        return ['title' => $title, 'content' => sprintf($content, 'ERROR: ' . $e->getMessage())];
    }
}
