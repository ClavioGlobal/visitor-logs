<?php
/*
Plugin Name: Visitor Logs
Description: Tracks the IP addresses of visitors and stores them in the WordPress database.
Version: 1.3
Author: Clavio Global
GitHub Plugin URI: https://github.com/ClavioGlobal/visitor-logs
GitHub Branch: main
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Function to create the database table on plugin activation
function vl_create_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'visitor_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        visit_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Function to log visitor IP address
function vl_log_visitor() {
    global $wpdb;

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $table_name = $wpdb->prefix . 'visitor_logs';

    // Insert the IP address into the database
    $wpdb->insert($table_name, array('ip_address' => $ip_address));
}

// Function to display logs in admin area
function vl_display_logs() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'visitor_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY visit_time DESC");

    echo '<div class="wrap">';
    echo '<h2>Visitor Logs</h2>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>ID</th><th>IP Address</th><th>Visit Time</th></tr></thead>';
    echo '<tbody>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . esc_html($log->id) . '</td>';
        echo '<td>' . esc_html($log->ip_address) . '</td>';
        echo '<td>' . esc_html($log->visit_time) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '<a href="' . admin_url('admin.php?page=export-logs') . '" class="button">Export to Excel</a>';
    echo '</div>';
}

// Function to export logs to Excel
function vl_export_to_excel() {
    global $wpdb;

    // Require PhpSpreadsheet classes
    require_once plugin_dir_path(__FILE__) . 'vendor/PhpSpreadsheet/src/Bootstrap.php';

    $table_name = $wpdb->prefix . 'visitor_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table_name");

    // Create new Spreadsheet object
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column headers
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'IP Address');
    $sheet->setCellValue('C1', 'Visit Time');

    // Add data to the sheet
    $row = 2; // Start from the second row
    foreach ($logs as $log) {
        $sheet->setCellValue('A' . $row, $log->id);
        $sheet->setCellValue('B' . $row, $log->ip_address);
        $sheet->setCellValue('C' . $row, $log->visit_time);
        $row++;
    }

    // Set headers for the download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="visitor_logs.xlsx"');
    header('Cache-Control: max-age=0');

    // Write the file to output
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Hook the activation function
register_activation_hook(__FILE__, 'vl_create_table');

// Hook the logging function to 'init'
add_action('init', 'vl_log_visitor');

// Create an admin menu for the plugin
function vl_add_admin_menu() {
    add_menu_page('Visitor Logs', 'Visitor Logs', 'manage_options', 'visitor-logs', 'vl_display_logs');
    add_submenu_page('visitor-logs', 'Export Logs', 'Export to Excel', 'manage_options', 'export-logs', 'vl_export_to_excel');
}
add_action('admin_menu', 'vl_add_admin_menu');
