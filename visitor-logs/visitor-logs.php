<?php
/*
Plugin Name: Visitor Logs
Description: Tracks the IP addresses of visitors and stores them in the WordPress database.
Version: 1.4
Author: Clavio Global
GitHub Plugin URI: https://github.com/ClavioGlobal/visitor-logs
GitHub Branch: main
*/

// Set the timezone to IST (Indian Standard Time)
date_default_timezone_set('Asia/Kolkata');

// Hook to record IP when the site is visited
add_action('wp', 'track_visitor_ip');

function track_visitor_ip() {
    global $wpdb;

    // Get visitor IP
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Table name (uses the WordPress table prefix)
    $table_name = $wpdb->prefix . 'visitor_logs';

    // Check if the IP is already stored in the database
    $existing_ip = $wpdb->get_var($wpdb->prepare("SELECT ip_address FROM $table_name WHERE ip_address = %s", $ip_address));

    // If IP doesn't exist, store it with current IST time
    if (!$existing_ip) {
        $wpdb->insert(
            $table_name,
            array(
                'ip_address' => $ip_address,
                'visit_time' => current_time('mysql', 0) // Store the current time in IST
            )
        );
    }
}

// Function to create a database table when the plugin is activated
function create_visitor_logs_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'visitor_logs'; // Use WordPress table prefix for compatibility
    $charset_collate = $wpdb->get_charset_collate();

    // SQL to create the table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ip_address varchar(55) NOT NULL,
        visit_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Use the WordPress function dbDelta to safely create the table
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Hook to run the function when the plugin is activated
register_activation_hook(__FILE__, 'create_visitor_logs_table');

// Add an admin menu item to view the tracked IPs, export them, and clear logs
add_action('admin_menu', 'visitor_logs_menu');

function visitor_logs_menu() {
    add_menu_page('Visitor Logs', 'Visitor Logs', 'manage_options', 'visitor-logs', 'visitor_logs_page');
    add_submenu_page('visitor-logs', 'Export IPs', 'Export IPs', 'manage_options', 'export-ips', 'export_ips_page');
    add_submenu_page('visitor-logs', 'Clear Logs', 'Clear Logs', 'manage_options', 'clear-logs', 'clear_logs_page');
}

// Display the IPs in the admin dashboard
function visitor_logs_page() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'visitor_logs';
    $results = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap">';
    echo '<h2>Tracked Visitor IP Addresses</h2>';
    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr><th>ID</th><th>IP Address</th><th>Visit Time</th></tr></thead>';
    echo '<tbody>';

    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row->id) . '</td>';
        echo '<td>' . esc_html($row->ip_address) . '</td>';
        echo '<td>' . esc_html($row->visit_time) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// Function to export IPs to a text file without clearing the logs
function export_ips_page() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'visitor_logs';
    $results = $wpdb->get_results("SELECT ip_address FROM $table_name");

    if ($results) {
        // Create a file to export IPs
        $file = fopen(plugin_dir_path(__FILE__) . 'exported_ips.txt', 'w');

        foreach ($results as $row) {
            fwrite($file, $row->ip_address . PHP_EOL);
        }

        fclose($file);

        echo '<div class="notice notice-success"><p>IPs exported successfully. <a href="' . plugin_dir_url(__FILE__) . 'exported_ips.txt" download>Download the file</a></p></div>';
    } else {
        echo '<div class="notice notice-warning"><p>No IPs to export.</p></div>';
    }
}

// Function to handle clearing logs
function clear_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_logs';

    if (isset($_POST['confirm_clear']) && $_POST['confirm_clear'] == 'confirm') {
        // Backup the current logs before deletion
        $logs_backup = $wpdb->get_results("SELECT * FROM $table_name");

        if ($logs_backup) {
            update_option('visitor_logs_backup', $logs_backup); // Save logs as an option

            // Clear the logs
            $wpdb->query("TRUNCATE TABLE $table_name");

            echo '<div class="notice notice-success"><p>Logs cleared successfully. <a href="?page=clear-logs&undo=1">Undo</a></p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>No logs found to clear.</p></div>';
        }
    }

    if (isset($_GET['undo'])) {
        // Restore logs from the backup
        $logs_backup = get_option('visitor_logs_backup');
        if ($logs_backup) {
            foreach ($logs_backup as $log) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'ip_address' => $log->ip_address,
                        'visit_time' => $log->visit_time
                    )
                );
            }
            delete_option('visitor_logs_backup'); // Remove backup after restoring

            echo '<div class="notice notice-success"><p>Logs restored successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>No backup found to restore.</p></div>';
        }
    }

    // Confirmation form for clearing logs
    echo '<div class="wrap">';
    echo '<h2>Clear Visitor Logs</h2>';
    echo '<form method="post" action="">';
    echo '<p>Type "confirm" below to clear all visitor logs.</p>';
    echo '<input type="text" name="confirm_clear" />';
    echo '<input type="submit" value="Clear Logs" class="button button-primary" />';
    echo '</form>';
    echo '</div>';
}

// Add GitHub plugin update support
add_filter('pre_set_site_transient_update_plugins', 'github_plugin_update');

function github_plugin_update($transient) {
    // Get plugin details from GitHub
    if (empty($transient->checked)) {
        return $transient;
    }

    $response = wp_remote_get('https://api.github.com/repos/ClavioGlobal/visitor-logs/releases/latest');
    if (is_wp_error($response)) {
        return $transient;
    }

    $response_body = json_decode(wp_remote_retrieve_body($response));
    $latest_version = $response_body->tag_name;

    // Compare versions and update if necessary
    if (version_compare($latest_version, '1.4', '>')) {
        $plugin_slug = plugin_basename(__FILE__);
        $transient->response[$plugin_slug] = (object) [
            'slug' => $plugin_slug,
            'new_version' => $latest_version,
            'url' => 'https://github.com/ClavioGlobal/visitor-logs',
            'package' => $response_body->zipball_url,
        ];
    }

    return $transient;
}
?>
