<?php if (strtolower(PHP_SAPI) !== 'cli') die('This file cannot be accessed directly');

/**
 * This file is part of the whmcs-registrars-coza library.
 *
 * (c) Gunter Grodotzki <gunter@afri.cc>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

// debug (should always be on on cron jobs to also catch up PHP-errors)
error_reporting(E_ALL);
ini_set('display_errors', true);

// so relative paths work
chdir(__DIR__);

// get system path
require 'config.php';
$whmcspath = realpath($whmcspath);

// required libs
require_once $whmcspath . '/init.php';
require_once $whmcspath . '/includes/registrarfunctions.php';
require_once $whmcspath . '/includes/classes/AfriCC/autoload.php';
require_once $whmcspath . '/modules/registrars/coza/Factory.php';

$params = getRegistrarConfigOptions('coza');

$epp_client = \COZA\Factory::build($params);

try {
    $epp_client->connect();

    while (true) {
        $poll = new \AfriCC\EPP\Frame\Command\Poll;
        $poll->request();

        $response = $epp_client->request($poll);
        unset($poll);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            echo date('Y-m-d H:i:s ') . 'did not receive a response' . PHP_EOL;
            exit(1);
        }

        // all ok but empty message queue
        if ($response->code() === 1300) {
            break;
        }

        if (!($response instanceof \AfriCC\EPP\Frame\Response\MessageQueue)) {
            unset($epp_client);
            echo date('Y-m-d H:i:s ') . 'did not receive a message-queue' . PHP_EOL;
            exit(1);
        }

        if (!$response->success()) {
            echo date('Y-m-d H:i:s ') . sprintf('unsuccessfull: %s (%d)', $response->message(), $response->code()) . PHP_EOL;
            break;
        }

        insert_query('mod_coza_addon_messages', [
            'created'   => $response->queueDate('Y-m-d H:i:s'),
            'code'      => $response->code(),
            'message'   => $response->queueMessage(),
        ]);

        // send ack
        $poll = new \AfriCC\EPP\Frame\Command\Poll;
        $poll->ack($response->queueId());

        $ack_response = $epp_client->request($poll);
        if (!($ack_response instanceof \AfriCC\EPP\Frame\Response) || !$ack_response->success()) {
            unset($epp_client);
            echo date('Y-m-d H:i:s ') . 'ack failed' . PHP_EOL;
            exit(1);
        }

        // stop on empty queue
        if ($response->queueCount() <= 1) {
            break;
        }

        // cleanup
        unset($ack_response, $response, $poll);
    }

    unset($epp_client);
    exit(0);

} catch(Exception $e) {
    unset($epp_client);
    echo date('Y-m-d H:i:s ') . $e->getMessage() . PHP_EOL;
    exit(1);
}
