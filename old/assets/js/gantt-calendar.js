jQuery(document).ready(function($) {

    
    // Navigation
    $('.prev-month, .next-month').on('click', function() {
        const month = $(this).data('month');
        const year = $(this).data('year');
        loadCalendar(month, year);
    });

    // Filters
    $('#rv-lot-filter, #status-filter').on('change', function() {
        const month = $('.prev-month').data('month') + 1;
        const year = $('.prev-month').data('year');
        loadCalendar(month, year);
    });

    // Edit booking
    $(document).on('click', '.booked', function() {
        const bookingId = $(this).data('booking-id');
        $.ajax({
            url: rvbs_gantt.ajax_url,
            type: 'POST',
            data: {
                action: 'rvbs_get_booking',
                booking_id: bookingId,
                nonce: rvbs_gantt.nonce
            },
            success: function(response) {
                if (response.success) {
                    const booking = response.data;
                    $('#booking_id').val(booking.id);
                    $('#lot_id').val(booking.lot_id);
                    $('#user_id').val(booking.user_id);
                    $('#check_in').val(booking.check_in);
                    $('#check_out').val(booking.check_out);
                    $('#total_price').val(booking.total_price);
                    $('#status').val(booking.status);
                    $('#booking-modal').show();
                } else {
                    alert('Error loading booking details');
                }
            },
            error: function() {
                alert('Error loading booking details');
            }
        });
    });

    // Close modal
    $('#close-modal').on('click', function() {
        $('#booking-modal').hide();
    });

    // Save booking
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();
        const bookingId = $('#booking_id').val();
        const action = bookingId ? 'rvbs_update_booking' : 'rvbs_add_booking';
        const data = {
            action: action,
            booking_id: bookingId,
            lot_id: $('#lot_id').val(),
            user_id: $('#user_id').val(),
            check_in: $('#check_in').val(),
            check_out: $('#check_out').val(),
            total_price: $('#total_price').val(),
            status: $('#status').val(),
            nonce: rvbs_gantt.nonce
        };

        $.ajax({
            url: rvbs_gantt.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    $('#booking-modal').hide();
                    const month = $('.prev-month').data('month') + 1;
                    const year = $('.prev-month').data('year');
                    loadCalendar(month, year);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('Error saving booking');
            }
        });
    });

    // Cancel booking
    $('#cancel-booking').on('click', function() {
        const bookingId = $('#booking_id').val();
        if (!bookingId) return;

        if (confirm('Are you sure you want to cancel this booking?')) {
            $.ajax({
                url: rvbs_gantt.ajax_url,
                type: 'POST',
                data: {
                    action: 'rvbs_cancel_booking',
                    booking_id: bookingId,
                    nonce: rvbs_gantt.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        $('#booking-modal').hide();
                        const month = $('.prev-month').data('month') + 1;
                        const year = $('.prev-month').data('year');
                        loadCalendar(month, year);
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('Error cancelling booking');
                }
            });
        }
    });

    function loadCalendar(month, year) {
        const lotId = $('#rv-lot-filter').val();
        const status = $('#status-filter').val();

        $.ajax({
            url: rvbs_gantt.ajax_url,
            type: 'POST',
            data: {
                action: 'rvbs_load_calendar',
                month: month,
                year: year,
                lot_id: lotId,
                status: status,
                nonce: rvbs_gantt.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.calendar-container').html(response.data.html);
                    $('.current-month').text(response.data.month);
                    $('.prev-month').data('month', month - 1).data('year', year);
                    $('.next-month').data('month', parseInt(month) + 1).data('year', year);
                } else {
                    alert('Error loading calendar');
                }
            },
            error: function() {
                alert('Error loading calendar');
            }
        });
    }
});