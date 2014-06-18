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

$result = mysql_query('SELECT * FROM `mod_coza_contact_deletequeue` WHERE `deleted` = 0 AND `next_due` < NOW() LIMIT 10');
if ($result === false || mysql_num_rows($result) === 0) {
    unset($result);
    exit(0);
}

$params = getRegistrarConfigOptions('coza');

$epp_client = \COZA\Factory::build($params);

try {
    $epp_client->connect();

    while (($row = mysql_fetch_assoc($result))) {
        $frame = new \AfriCC\EPP\Frame\Command\Delete\Contact;
        $frame->setId($row['contact_handle']);

        $response = $epp_client->request($frame);

        if (!($response instanceof \AfriCC\EPP\Frame\Response)) {
            unset($epp_client);
            echo date('Y-m-d H:i:s ') . 'unable to get response' . PHP_EOL;
            exit(1);
        }

        if (!$response->success()) {
            echo date('Y-m-d H:i:s ') . $response->code() . ' - ' . $response->message() . PHP_EOL;
            update_query('mod_coza_contact_deletequeue', [
                'next_due' => date('Y-m-d H:i:s', strtotime('+6 day')),
            ], [
                'id' => (int) $row['id'],
            ]);
        } else {
            update_query('mod_coza_contact_deletequeue', [
                'deleted' => 1,
            ], [
                'id' => (int) $row['id'],
            ]);
        }

        unset($response, $frame);
    }

    unset($epp_client);
    exit(0);

} catch(Exception $e) {
    unset($epp_client);
    echo date('Y-m-d H:i:s ') . $e->getMessage() . PHP_EOL;
    exit(1);
}
