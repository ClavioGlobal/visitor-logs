<?php

/*
Plugin Name: Visitor Logs
Description: Tracks the IP addresses of visitors and stores them in the WordPress database.
Version: 1.1
Author: Clavio Global
GitHub Plugin URI: https://github.com/ClavioGlobal/visitor-logs
GitHub Branch: main
*/

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

    // If IP doesn't exist, store it
    if (!$existing_ip) {
        $wpdb->insert(
            $table_name,
            array(
                'ip_address' => $ip_address,
                'visit_time' => current_time('mysql')
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

// Add an admin menu item to view the tracked IPs
add_action('admin_menu', 'visitor_logs_menu');

function visitor_logs_menu() {
    add_menu_page('Visitor Logs', 'Visitor Logs', 'manage_options', 'visitor-logs', 'visitor_logs_page');
}

// Display the IPs in the admin dashboard and add the Export to Excel button
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

    // Export to Excel Button
    echo '<form method="post" action="">';
    echo '<input type="submit" name="export_to_excel" class="button button-primary" value="Export to Excel">';
    echo '</form>';

    // Handle the Export button action
    if (isset($_POST['export_to_excel'])) {
        export_visitor_logs_to_excel();
    }

    echo '</div>';
}

// Function to export the logs to Excel
function export_visitor_logs_to_excel() {
    global $wpdb;

    // Manually include necessary PhpSpreadsheet classes
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Cell/Cell.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Cell/Coordinate.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Cell/DataType.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Worksheet/Worksheet.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Worksheet/Row.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Worksheet/Column.php';
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/PhpSpreadsheet/Worksheet/Dimension.php';

    // Use PhpSpreadsheet classes
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

    $table_name = $wpdb->prefix . 'visitor_logs';
    $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    // Create a new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column headers
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'IP Address');
    $sheet->setCellValue('C1', 'Visit Time');

    // Populate rows with data
    $row_num = 2;
    foreach ($results as $row) {
        $sheet->setCellValue('A' . $row_num, $row['id']);
        $sheet->setCellValue('B' . $row_num, $row['ip_address']);
        $sheet->setCellValue('C' . $row_num, $row['visit_time']);
        $row_num++;
    }

    // Set HTTP headers for file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="visitor_logs.xlsx"');

    // Write the Excel file and output it for download
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    exit; // Ensure no further processing
}
