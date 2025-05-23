<?php
/*

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
    }

    public function enqueue_scripts($hook)
    {
        if ($hook !== 'toplevel_page_rv-booking-calendar') {
            return;
        }

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
                            <div id="availability-message"></div>
                            <button type="submit" >Save Booking</button>
                            <button type="button" id="close-modal">Close</button>
                        </form>
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

        $lot_id = intval($_POST['lot_id']);
        $user_id = intval($_POST['user_id']);
        $check_in = sanitize_text_field($_POST['check_in']);
        $check_out = sanitize_text_field($_POST['check_out']);
        $total_price = floatval($_POST['total_price']);
        $status = sanitize_text_field($_POST['status']);
        $post_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}rvbs_rv_lots WHERE post_id = %d", $lot_id));

        // Check availability
        $conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rvbs_bookings 
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

        // check all inpute file is valid 

        // create the user if not exist 
        

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
            return;
           
        }

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
        } else {
            wp_send_json_success('Booking added successfully');
        }
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

    public function get_booking()
    {
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