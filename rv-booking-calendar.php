<?php
/*
Plugin Name: RV Gantt Booking Calendar
Description: A Gantt-style booking calendar for RV lots in the WordPress admin dashboard.
Version: 2.4.8
Author: Your Name
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

class RV_Gantt_Booking_Calendar
{
    public function __construct()
    {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX actions
        add_action('wp_ajax_rvbs_load_calendar', array($this, 'load_calendar_data'));



        //add new bookin for admin panel 
        add_action('wp_ajax_rvbs_add_booking', array($this, 'add_booking'));
        add_action('wp_ajax_nopriv_rvbs_add_booking', array($this, 'add_booking'));

        // add user search for admin panel
        add_action('wp_ajax_rvbs_user_search', array($this, 'rvbs_user_search_callback'));
        add_action('wp_ajax_nopriv_rvbs_user_search', array($this, 'rvbs_user_search_callback'));


        add_action('wp_ajax_rvbs_update_booking', array($this, 'update_booking'));
        add_action('wp_ajax_rvbs_cancel_booking', array($this, 'cancel_booking'));
        add_action('wp_ajax_rvbs_get_booking', array($this, 'get_booking'));
        add_action('wp_ajax_nopriv_rvbs_get_booking', array($this, 'get_booking'));

        // check availability for admin panel
        add_action('wp_ajax_rvbs_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_rvbs_check_availability_edit', array($this, 'check_availability_on_edit'));

        // get booked dates for admin panel the date is disabled in the calendar

        add_action('wp_ajax_rvbs_get_booked_dates', array($this, "get_booked_dates_callback"));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'RV Booking Calendar',
            'RV Calendar',
            'manage_options',
            'rv-booking-calendar',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );
        // ad submenu page for the booking calendar search and filter
        add_submenu_page(
            'rv-booking-calendar',
            'Search Bookings',
            'Search Bookings',
            'manage_options',
            'rv-booking-calendar-search',
            array($this, 'render_admin_page')
        );
    }

    // check unavailable dates for the booking



    public function get_booked_dates_callback()
    {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'rvbs_bookings';
        $lot_id = isset($_POST['lot_id']) ? intval($_POST['lot_id']) : 0;
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        if (!$lot_id) {
            wp_send_json_error('Invalid lot ID');
            wp_die();
        }

        // Query booked dates for the given lot_id
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT check_in, check_out FROM  $table_bookings WHERE post_id = %d  AND status != 'cancelled'",
                $lot_id,
            )
        );

        $disabled_dates = [];
        foreach ($bookings as $booking) {
            $check_in = new DateTime($booking->check_in);
            $check_out = new DateTime($booking->check_out);
            $interval = new DateInterval('P1D');
            $date_range = new DatePeriod($check_in, $interval, $check_out);

            foreach ($date_range as $date) {
                $disabled_dates[] = $date->format('Y-m-d');
            }
            // Include check_out date as it might be the last day of booking
            $disabled_dates[] = $check_out->format('Y-m-d');
        }

        $disabled_dates = array_unique($disabled_dates); // Remove duplicates
        wp_send_json_success(['disabled_dates' => $disabled_dates]);
        wp_die();
    }
    public function enqueue_scripts($hook)
    {

        if (!is_admin()) {
            return;
        }

        // Check the current admin page slug
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';


        if ($hook == 'toplevel_page_rv-booking-calendar') {



            // Enqueue styles
            wp_enqueue_style('rvbs-gantt-css', plugin_dir_url(__FILE__) . './assets/css/gantt-calendar.css', array(), '2.4.8');

            // Enqueue scripts
            wp_enqueue_script('rvbs-gantt-js', plugin_dir_url(__FILE__) . './assets/js/gantt-calendar.js', array('jquery'), '2.4.8', true);
            wp_localize_script('rvbs-gantt-js', 'rvbs_gantt', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rvbs_gantt_nonce'),
            ));



            // enqueue_flatpicker cdn 
            wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js', array('jquery'), '4.6.13', true);
            wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css', array(), '4.6.13');
        }

        if ($current_page == 'rv-booking-calendar-search') {
            // Enqueue styles
          
        }
    }

    // old logic workigin on this function
    // check  availability function on add new booking 
    public function check_availability()
    {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        $lot_id = intval($_POST['lot_id']);
        $check_in = sanitize_text_field($_POST['check_in']);
        $check_out = sanitize_text_field($_POST['check_out']);

        // Validate input
        if (!$lot_id || !$check_in || !$check_out) {
            wp_send_json_error('Invalid input data.');
        }

        // Check if lot exists and is available
        $table_lots = $wpdb->prefix . 'rvbs_rv_lots';
        $lot = $wpdb->get_row($wpdb->prepare(
            "SELECT post_id, is_available, is_trash, deleted_post 
             FROM $table_lots 
             WHERE post_id = %d AND is_available = 1 AND is_trash = 0 AND deleted_post = 0",
            $lot_id
        ));

        if (!$lot) {
            wp_send_json_error('Lot is not available or does not exist.');
        }

        // Check for conflicting bookings
        $table_bookings = $wpdb->prefix . 'rvbs_bookings';
        $conflict = $wpdb->get_var($wpdb->prepare(
            "
            SELECT COUNT(*) 
            FROM $table_bookings 
            WHERE post_id = %d 
            AND status IN ('pending', 'confirmed')
            AND (
                (%s BETWEEN check_in AND check_out)
                OR (%s BETWEEN check_in AND check_out)
                OR (check_in BETWEEN %s AND %s)
            )",
            $lot_id,
            $check_in,
            $check_out,
            $check_in,
            $check_out
        ));

        if ($conflict > 0) {
            wp_send_json_error('Selected dates are not available.');
        } else {
            wp_send_json_success('Dates are available.');
        }
    }

    // check availability function on edit booking
    public function check_availability_on_edit()
    {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        $response = [];

        $lot_id = intval($_POST['lot_id']);
        $check_in = sanitize_text_field($_POST['check_in']);
        $check_out = sanitize_text_field($_POST['check_out']);
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        // check onavailability date for the booking

        $table_bookings = $wpdb->prefix . 'rvbs_bookings';
        $table_lots = $wpdb->prefix . 'rvbs_rv_lots';

        // Sanitize input


        // Validate input
        if (!$lot_id) {
            wp_send_json_error(['message' => 'Missing lot ID']);
            return;
        }

        // Fetch booked date ranges for the lot
        $unavailable_dates_query = $wpdb->prepare(
            "
        SELECT DISTINCT rb.check_in, rb.check_out
        FROM $table_bookings rb
        INNER JOIN $table_lots rl ON rb.post_id = rl.post_id AND rb.lot_id = rl.id
        WHERE rb.post_id = %d
        AND rb.status IN ('pending', 'confirmed')
        AND rl.is_available = 1
        AND rl.is_trash = 0
        AND rl.status = 'confirmed'",
            $lot_id
        );

        $booked_ranges = $wpdb->get_results($unavailable_dates_query);
        $unavailable_dates = [];

        // Expand date ranges into individual dates
        foreach ($booked_ranges as $range) {
            $start = new DateTime($range->check_in);
            $end = new DateTime($range->check_out);
            $end->modify('+1 day'); // Include the checkout day as unavailable

            $interval = new DateInterval('P1D');
            $date_range = new DatePeriod($start, $interval, $end);

            foreach ($date_range as $date) {
                $unavailable_dates[] = $date->format('Y-m-d');
            }
        }

        // Remove duplicates and sort
        $unavailable_dates = array_unique($unavailable_dates);
        sort($unavailable_dates); // Optional: sort dates chronologically

        // Return the list of unavailable dates
        $response['unavailable_dates'] = $unavailable_dates;
        // check onavailability date for the booking

        // Optional buffer day between bookings (0 = no buffer, 1 = 1-day gap required)
        $buffer_days = 0;

        if (!$lot_id || !$check_in || !$check_out || !strtotime($check_in) || !strtotime($check_out)) {
            wp_send_json_error('Invalid input data.');
        }

        if (strtotime($check_in) >= strtotime($check_out)) {
            wp_send_json_error('Check-in date must be before check-out date.');
        }
        // chek if lot exists and is available
        $lot = $wpdb->get_row($wpdb->prepare(
            "SELECT post_id, is_available, is_trash, deleted_post 
         FROM {$wpdb->prefix}rvbs_rv_lots 
         WHERE post_id = %d AND is_available = 1 AND is_trash = 0 AND deleted_post = 0",
            $lot_id
        ));

        if (!$lot) {
            wp_send_json_error('Lot is not available or does not exist.');
        }

        // Check for conflicting bookings
        $original_booking = null;
        if ($booking_id) {
            $original_booking = $wpdb->get_row($wpdb->prepare(
                "SELECT check_in, check_out 
             FROM {$wpdb->prefix}rvbs_bookings 
             WHERE id = %d AND post_id = %d",
                $booking_id,
                $lot_id
            ));

            if (!$original_booking) {
                wp_send_json_error('Booking does not exist or is invalid.');
            }
        }

        $original_check_in = $original_booking ? $original_booking->check_in : null;
        $original_check_out = $original_booking ? $original_booking->check_out : null;

        // Check if the booking is an extension or shortening
        $is_extension = (
            $original_booking &&
            strtotime($check_in) == strtotime($original_check_in) &&
            strtotime($check_out) > strtotime($original_check_out)
        );

        $is_shortening = (
            $original_booking &&
            strtotime($check_in) >= strtotime($original_check_in) &&
            strtotime($check_out) <= strtotime($original_check_out)
        );

        // Adjust dates for buffer
        $adjusted_check_in = date('Y-m-d', strtotime($check_in) - ($buffer_days * 86400));
        $adjusted_check_out = date('Y-m-d', strtotime($check_out) + ($buffer_days * 86400));

        if ($is_extension) {
            $extended_start = $original_check_out;
            $extended_end = $check_out;

            $adjusted_start = date('Y-m-d', strtotime($extended_start) + ($buffer_days * 86400));
            $adjusted_end = date('Y-m-d', strtotime($extended_end) + ($buffer_days * 86400));

            $query = "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}rvbs_bookings 
            WHERE post_id = %d 
            AND status IN ('pending', 'confirmed')
            AND NOT (check_out <= %s OR check_in >= %s)
            AND id != %d
        ";
            $params = [$lot_id, $extended_start, $adjusted_end, $booking_id];
            $conflict = $wpdb->get_var($wpdb->prepare($query, ...$params));

            if ($conflict > 0) {
                wp_send_json_error('The extended booking period conflicts with other bookings.');
            }

            $extended_days = (strtotime($check_out) - strtotime($original_check_out)) / (60 * 60 * 24);
            $response['message'] = "Booking extended by {$extended_days} day(s), now until {$check_out}.";
            $response['booking_status'] = 'extended';

            wp_send_json_success($response);
        }

        // If shortening or moving dates
        if (!$is_shortening) {
            $query = "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}rvbs_bookings 
            WHERE post_id = %d 
            AND status IN ('pending', 'confirmed')
            AND NOT (check_out <= %s OR check_in >= %s)
            AND id != %d
        ";
            $params = [$lot_id, $adjusted_check_in, $adjusted_check_out, $booking_id];
            $conflict = $wpdb->get_var($wpdb->prepare($query, ...$params));

            if ($conflict > 0) {
                wp_send_json_error('Selected dates conflict with other bookings.');
            }
        }

        if ($is_shortening) {
            $response['message'] = "Booking shortened, now ends on {$check_out}.";
            $response['booking_status'] = 'shortened';
        } else {
            $response['message'] = "Booking updated to new date range: {$check_in} to {$check_out}.";
            $response['booking_status'] = 'extended';
        }

        wp_send_json_success($response);
    }


    // old logic workigin on this function


    public function render_admin_page()
    {
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

        // Normalize month and year
        while ($month > 12) {
            $month -= 12;
            $year++;
        }
        while ($month < 1) {
            $month += 12;
            $year--;
        }

        $date = new DateTime(sprintf("%d-%02d-01", $year, $month));
?>
        <div class="wrap">
            <h1>RV Gantt Booking Calendar</h1>

            <div class="rvbs-gantt">

                <div class="add_new_booking">
                    <button id="add-new-booking" class="button button-primary">Add New Booking</button>
                </div>
                <div class="calendar-navigation">
                    <button class="prev-month" data-month="<?php echo $date->modify('-1 month')->format('m'); ?>" data-year="<?php echo $date->format('Y'); ?>">Previous</button>
                    <span class="current-month"><?php echo $date->format('F Y'); ?></span>
                    <button class="next-month" data-month="<?php echo $date->modify('+1 month')->format('m'); ?>" data-year="<?php echo $date->format('Y'); ?>">Next</button>
                </div>

                <div class="calendar-container">
                    <!-- Calendar will be rendered by JavaScript -->
                </div>

                <!-- Booking Form Modal -->
                <div id="booking-modal" style="display:none;">
                    <div class="modal-content rvbs-modal-content">
                        <h2>Add New Booking</h2>
                        <form id="booking-form">
                            <label for="lot_id">RV Lot:</label>
                            <select name="lot_id" id="lot_id" required>
                                <option value="">Select Lot</option>
                                <?php
                                global $wpdb;
                                $lots = $wpdb->get_results("SELECT id, post_id FROM {$wpdb->prefix}rvbs_rv_lots WHERE is_trash = 0 AND deleted_post = 0");

                                foreach ($lots as $lot) :
                                    $post = get_post($lot->post_id);
                                    $price = get_post_meta($lot->post_id, '_rv_lots_price', true); // use the correct meta key
                                    if ($post) :
                                ?>
                                        <option value="<?php echo esc_attr($lot->post_id); ?>" data-price="<?php echo esc_attr($price); ?>">
                                            <?php echo esc_html($post->post_title); ?>
                                        </option>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </select>

                            <input type="hidden" name="booking_id" id="booking_id">
                            <label for="user_search">User:</label>
                            <input type="text" id="user_search" placeholder="Search by name or email..." autocomplete="off" required>
                            <input type="hidden" name="user_id" id="user_id">

                            <div id="user-search-results" style="border:1px solid #ccc; display:none;"></div>

                            <div id="new-user-fields" style="display:none;">
                                <p>No user found. Create a new user:</p>
                                <label>Name: <input type="text" id="new_user_name"></label>
                                <label>Email: <input type="email" id="new_user_email"></label>
                            </div>

                            <label for="check_in">Check-In Date:</label>
                            <input type="text" name="check_in" id="check_in" class="rvbs-date-picker" required>
                            <label for="check_out">Check-Out Date:</label>
                            <input type="text" name="check_out" id="check_out" class="rvbs-date-picker" required>
                            <label for="total_price">Total Price:</label>
                            <input type="number" step="0.01" name="total_price" id="total_price" readonly>

                            <!-- NEW Booking Status Field -->
                            <label for="booking_status">Booking Status:</label>
                            <select name="booking_status" id="booking_status" required>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Canceled</option>
                            </select>


                            <div id="availability-message"></div>
                            <button type="submit">Save Booking</button>
                            <button type="button" id="close-modal">Close</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
        </div>
<?php


    }

    public function render_calendar_grid($month, $year, $lot_id = '', $status = '')
    {
        global $wpdb;

        // Normalize month and year
        while ($month > 12) {
            $month -= 12;
            $year++;
        }
        while ($month < 1) {
            $month += 12;
            $year--;
        }

        $date = new DateTime(sprintf("%d-%02d-01", $year, $month));
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


        // Prepare data to return
        $lots_data = [];
        $serial = 1;
        foreach ($rv_lots as $lot) {
            $post = get_post($lot->post_id);
            if (!$post) continue;

            $lot_data = [
                'serial' => $serial++,
                'id' => $lot->id,
                'title' => esc_html($post->post_title),
                'is_available' => $lot->is_available,
                'bookings' => []
            ];

            // Add bookings for this lot
            if (isset($bookings_by_lot[$lot->id])) {
                foreach ($bookings_by_lot[$lot->id] as $booking) {

                    $check_in = new DateTime($booking->check_in);
                    $check_out = new DateTime($booking->check_out);
                    $month_start = new DateTime("$year-$month-01");
                    $month_end = new DateTime("$year-$month-$days_in_month");

                    // Calculate start_day and end_day within the current month
                    $start_day = $check_in >= $month_start ? (int)$check_in->format('j') : 1;
                    $end_day = $check_out <= $month_end ? (int)$check_out->format('j') : $days_in_month;

                    $lot_data['bookings'][] = [
                        'id' => $booking->id,
                        'user_id' => $booking->user_id,
                        'post_id' => $booking->post_id,
                        'lot_id' => $booking->lot_id,
                        'check_in' => $booking->check_in,
                        'check_out' => $booking->check_out,
                        'total_price' => number_format($booking->total_price, 2),
                        'status' => $booking->status,
                        'start_day' => $start_day,
                        'end_day' => $end_day,
                        'is_expired' => $check_out < $today
                    ];
                }
            }

            $lots_data[] = $lot_data;
        }
        // var_dump($lots_data  );

        // Prepare days data with today highlight
        $days_data = [];
        for ($day = 1; $day <= $days_in_month; $day++) {
            $current_date = new DateTime("$year-$month-$day");
            $is_today = $current_date->format('Y-m-d') === $today->format('Y-m-d');
            $days_data[] = [
                'day' => $day,
                'is_today' => $is_today,
                'is_expired' => $current_date < $today
            ];
        }

        return [
            'month' => $month,
            'year' => $year,
            'days_in_month' => $days_in_month,
            'lots' => $lots_data,
            'days' => $days_data
        ];
    }

    public function load_calendar_data()
    {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $lot_id = isset($_POST['lot_id']) ? intval($_POST['lot_id']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        // Normalize month and year
        while ($month > 12) {
            $month -= 12;
            $year++;
        }
        while ($month < 1) {
            $month += 12;
            $year--;
        }

        $calendar_data = $this->render_calendar_grid($month, $year, $lot_id, $status);

        wp_send_json_success([
            'calendar_data' => $calendar_data,
            'month_display' => date('F Y', strtotime("$year-$month-01")),
        ]);
    }

    public function add_booking()
    {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        // Sanitize and validate inputs
        $lot_id = intval($_POST['lot_id']);
        $user_id = intval($_POST['user_id']);
        $check_in = sanitize_text_field($_POST['check_in']);
        $check_out = sanitize_text_field($_POST['check_out']);
        $total_price = floatval($_POST['total_price']);
        $status = isset($_POST['status']) ?  sanitize_text_field($_POST['status'])  : 'pending';


        $post_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}rvbs_rv_lots WHERE post_id = %d", $lot_id));

        // Validate inputs
        if (!$lot_id) {
            wp_send_json_error('Lot ID is required.');
        }
        if (!$check_in || !DateTime::createFromFormat('Y-m-d', $check_in)) {
            wp_send_json_error('Valid check-in date is required.');
        }
        if (!$check_out || !DateTime::createFromFormat('Y-m-d', $check_out)) {
            wp_send_json_error('Valid check-out date is required.');
        }
        if ($check_out <= $check_in) {
            wp_send_json_error('Check-out date must be after check-in date.');
        }
        if (!$total_price) {
            wp_send_json_error('Total price is required.');
        }

        // Check lot existence and availability
        $table_lots = $wpdb->prefix . 'rvbs_rv_lots';
        $lot = $wpdb->get_row($wpdb->prepare(
            "SELECT id, post_id 
             FROM $table_lots 
             WHERE post_id = %d AND is_available = 1 AND is_trash = 0 AND deleted_post = 0",
            $lot_id
        ));
        // if lot not found or not available then return error
        if (!$lot) {
            wp_send_json_error('Lot is not available or does not exist.');
        }

        // Create new user if user_id == 0
        $user_email = '';
        if ($user_id == 0) {
            $new_user_name = sanitize_text_field($_POST['user_name']);
            $new_user_email = sanitize_email($_POST['user_email']);

            if (!$new_user_name) {
                wp_send_json_error('User name is required for new user.');
            }
            if (!$new_user_email || !is_email($new_user_email)) {
                wp_send_json_error('Valid email address is required for new user.');
            }

            // Check if email already exists
            if (email_exists($new_user_email)) {
                wp_send_json_error('Email address is already registered.');
            }

            $password = wp_generate_password(12, true);
            $user_id = wp_create_user($new_user_email, $password, $new_user_email);

            if (is_wp_error($user_id)) {
                wp_send_json_error('Error creating user: ' . $user_id->get_error_message());
            }

            // Update user display name
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $new_user_name,
                'role' => 'subscriber',
            ]);

            $user_email = $new_user_email;

            // Send welcome email with login details
            $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
            $login_url = wp_login_url();
            $dashboard_url = home_url('/user-dashboard');
            $welcome_message = sprintf(__('Welcome to %s!'), $site_name) . "\r\n\r\n";
            $welcome_message .= sprintf(__('Username: %s'), $new_user_email) . "\r\n";
            $welcome_message .= sprintf(__('Password: %s'), $password) . "\r\n\r\n";
            $welcome_message .= __('Log in here: ') . $login_url . "\r\n";
            $welcome_message .= __('View your bookings: ') . $dashboard_url . "\r\n\r\n";
            $welcome_message .= __('As a subscriber, you can manage your bookings from your dashboard.') . "\r\n";
            wp_mail($new_user_email, sprintf(__('[%s] Your Account Created'), $site_name), $welcome_message);

            // Notify admin
            $admin_message = sprintf(__('New user registration on %s:'), $site_name) . "\r\n\r\n";
            $admin_message .= sprintf(__('Username: %s'), $new_user_email) . "\r\n";
            $admin_message .= sprintf(__('Email: %s'), $new_user_email) . "\r\n";
            wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Registration'), $site_name), $admin_message);
        } else {
            $user = get_user_by('ID', $user_id);
            if ($user) {
                $user_email = $user->user_email;
            }
        }

        if (!$user_id || !$user_email) {
            wp_send_json_error('Valid user ID is required.');
        }

        // Check availability
        $table_bookings = $wpdb->prefix . 'rvbs_bookings';
        // Check availability
        // Check availability
        $conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_bookings 
             WHERE lot_id = %d AND (
                 (check_in <= %s AND check_out >= %s) OR 
                 (check_in <= %s AND check_out >= %s)
             )",
            $lot_id,
            $check_out,
            $check_in,
            $check_in,
            $check_out
        ));

        if ($conflict > 0) {
            wp_send_json_error('Selected dates are not available.');
        }

        // Check if any of the required fields are empty or invalid
        if (!$lot_id || !$user_id || !$check_in || !$check_out || !$total_price) {
            wp_send_json_error('Invalid input data.');
            // show whis input is not valid liike lot id user or anything

            //    check oneby one 

            if (!$lot_id) {
                wp_send_json_error('Lot ID is required.');
            }
            if (!$user_id) {
                wp_send_json_error('User ID is required.');
            }
            if (!$check_in) {
                wp_send_json_error('Check-in date is required.');
            }
            if (!$check_out) {
                wp_send_json_error('Check-out date is required.');
            }
            if (!$total_price) {
                wp_send_json_error('Total price is required.');
            }
            if (!in_array($status, ['pending', 'confirmed', 'cancelled'])) {
                wp_send_json_error('Invalid booking status.');
            }
        }

        // Insert booking
        $wpdb->insert(
            "{$wpdb->prefix}rvbs_bookings",
            array(
                'lot_id' => $post_id,
                'post_id' => $lot_id,
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
        }

        // Send booking confirmation email
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $booking_message = sprintf(__('Dear %s,'), $user_id == 0 ? sanitize_text_field($_POST['user_name']) : get_userdata($user_id)->display_name) . "\r\n\r\n";
        $booking_message .= __('Your booking has been successfully created!') . "\r\n\r\n";
        $booking_message .= __('Booking Details:') . "\r\n";
        $booking_message .= sprintf(__('Lot ID: %s'), $lot_id) . "\r\n";
        $booking_message .= sprintf(__('Check-In: %s'), $check_in) . "\r\n";
        $booking_message .= sprintf(__('Check-Out: %s'), $check_out) . "\r\n";
        $booking_message .= sprintf(__('Total Price: $%s'), number_format($total_price, 2)) . "\r\n";
        $booking_message .= sprintf(__('Status: %s'), ucfirst($status)) . "\r\n\r\n";
        $booking_message .= __('View your bookings: ') . home_url('/user-dashboard') . "\r\n\r\n";
        $booking_message .= sprintf(__('Thank you for choosing %s!'), $site_name) . "\r\n";
        wp_mail($user_email, sprintf(__('[%s] Booking Confirmation'), $site_name), $booking_message);

        wp_send_json_success('Booking added successfully');
    }



    public function rvbs_user_search_callback()
    {
        global $wpdb;

        $keyword = sanitize_text_field($_POST['keyword']);
        $users = get_users([
            'search'         => "*$keyword*",
            'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
            'number'         => 10
        ]);

        $results = [];

        foreach ($users as $user) {
            $results[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            ];
        }

        wp_send_json($results);
    }

    public function update_booking()
    {
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

    public function cancel_booking()
    {
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
    // Function to get booking details on click the booking block in the calendar
    public function get_booking()
    {
        check_ajax_referer('rvbs_gantt_nonce', 'nonce');
        global $wpdb;

        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $booking_post_id = isset($_POST['booking_post_id']) ? intval($_POST['booking_post_id']) : 0;

        if (!$booking_id && !$booking_post_id) {
            wp_send_json_error('Invalid booking ID');
        }

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rvbs_bookings WHERE id = %d AND post_id = %d",
            $booking_id,
            $booking_post_id
        ));

        if ($booking) {
            // Enrich with user data
            $user = get_userdata($booking->user_id);
            if ($user) {
                $booking->user_name  = $user->display_name;
                $booking->user_email = $user->user_email;
            }

            wp_send_json_success($booking);
        } else {
            wp_send_json_error('Booking not found');
        }
    }
}

// Initialize the plugin
new RV_Gantt_Booking_Calendar();
?>