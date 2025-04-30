<?php
/*
Plugin Name: RV Gantt Booking Calendar
Description: A Gantt-style booking calendar for RV lots in the WordPress admin dashboard.
Version: 2.4.5
Author: Your Name
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

class RV_Gantt_Booking_Calendar {
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX actions
        add_action('wp_ajax_rvbs_load_calendar', array($this, 'load_calendar_data'));
        add_action('wp_ajax_rvbs_add_booking', array($this, 'add_booking'));
        add_action('wp_ajax_rvbs_update_booking', array($this, 'update_booking'));
        add_action('wp_ajax_rvbs_cancel_booking', array($this, 'cancel_booking'));
        add_action('wp_ajax_rvbs_get_booking', array($this, 'get_booking'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'RV Booking Calendar',
            'RV Calendar',
            'manage_options',
            'rv-booking-calendar',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_rv-booking-calendar') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style('rvbs-gantt-css', plugin_dir_url(__FILE__) . 'assets/css/gantt-calendar.css', array(), '2.4.5');

        // Enqueue scripts
        wp_enqueue_script('rvbs-gantt-js', plugin_dir_url(__FILE__) . 'assets/js/gantt-calendar.js', array('jquery'), '2.4.5', true);
        wp_localize_script('rvbs-gantt-js', 'rvbs_gantt', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rvbs_gantt_nonce'),
        ));
    }

    public function render_admin_page() {
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $date = new DateTime("$year-$month-01");
        ?>
        <div class="wrap">
            <h1>RV Gantt Booking Calendar</h1>

            <div class="rvbs-gantt">
                <div class="calendar-navigation">
                    <button class="prev-month" data-month="<?php echo $date->modify('-1 month')->format('m'); ?>" data-year="<?php echo $date->format('Y'); ?>">Previous</button>
                    <span class="current-month"><?php echo $date->format('F Y'); ?></span>
                    <button class="next-month" data-month="<?php echo $date->modify('+1 month')->format('m'); ?>" data-year="<?php echo $date->format('Y'); ?>">Next</button>
                </div>

                <div class="calendar-container">
                    <?php $this->render_calendar_grid($month, $year); ?>
                </div>

                <!-- Booking Form Modal -->
                <div id="booking-modal" style="display:none;">
                    <div class="modal-content">
                        <h2>Add/Edit Booking</h2>
                        <form id="booking-form">
                            <input type="hidden" name="booking_id" id="booking_id">
                            <input type="hidden" name="lot_id" id="lot_id">
                            <label for="user_id">User ID:</label>
                            <input type="number" name="user_id" id="user_id" required>
                            <label for="check_in">Check-In Date:</label>
                            <input type="date" name="check_in" id="check_in" required>
                            <label for="check_out">Check-Out Date:</label>
                            <input type="date" name="check_out" id="check_out" required>
                            <label for="total_price">Total Price:</label>
                            <input type="number" step="0.01" name="total_price" id="total_price" required>
                            <label for="status">Status:</label>
                            <select name="status" id="status">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <button type="submit">Save Booking</button>
                            <button type="button" id="cancel-booking">Cancel Booking</button>
                            <button type="button" id="close-modal">Close</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_calendar_grid($month, $year, $lot_id = '', $status = '') {
        global $wpdb;
        $date = new DateTime("$year-$month-01");
        $days_in_month = $date->format('t');
        $today = new DateTime(); // Current date for expired check

        // Fetch RV lots
        $query = "SELECT * FROM {$wpdb->prefix}rvbs_rv_lots WHERE is_trash = 0 AND deleted_post = 0";
        if ($lot_id) {
            $query .= $wpdb->prepare(" AND id = %d", $lot_id);
        }
        $rv_lots = $wpdb->get_results($query);

        // Fetch bookings for the month
        $bookings_query = $wpdb->prepare(
            "SELECT b.*, l.post_id FROM {$wpdb->prefix}rvbs_bookings b
            JOIN {$wpdb->prefix}rvbs_rv_lots l ON b.lot_id = l.id
            WHERE (b.check_in <= %s AND b.check_out >= %s)",
            "$year-$month-$days_in_month",
            "$year-$month-01"
        );
        if ($status) {
            $bookings_query .= $wpdb->prepare(" AND b.status = %s", $status);
        }
        if ($lot_id) {
            $bookings_query .= $wpdb->prepare(" AND b.lot_id = %d", $lot_id);
        }
        $bookings = $wpdb->get_results($bookings_query);

        // Prepare bookings array for easy lookup
        $bookings_by_lot = array();
        foreach ($bookings as $booking) {
            $bookings_by_lot[$booking->lot_id][] = $booking;
        }
    

        ?>
        <table class="rvbs-gantt-table">
            <thead>
                <tr>
                    <th>Serial No</th>
                    <th>RV Lot Title</th>
                    <th>Status</th>
                    <?php for ($day = 1; $day <= $days_in_month; $day++) : ?>
                        <!-- // check the current date and add a class to highlite the current date -->
                        <?php $current_date = new DateTime("$year-$month-$day"); ?>
                        <?php 
                        
                        $is_today = $current_date->format('Y-m-d') === $today->format('Y-m-d') ? 'today' : '';
                        
                        // var_dump($current_date); // Debugging line to check the value of $is_today
                        
                        
                        ?>
              

                        <th><?php echo $day; ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $serial = 1;
                foreach ($rv_lots as $lot) :
                    $post = get_post($lot->post_id);
                    if (!$post) continue;
                    ?>
                    <tr>
                        <td><?php echo $serial++; ?></td>
                        <td><?php echo esc_html($post->post_title); ?></td>
                        <td>
                            <select class="lot-status" data-lot-id="<?php echo $lot->id; ?>">
                                <option value="available" <?php selected($lot->is_available, 1); ?>>Available</option>
                                <option value="unavailable" <?php selected($lot->is_available, 0); ?>>Unavailable</option>
                            </select>
                        </td>
                        <?php for ($day = 1; $day <= $days_in_month; $day++) : ?>
                            <?php
                            $current_date = new DateTime("$year-$month-$day");
                            $current_date_str = $current_date->format('Y-m-d');
                            $is_expired = $current_date < $today ? ' expired' : '';
                            ?>
                            <td class="day-cell" data-day="<?php echo $day; ?>" data-lot-id="<?php echo $lot->id; ?>">
                                <?php
                                // Check if lot is unavailable
                                if (!$lot->is_available) {
                                    // echo '<div class="unavailable">expire</div>';
                                    continue;
                                }

                                // Check if this lot is booked on this day
                                $booking = null;
                                $booking_id = 0;
                                if (isset($bookings_by_lot[$lot->id])) {
                                    foreach ($bookings_by_lot[$lot->id] as $b) {
                                        $check_in = new DateTime($b->check_in);
                                        $check_out = new DateTime($b->check_out);
                                        if ($current_date >= $check_in && $current_date <= $check_out) {
                                            $booking = $b;
                                            $booking_id = $b->id;
                                            break;
                                        }
                                    }
                                }

                                if ($booking) {
                                    $check_in = new DateTime($booking->check_in);
                                    $check_out = new DateTime($booking->check_out);
                                    $start_day = ($check_in->format('Y-m') === "$year-$month") ? $check_in->format('j') : 1;
                                    $is_expired = $check_out < $today ? ' expired' : '';

                                    if ($day == $start_day) {
                                        $interval = $check_in->diff($check_out);
                                        $duration = $interval->days + 1;
                                        $end_day = ($check_out->format('Y-m') === "$year-$month") ? $check_out->format('j') : $days_in_month;
                                        $width = ($end_day - $start_day + 1) * 100; // Total width of the booking block (each cell is 100px wide)

                                        $booking_info = "User ID: {$booking->user_id}\nCheck-in: {$booking->check_in}\nCheck-out: {$booking->check_out}\nTotal Price: $" . number_format($booking->total_price, 2) . "\nStatus: " . ucfirst($booking->status);
                                        $status_class = "status-{$booking->status}";
                                        $is_check_in = $current_date == $check_in ? ' check-in' : '';
                                        $is_check_out = $current_date == $check_out ? ' check-out' : '';

                                        // Calculate previous period (from check-in to current date)
                                        $previous_period_start = $check_in;
                                        $previous_period_end = $check_in; // Default to check-in date
                                        $previous_period_width = 0;

                                        if ($today <= $check_out) { // Only calculate previous period if the booking hasn't fully expired
                                            if ($today >= $check_in && $today <= $check_out) {
                                                // Current date is within the booking: previous period is from check-in to today
                                                $previous_period_start = $check_in;
                                                $previous_period_end = $today;
                                            }
                                            // If today < check_in, previous period is 0 (no gray portion)

                                            // Calculate the number of days for the previous period within the current month
                                            $previous_start_day = ($previous_period_start->format('Y-m') === "$year-$month") ? $previous_period_start->format('j') : 1;
                                            $previous_end_day = ($previous_period_end->format('Y-m') === "$year-$month") ? $previous_period_end->format('j') : $days_in_month;
                                            if ($previous_start_day <= $end_day && $previous_end_day >= $start_day) {
                                                $previous_period_days = min($previous_end_day, $end_day) - max($previous_start_day, $start_day) + 1;
                                                if ($previous_period_days > 0) {
                                                    $previous_period_width = $previous_period_days * 100; // Width of previous period
                                                }
                                            }
                                        }

                                        // Render the previous period (gray, if applicable)
                                        // if ($previous_period_width > 0) {
                                        //     echo "<div class='booked previous-period $status_class$is_expired' data-booking-id='$booking_id' style='width: {$previous_period_width}px; left: 0;' title='$booking_info'>";
                                        //     echo "<span class='booking-dates'>{$booking->check_in} to {$booking->check_out}</span>";
                                        //     echo "</div>";
                                        // }

                                        // Render the remaining booking period (from after previous period to check-out)
                                        $remaining_start = max($previous_period_end, $check_in);
                                        $remaining_start_day = ($remaining_start->format('Y-m') === "$year-$month") ? $remaining_start->format('j') : 1;
                                        if ($remaining_start_day <= $end_day) {
                                            $remaining_days = $end_day - $remaining_start_day + 1;
                                            $remaining_width = $remaining_days * 100;
                                            $remaining_left = ($remaining_start_day - $start_day) * 100;
                                            echo "<div class='booked $status_class$is_check_in$is_check_out$is_expired' data-booking-id='$booking_id' style='width: {$remaining_width}px; left: {$remaining_left}px;' title='$booking_info'>";
                                            echo "<span class='booking-dates'>{$booking->check_in} to {$booking->check_out}</span>";
                                            echo "</div>";
                                        }
                                    }
                                }
                                ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function load_calendar_data() {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $lot_id = isset($_POST['lot_id']) ? intval($_POST['lot_id']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        ob_start();
        $this->render_calendar_grid($month, $year, $lot_id, $status);
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'month' => date('F Y', strtotime("$year-$month-01")),
        ));
    }

    public function add_booking() {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        $lot_id = intval($_POST['lot_id']);
        $user_id = intval($_POST['user_id']);
        $check_in = sanitize_text_field($_POST['check_in']);
        $check_out = sanitize_text_field($_POST['check_out']);
        $total_price = floatval($_POST['total_price']);
        $status = sanitize_text_field($_POST['status']);
        $post_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}rvbs_rv_lots WHERE id = %d", $lot_id));

        $wpdb->insert(
            "{$wpdb->prefix}rvbs_bookings",
            array(
                'lot_id' => $lot_id,
                'post_id' => $post_id,
                'user_id' => $user_id,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'total_price' => $total_price,
                'status' => $status,
            ),
            array('%d', '%d', '%d', '%s', '%s', '%f', '%s')
        );

        if ($wpdb->last_error) {
            wp_send_json_error('Error adding booking: ' . $wpdb->last_error);
        } else {
            wp_send_json_success('Booking added successfully');
        }
    }

    public function update_booking() {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        $booking_id = intval($_POST['booking_id']);
        $user_id = intval($_POST['user_id']);
        $check_in = sanitize_text_field($_POST['check_in']);
        $check_out = sanitize_text_field($_POST['check_out']);
        $total_price = floatval($_POST['total_price']);
        $status = sanitize_text_field($_POST['status']);

        $wpdb->update(
            "{$wpdb->prefix}rvbs_bookings",
            array(
                'user_id' => $user_id,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'total_price' => $total_price,
                'status' => $status,
            ),
            array('id' => $booking_id),
            array('%d', '%s', '%s', '%f', '%s'),
            array('%d')
        );

        if ($wpdb->last_error) {
            wp_send_json_error('Error updating booking: ' . $wpdb->last_error);
        } else {
            wp_send_json_success('Booking updated successfully');
        }
    }

    public function cancel_booking() {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        $booking_id = intval($_POST['booking_id']);
        $wpdb->update(
            "{$wpdb->prefix}rvbs_bookings",
            array('status' => 'cancelled'),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );

        if ($wpdb->last_error) {
            wp_send_json_error('Error cancelling booking: ' . $wpdb->last_error);
        } else {
            wp_send_json_success('Booking cancelled successfully');
        }
    }

    public function get_booking() {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        if (!$booking_id) {
            wp_send_json_error('Invalid booking ID');
        }

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rvbs_bookings WHERE id = %d",
            $booking_id
        ));

        if ($booking) {
            wp_send_json_success($booking);
        } else {
            wp_send_json_error('Booking not found');
        }
    }
}

// Initialize the plugin
new RV_Gantt_Booking_Calendar();
?>