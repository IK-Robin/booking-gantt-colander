<?php
if (!defined('ABSPATH')) {
    exit;
}

// // Function to create the rvbs_rv_lots table
// function rvbs_create_rv_lot_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'rvbs_rv_lots';
//     $charset_collate = $wpdb->get_charset_collate();

//     // Check if the table already exists
//     $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
//     if ($table_exists === $table_name) {
//         return; // Table already exists, no need to create it again
//     }

//     // SQL query to create the table
//     $sql = "CREATE TABLE $table_name (
//         id INT NOT NULL AUTO_INCREMENT,
//         post_id INT NOT NULL,
//         user_id INT NULL,
//         start_date DATE NULL,
//         end_date DATE NULL,
//         is_available TINYINT(1) NOT NULL DEFAULT 1,
//         status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
//         is_trash TINYINT(1) NOT NULL DEFAULT 0,
//         deleted_post TINYINT(1) NOT NULL DEFAULT 0,
//         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//         PRIMARY KEY (id),
//         KEY post_id (post_id)
//     ) $charset_collate;";

//     // Include the upgrade.php file for dbDelta()
//     require_once ABSPATH . 'wp-admin/includes/upgrade.php';

//     // Execute the SQL query
//     dbDelta($sql);

//     // Check for errors
//     if (!empty($wpdb->last_error)) {
//         error_log('Error creating RV bookings table: ' . $wpdb->last_error);
//     } else {
//         error_log('RV bookings table created successfully.');
//     }

//     // Add foreign key constraint separately
//     $foreign_key_sql = "ALTER TABLE $table_name
//         ADD CONSTRAINT fk_post_id
//         FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}posts(ID)
//         ON DELETE CASCADE;";

//     $wpdb->query($foreign_key_sql);

//     if (!empty($wpdb->last_error)) {
//         error_log('Error adding foreign key constraint to rv_lots: ' . $wpdb->last_error);
//     } else {
//         error_log('Foreign key constraint added to rv_lots successfully.');
//     }
// }

// Function to create the rvbs_bookings table
// function rvbs_create_rv_lot_bookings_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'rvbs_bookings';
//     $charset_collate = $wpdb->get_charset_collate();

//     // Check if the table already exists
//     $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
//     if ($table_exists === $table_name) {
//         return; // Table already exists
//     }

//     // SQL query to create the booking table
//     $sql = "CREATE TABLE $table_name (
//         id INT NOT NULL AUTO_INCREMENT,
//         lot_id INT NOT NULL,
//         post_id INT NOT NULL,
//         user_id INT NULL,
//         check_in DATE NOT NULL,
//         check_out DATE NOT NULL,
//         total_price DECIMAL(10,2) NOT NULL,
//         status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
//         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//         PRIMARY KEY (id),
//         KEY lot_id (lot_id),
//         KEY post_id (post_id)
//     ) $charset_collate;";

//     require_once ABSPATH . 'wp-admin/includes/upgrade.php';
//     dbDelta($sql);

//     if (!empty($wpdb->last_error)) {
//         error_log('Error creating RV lot bookings table: ' . $wpdb->last_error);
//     } else {
//         error_log('RV lot bookings table created successfully.');
//     }

//     // Add foreign key constraints separately
//     $foreign_key_sql = "ALTER TABLE $table_name
//         ADD CONSTRAINT fk_lot_id FOREIGN KEY (lot_id) REFERENCES {$wpdb->prefix}rvbs_rv_lots(id) ON DELETE CASCADE,
//         ADD CONSTRAINT fk_post_id FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE;";

//     $wpdb->query($foreign_key_sql);

//     if (!empty($wpdb->last_error)) {
//         error_log('Error adding foreign key constraints to bookings: ' . $wpdb->last_error);
//     } else {
//         error_log('Foreign key constraints added to bookings successfully.');
//     }
// }

// Function to create the rvbs_booking_counts table
// function rvbs_create_booking_counts_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'rvbs_booking_counts';
//     $charset_collate = $wpdb->get_charset_collate();

//     // Check if the table already exists
//     $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
//     if ($table_exists === $table_name) {
//         return; // Table already exists
//     }

//     // SQL query to create the booking counts table
//     $sql = "CREATE TABLE $table_name (
//         id INT NOT NULL AUTO_INCREMENT,
//         user_id INT NULL,
//         lot_id INT NOT NULL,
//         post_id INT NOT NULL,
//         booking_count INT NOT NULL DEFAULT 0,
//         last_booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//         PRIMARY KEY (id),
//         UNIQUE KEY user_lot_post (user_id, lot_id, post_id),
//         KEY user_id (user_id),
//         KEY lot_id (lot_id),
//         KEY post_id (post_id)
//     ) $charset_collate;";

//     require_once ABSPATH . 'wp-admin/includes/upgrade.php';
//     dbDelta($sql);

//     if (!empty($wpdb->last_error)) {
//         error_log('Error creating RV lot booking counts table: ' . $wpdb->last_error);
//     } else {
//         error_log('RV lot booking counts table created successfully.');
//     }

//     // Add foreign key constraints separately
//     $foreign_key_sql = "ALTER TABLE $table_name
//         ADD CONSTRAINT fk_counts_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
//         ADD CONSTRAINT fk_counts_lot_id FOREIGN KEY (lot_id) REFERENCES {$wpdb->prefix}rvbs_rv_lots(id) ON DELETE CASCADE,
//         ADD CONSTRAINT fk_counts_post_id FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE;";

//     $wpdb->query($foreign_key_sql);

//     if (!empty($wpdb->last_error)) {
//         error_log('Error adding foreign key constraints to booking_counts: ' . $wpdb->last_error);
//     } else {
//         error_log('Foreign key constraints added to booking_counts successfully.');
//     }
// }

// Function to create all tables in the correct order
// function rvbs_create_rv_lot_tables() {
//     // Ensure tables are created in dependency order
//     rvbs_create_rv_lot_table();         // First, since bookings and counts depend on it
//     rvbs_create_rv_lot_bookings_table(); // Second, depends on rv_lots
//     rvbs_create_booking_counts_table();  // Third, depends on rv_lots
// }