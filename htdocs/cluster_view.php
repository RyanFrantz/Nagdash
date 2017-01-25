<?php
error_reporting(E_ALL);
require_once '../config.php';
require_once '../phplib/utils.php';
require_once '../phplib/timeago.php';
require_once '../phplib/NagiosApi.php';
require_once '../phplib/NagiosLivestatus.php';


if (!function_exists('curl_init')) {
  die("ERROR: The PHP curl extension must be installed for Nagdash to function");
}

$nagios_host_status = array(0 => "UP", 1 => "DOWN", 2 => "UNREACHABLE");
$nagios_service_status = array(0 => "OK", 1 => "WARNING", 2 => "CRITICAL", 3 => "UNKNOWN");
$nagios_host_status_colour = array(0 => "status_green", 1 => "status_red", 2 => "status_yellow");
$nagios_service_status_colour = array(0 => "status_green", 1 => "status_yellow", 2 => "status_red", 3 => "status_grey");
$up_arrow = '&#x2B06';
$down_arrow = '&#x2B07';

$nagios_toggle_status = array(0 => "disabled", 1 => "enabled");

$sort_by_time = ( isset($sort_by_time) && $sort_by_time ) ? true : false;

// Check to see if the user has a cookie disable some nagios instances
if (array_key_exists('nagdash_unwanted_hosts', $_COOKIE)) {
    $unwanted_hosts = unserialize($_COOKIE['nagdash_unwanted_hosts']);
} else {
    $unwanted_hosts = array();
}

if (!is_array($unwanted_hosts)) $unwanted_hosts = array();


// Check to see if the user has a cookie to filter out unrequired hosts
if (array_key_exists('nagdash_hostfilter', $_COOKIE)) {
    $cookie_filter = $_COOKIE['nagdash_hostfilter'];
}

if (!empty($cookie_filter)) {
    $filter = $cookie_filter;
}

// If the user wants to filter by last state change, grab the filter value.
// Else default to '0' (we won't filter by last state change).
// IDEA: Perhaps we create a $filter array tp pass to
// NagdashHelpers::parse_nagios_host_data() that contains the values of 'hostfilter',
// 'select_last_state_change', 'sort_by_time', and 'sort_descending'.
if (isset($_COOKIE['select_last_state_change'])) {
    $filter_select_last_state_change = (int) $_COOKIE['select_last_state_change'];
} else {
    $filter_select_last_state_change = 0;
}

// If 1, the user wants to sort by time; 0 if not.
if (isset($_COOKIE['sort_by_time'])) {
    $filter_sort_by_time = (int) $_COOKIE['sort_by_time'];
}

// If 1, the user wants to sort descending; 0 if not.
if (isset($_COOKIE['sort_descending'])) {
    $filter_sort_descending = (int) $_COOKIE['sort_descending'];
}

// Collect the API data from each Nagios host.

if (isset($mock_state_file)) {
    $data = json_decode(file_get_contents($mock_state_file), true);
    $state = $data['content'];
    $errors = [];
    $curl_stats = [];
    $api_cols = [];
} else {
    list($state, $api_cols, $errors, $curl_stats) = NagdashHelpers::get_nagios_host_data($upstream_nagios_hosts,
        $unwanted_hosts, $api_type);
}

// Sort the array alphabetically by cluster.
NagdashHelpers::deep_ksort($state);

// At this point, the data collection is completed.

if (count($errors) > 0) {
    foreach ($errors as $error) {
        echo "<div class='status_red'>{$error}</div>";
    }
}
list($host_summary, $service_summary, $down_hosts, $known_hosts, $known_services, $broken_services) = NagdashHelpers::parse_nagios_host_data($state, $filter, $api_cols, $filter_select_last_state_change);
?>

<?php
/*
 * TODO:
 * 1. Rewrite the HTML output so we no longer use <table>.
 * 2. Add support for 'long_plugin_output', 'notes', and 'action_url' fields.
 */
?>

<div id="info-window"><button class="close" onClick='$("#info-window").fadeOut("fast");'>&times;</button><div id="info-window-text"></div></div>
<div id="wrapper">
    <div id="header-bar">
        <div class="name">Name</div>
        <div class="state">State</div>
        <div class="duration">Duration</div>
        <div class="detail">Details</div>
    </div> <!-- End header -->
    <div id="content">
<?php
    foreach ($down_hosts as $host) {
        $host_status = strtolower($nagios_host_status[$host['host_state']]);
        $hostname = $host['hostname'];
            echo "<a onClick=\"toggle_visibility('{$hostname}_services');\">";
            echo "    <div class=\"cluster\" id=\"{$hostname}\">";
            echo "        <div class=\"name {$host_status}\">{$hostname}</div>";
            $host_state_display = strtoupper($host_status);
            if ($host['host_state'] == 0) {
                $host_state_display = $up_arrow . " {$host_state_display}";
            } else {
                $host_state_display = $down_arrow . " PROBLEM";
            }
            echo "        <div class=\"state {$host_status}\">{$host_state_display}</div>";
            echo "        <div class=\"duration {$host_status}\">{$host['duration']}</div>";
            echo "        <div class=\"detail {$host_status}\">{$host['detail']}</div>";
            echo "        <div class=\"services\" id=\"{$hostname}_services\">";
            foreach ($host['services'] as $service) {
                $service_status = strtolower($nagios_service_status[$service['service_state']]);
                $service_name = $service['service_name'];
                $service_state_display = strtoupper($service_status);
                if ($service['service_state'] == 0) {
                    $service_state_display = $up_arrow . " {$service_state_display}";
                } else {
                    $service_state_display = $down_arrow . " {$service_state_display}";
                }
                echo "            <a onClick=\"toggle_visibility('{$hostname}_{$service_name}_long_output');\">";
                echo "            <div class=\"service-row\" id=\"{$hostname}_{$service_name}\">";
                echo "                <div class=\"name {$service_status}\">{$service_name}</div>";
                echo "                <div class=\"state {$service_status}\">{$service_state_display}</div>";
                echo "                <div class=\"duration {$service_status}\">{$service['duration']}</div>";
                echo "                <div class=\"detail {$service_status}\">{$service['detail']}</div>";
                echo "                    <div class=\"service-long-output {$service_status}\" id=\"{$hostname}_{$service_name}_long_output\">";
                echo "                        Long output: '{$service['long_plugin_output']}'<br>";
                echo "                        Notes: '{$service['notes']}'<br>";
                echo "                        Runbook: <a href=\"{$service['action_url']}\" target=\"_blank\">{$service['action_url']}</a>";
                echo "                    </div>";
                echo "            </div>";
                echo "            </a>";
            }
            echo "         </div>";
            echo "    </div>";
            echo "</a>";
    }
?>
    </div> <!-- End content -->
</div> <!-- End wrapper -->

<?php
/*
    foreach ($down_hosts as $host) {
        echo "HOST_STATE: " . strtolower($nagios_host_status[$host['host_state']]) . "<br>";
        echo "<tr id='host_row' class='{$nagios_host_status_colour[$host['host_state']]}'>";
        $tag = NagdashHelpers::print_tag($host['tag'], count($upstream_nagios_hosts));
        //echo "<td>{$host['hostname']} " . $tag . " <span class='controls'>";
        echo "<td><a style='color:white;' onClick=\"toggle_visibility('{$host['hostname']}_services');\">{$host['hostname']}</a> " . $tag . " <span class='controls'>";
        NagdashHelpers::render('controls.php',[ "tag" => $host['tag'],
                                            "host" => $host['hostname'],
                                            "service" => '']);
        echo "</span></td>";
        echo "<td>{$nagios_host_status[$host['host_state']]}</td>";
        echo "<td>{$host['duration']}</td>";
        echo "<td class=\"desc\">{$host['detail']}</td>";
        foreach ($host['services'] as $service) {
            $tag = NagdashHelpers::print_tag($service['tag'], count($upstream_nagios_hosts));
            echo "{$nagios_service_status_colour[$service['service_state']]}'>" . $service['service_name']. $tag . " <span class='controls'>";
            NagdashHelpers::render('controls.php', ["tag" => $service['tag'],
                                                "host" => $service['hostname'],
                                                "service" => $service['service_name']]);
            echo "</span>";
        }
    }
*/
?>

<?php

echo "<!-- nagios-api server status: -->";
foreach ($curl_stats as $server => $server_stats) {
    echo "<!-- {$server_stats['url']} returned code {$server_stats['http_code']}, {$server_stats['size_download']} bytes ";
    echo "in {$server_stats['total_time']} seconds (first byte: {$server_stats['starttransfer_time']}). JSON parsed " . (isset($server_stats['objects']) ? $server_stats['objects'] : null) . " hosts -->\n";
}

?>
