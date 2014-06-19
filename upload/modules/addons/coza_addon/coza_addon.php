<?php if (!defined('WHMCS')) die('This file cannot be accessed directly');

/**
 * This file is part of the whmcs-registrars-coza library.
 *
 * (c) Gunter Grodotzki <gunter@afri.cc>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

function coza_addon_config()
{
    $config_array = [
        'name'          => 'CO.ZA EPP Messages',
        'description'   => 'This addon displays EPP poll messages.<br><a href="https://github.com/AfriCC/whmcs-registrars-coza">GitHub</a> | <a href="https://www.registry.net.za">Registry</a>',
        'author'        => '<a href="https://www.afri.cc">AfriCC</a>',
        'version'       => '0.1.1',
        'language'      => 'english',
        'fields'        => [],
    ];

    return $config_array;
}

function coza_addon_activate()
{
    $sql_structure = <<<'EOD'
CREATE TABLE IF NOT EXISTS `mod_coza_addon_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `code` smallint(4) unsigned NOT NULL,
  `message` text NOT NULL,
  PRIMARY KEY (`id`),
  INDEX (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mod_coza_contact_deletequeue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `next_due` datetime NOT NULL,
  `contact_handle` varchar(16) NOT NULL,
  `deleted` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  INDEX (`next_due`),
  INDEX (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
EOD;

    $queries = preg_split('/;/', $sql_structure, null, PREG_SPLIT_NO_EMPTY);
    foreach ($queries as $query) {
        $result = mysql_query(trim($query));

        if (!$result) {
            return ['status' => 'error', 'description' => sprintf('There was a problem activating the module: %s', mysql_error())];
        }
    }

    return ['status' => 'success', 'description' => 'Open module configuration for configuration options.'];
}

function coza_addon_deactivate()
{
    $sql_query = <<<'EOD'
DROP TABLE IF EXISTS `mod_coza_addon_messages`;
DROP TABLE IF EXISTS `mod_coza_contact_deletequeue`;
EOD;

    $queries = preg_split('/;/', $sql_query, null, PREG_SPLIT_NO_EMPTY);
    foreach ($queries as $query) {
        $result = mysql_query(trim($query));

        if (!$result) {
            return ['status' => 'error', 'description' => sprintf('There was an error deactivating the module: %s', mysql_error())];
        }
    }

    return ['status' => 'success', 'description' => 'Module has been deactivated.'];
}

function coza_addon_output($vars)
{
    $date_to = $date_from = '';

    if (empty($_POST['button']) || $_POST['button'] === 'Display All' || empty($_POST['date_from']) || empty($_POST['date_to'])) {
        $sql_where = '';
    } else {
        try {
            $date_to = new DateTime($_POST['date_to']);
            $date_to = $date_to->format('Y-m-d');

            $date_from = new DateTime($_POST['date_from']);
            $date_from = $date_from->format('Y-m-d');
        } catch (Exception $e) {

            $date_from = new DateTime;
            $date_from = $date_from->format('Y-m-d');

            $date_from->modify('+1 day');
            $date_to = $date_from->format('Y-m-d');
        }

        $sql_where = sprintf('WHERE DATE(`created`) >= DATE(\'%s\') AND DATE(`created`) <= DATE(\'%s\')', mysql_real_escape_string($date_from), mysql_real_escape_string($date_to));
    }

    $sql_order_by = 'ORDER BY `id` DESC';
    $sql_limit = 'LIMIT 100';
    $result = mysql_query(sprintf('SELECT * FROM `mod_coza_addon_messages` %s %s %s',
        $sql_where,
        $sql_order_by,
        $sql_limit
    ));

    echo '
        <script>
            $(function() {
                $( "#date_from" ).datepicker({
                    dateFormat: "yy-mm-dd",
                    constrainInput: true
                });
                $( "#date_to" ).datepicker({
                    dateFormat: "yy-mm-dd",
                    constrainInput: true
                });
            });
        </script>
    ';
    echo '<p>Select a start and end date and hit search.</p>';
    echo '<form action="' . $vars['modulelink'] . '" method="post">';
    echo '<input id="date_from" type="text" value="' . $date_from . '" name="date_from">';
    echo '<input id="date_to" type="text" value="' . $date_to . '" name="date_to">';
    echo '<input type="submit" name="button" value="Search">';
    echo '<input type="submit" name="button" value="Display All">';
    echo '<br><br>';

    if ($result && mysql_num_rows($result) > 0) {

        echo '<div class="tablebg">';
        echo '<table id="epp-message-log" class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Timestamp</th>';
        echo '<th>Code</th>';
        echo '<th>Message</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        while (($row = mysql_fetch_array($result))) {
            echo '<tr>';
            echo '<td>' . $row['created'] . '</td>';
            echo '<td>' . $row['code'] . '</td>';
            echo '<td>' . $row['message'] . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<p>No logs yet for selected period.</p>';
    }

    echo '</form>';
}
