jQuery(document).ready(function ($) {
    // Helper function for random color
    function getRandomColor() {
        const letters = "0123456789ABCDEF";
        let color = "#";
        for (let i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    }

    // Add filter form to calendar container
    const filterForm = `
        <div class="rvbs-filter-container" style="margin-bottom: 20px;">
            <h3>Filter Bookings</h3>
            <form id="rvbs-filter-form">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                    <div>
                        <label for="user_search">User Name:</label>
                        <input type="text" id="user_search" name="user_search" placeholder="Enter user name">
                    </div>
                    <div>
                        <label for="status_filter">Booking Status:</label>
                        <select id="status_filter" name="status_filter">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                        </select>
                    </div>
                    <div>
                        <label for="lot_name">Lot Name:</label>
                        <input type="text" id="lot_name" name="lot_name" placeholder="Enter lot name">
                    </div>
                    <div>
                        <label for="lot_id">Lot ID:</label>
                        <input type="number" id="lot_id" name="lot_id" placeholder="Enter lot ID" min="1">
                    </div>
                    <div>
                        <label for="lot_type">Lot Type:</label>
                        <select id="lot_type" name="lot_type">
                            <option value="">All</option>
                            <option value="standard">Standard</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                    <div>
                        <label for="lot_category">Lot Category:</label>
                        <select id="lot_category" name="lot_category">
                            <option value="">All</option>
                            <option value="waterfront">Waterfront</option>
                            <option value="forest">Forest</option>
                            <option value="meadow">Meadow</option>
                        </select>
                    </div>
                    <div>
                        <label for="lot_price_min">Price Range:</label>
                        <input type="number" id="lot_price_min" name="lot_price_min" placeholder="Min price" min="0" step="0.01">
                        <input type="number" id="lot_price_max" name="lot_price_max" placeholder="Max price" min="0" step="0.01">
                    </div>
                    <div>
                        <label for="lot_capacity">Max Capacity:</label>
                        <input type="number" id="lot_capacity" name="lot_capacity" placeholder="Max guests" min="1">
                    </div>
                    <div>
                        <label for="date_range">Date Range:</label>
                        <select id="date_range" name="date_range">
                            <option value="">All Dates</option>
                            <option value="past_30">Past 30 Days</option>
                            <option value="past_90">Past 90 Days</option>
                            <option value="past_year">Past 1 Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                        <div id="custom_date_range" style="display: none; margin-top: 10px;">
                            <input type="text" id="date_range_start" name="date_range_start" placeholder="Start date">
                            <input type="text" id="date_range_end" name="date_range_end" placeholder="End date">
                        </div>
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <button type="submit">Apply Filters</button>
                    <button type="button" id="reset_filters">Reset</button>
                </div>
            </form>
        </div>
    `;
    $('.calendar-container').before(filterForm);

    // Initialize Flatpickr for custom date range
    flatpickr('#date_range_start, #date_range_end', {
        dateFormat: 'Y-m-d',
        minDate: '2000-01-01',
        maxDate: '2030-12-31'
    });

    // Show/hide custom date range inputs
    $('#date_range').on('change', function () {
        if ($(this).val() === 'custom') {
            $('#custom_date_range').show();
        } else {
            $('#custom_date_range').hide();
            $('#date_range_start, #date_range_end').val('');
        }
    });

    // Load calendar with filters
    function loadCalendar(month, year) {
        const filters = {
            user_search: $('#user_search').val(),
            status: $('#status_filter').val(),
            lot_name: $('#lot_name').val(),
            lot_id: $('#lot_id').val(),
            lot_type: $('#lot_type').val(),
            lot_category: $('#lot_category').val(),
            lot_price_min: $('#lot_price_min').val(),
            lot_price_max: $('#lot_price_max').val(),
            lot_capacity: $('#lot_capacity').val(),
            date_range: $('#date_range').val(),
            date_range_start: $('#date_range_start').val(),
            date_range_end: $('#date_range_end').val()
        };

        $.ajax({
            url: rvbs_gantt.ajax_url,
            type: 'POST',
            data: {
                action: 'rvbs_load_calendar',
                month: month,
                year: year,
                filters: filters,
                nonce: rvbs_gantt.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderCalendarGrid(response.data.calendar_data);
                    $('.current-month').text(response.data.month_display);
                    $('.prev-month').data('month', month - 1).data('year', year);
                    $('.next-month').data('month', parseInt(month) + 1).data('year', year);
                    addCurrentDateOverlay();
                } else {
                    alert('Error loading calendar');
                }
            },
            error: function (xhr, status, error) {
                console.error('Load calendar error:', xhr, status, error);
                alert('Error loading calendar');
            }
        });
    }

    // Handle filter form submission
    $('#rvbs-filter-form').on('submit', function (e) {
        e.preventDefault();
        const month = $('.prev-month').data('month') + 1 || new Date().getMonth() + 1;
        const year = $('.prev-month').data('year') || new Date().getFullYear();
        loadCalendar(month, year);
    });

    // Reset filters
    $('#reset_filters').on('click', function () {
        $('#rvbs-filter-form')[0].reset();
        $('#custom_date_range').hide();
        const month = new Date().getMonth() + 1;
        const year = new Date().getFullYear();
        loadCalendar(month, year);
    });

    // Render calendar grid (unchanged except for status filter)
    function renderCalendarGrid(data) {
        const $container = $('.calendar-container');
        $container.empty();

        const $gridContainer = $('<div class="rvbs-gantt-grid"></div>')
            .attr('data-current-month', data.month)
            .attr('data-current-year', data.year)
            .attr('data-days-in-month', data.days_in_month);

        const $headerRow = $('<div class="rvbs-gantt-row rvbs-gantt-header"></div>');
        $headerRow.append('<div class="rvbs-gantt-cell header-cell serial-no">Serial No</div>');
        $headerRow.append('<div class="rvbs-gantt-cell header-cell rv-lot-title">RV Lot Title</div>');
        $headerRow.append('<div class="rvbs-gantt-cell header-cell status">Status</div>');

        data.days.forEach((day) => {
            const $dayHeader = $('<div class="rvbs-gantt-cell header-cell day-header"></div>')
                .attr('data-day', day.day)
                .text(day.day);
            if (day.is_today) {
                $dayHeader.addClass('today');
            }
            $headerRow.append($dayHeader);
        });

        $gridContainer.append($headerRow);

        data.lots.forEach((lot) => {
            const $row = $('<div class="rvbs-gantt-row"></div>');
            $row.append(`<div class="rvbs-gantt-cell serial-no">${lot.serial}</div>`);
            $row.append(`<div class="rvbs-gantt-cell rv-lot-title">${lot.title}</div>`);

            const $statusCell = $('<div class="rvbs-gantt-cell status"></div>');
            const $statusSelect = $('<select class="lot-status"></select>').attr('data-lot-id', lot.id);
            $statusSelect.append(`<option value="available" ${lot.is_available ? 'selected' : ''}>Available</option>`);
            $statusSelect.append(`<option value="unavailable" ${!lot.is_available ? 'selected' : ''}>Unavailable</option>`);
            $statusCell.append($statusSelect);
            $row.append($statusCell);

            for (let day = 1; day <= data.days_in_month; day++) {
                const dayData = data.days[day - 1];
                const $dayCell = $('<div class="rvbs-gantt-cell day-cell"></div>')
                    .attr('data-day', day)
                    .attr('data-lot-id', lot.id);

                if (!lot.is_available) {
                    const $unavailable = $('<div class="unavailable"></div>').text('Unavailable');
                    $dayCell.append($unavailable);
                    $row.append($dayCell);
                    continue;
                }

                const bookingsForDay = lot.bookings.filter((booking) => {
                    return (
                        day >= booking.start_day &&
                        day <= booking.end_day &&
                        ['pending', 'confirmed'].includes(booking.status)
                    );
                });

                bookingsForDay.forEach((booking) => {
                    if (day === booking.start_day) {
                        const today = new Date();
                        const todayDate = today.getDate();
                        const todayMonth = today.getMonth() + 1;
                        const todayYear = today.getFullYear();
                        const checkOutDate = new Date(booking.check_out);
                        const isExpiredToday =
                            checkOutDate.getDate() === todayDate &&
                            checkOutDate.getMonth() + 1 === todayMonth &&
                            checkOutDate.getFullYear() === todayYear;

                        const bookingInfo = `Lot Title: ${lot.title}\nUser ID: ${booking.user_id}\nCheck-in: ${booking.check_in}\nCheck-out: ${booking.check_out}\nTotal Price: $${booking.total_price}\nStatus: ${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}`;

                        const isCheckIn =
                            new Date(booking.check_in).getDate() === day &&
                            new Date(booking.check_in).getMonth() + 1 === data.month &&
                            new Date(booking.check_in).getFullYear() === data.year
                                ? ' check-in'
                                : '';
                        const isCheckOut =
                            new Date(booking.check_out).getDate() === day &&
                            new Date(booking.check_out).getMonth() + 1 === data.month &&
                            new Date(booking.check_out).getFullYear() === data.year
                                ? ' check-out'
                                : '';
                        const isExpiredClass = booking.is_expired ? ' expired' : '';

                        const bookingColor = booking.is_expired ? '#d3d3d3' : getRandomColor();

                        const remainingStartDay = booking.start_day;
                        if (remainingStartDay <= booking.end_day) {
                            setTimeout(() => {
                                let cell_width = $dayCell.outerWidth();
                                const remainingDays = booking.end_day - booking.start_day + 1;
                                const remainingWidth = remainingDays * cell_width - 2;
                                const remainingLeft = (remainingStartDay - booking.start_day) * cell_width;
                                const $bookingDiv = $(`<div class="booked${isCheckIn}${isCheckOut}${isExpiredClass}"></div>`)
                                    .attr('data-booking-id', booking.id)
                                    .attr('data-booking-post-id', booking.post_id)
                                    .attr('data-check-in', booking.check_in)
                                    .attr('data-check-out', booking.check_out)
                                    .attr('data-start-day', booking.start_day)
                                    .attr('data-end-day', booking.end_day)
                                    .css('width', `${remainingWidth}px`)
                                    .css('left', `${remainingLeft}px`)
                                    .attr('data-title', bookingInfo);
                                $bookingDiv.css('background-color', bookingColor);
                                $bookingDiv.append(`<span class="booking-dates">${booking.check_in} to ${booking.check_out}</span>`);
                                $dayCell.append($bookingDiv);
                            }, 1);
                        }
                    }
                });

                $row.append($dayCell);
            }

            $gridContainer.append($row);
        });

        $container.append($gridContainer);
    }

    // Tooltip handling
    let $tooltip = null;

    $(document).on('mouseenter', '.booked', function (e) {
        if (!$tooltip) {
            $tooltip = $('<div class="rvbs-tooltip"></div>').appendTo('.rvbs-gantt');
        }

        const bookingInfo = $(this).attr('data-title').split('\n');
        const lotTitle = bookingInfo[0].replace('Lot Title: ', '');
        const userId = bookingInfo[1].replace('User ID: ', '');
        const checkIn = bookingInfo[2].replace('Check-in: ', '');
        const checkOut = bookingInfo[3].replace('Check-out: ', '');
        const totalPrice = bookingInfo[4].replace('Total Price: $', '');
        const status = bookingInfo[5].replace('Status: ', '');

        $tooltip.html(`
            <p><strong>Lot Title:</strong> ${lotTitle}</p>
            <p><strong>User ID:</strong> ${userId}</p>
            <p><strong>Check-in:</strong> ${checkIn}</p>
            <p><strong>Check-out:</strong> ${checkOut}</p>
            <p><strong>Total Price:</strong> $${totalPrice}</p>
            <p><strong>Status:</strong> ${status}</p>
        `);

        const offsetX = 10;
        const offsetY = 10;
        let left = e.clientX + offsetX;
        let top = e.clientY + offsetY;

        const tooltipWidth = $tooltip.outerWidth();
        const tooltipHeight = $tooltip.outerHeight();
        const windowWidth = $(window).width();
        const windowHeight = $(window).height();

        if (left + tooltipWidth > windowWidth) {
            left = e.clientX - tooltipWidth - offsetX;
        }
        if (top + tooltipHeight > windowHeight) {
            top = e.clientY - tooltipHeight - offsetY;
        }

        $tooltip.css({
            left: left + 'px',
            top: top + 'px',
            visibility: 'visible'
        });
    });

    $(document).on('mousemove', '.booked', function (e) {
        if ($tooltip) {
            let left = e.clientX + 10;
            let top = e.clientY + 10;

            const tooltipWidth = $tooltip.outerWidth();
            const tooltipHeight = $tooltip.outerHeight();
            const windowWidth = $(window).width();
            const windowHeight = $(window).height();

            if (left + tooltipWidth > windowWidth) {
                left = e.clientX - tooltipWidth - 10;
            }
            if (top + tooltipHeight > windowHeight) {
                top = e.clientY - tooltipHeight - 10;
            }

            $tooltip.css({
                left: left + 'px',
                top: top + 'px'
            });
        }
    });

    $(document).on('mouseleave', '.booked', function () {
        if ($tooltip) {
            $tooltip.css('visibility', 'hidden');
        }
    });

    // Placeholder for addCurrentDateOverlay (from previous code)
    function addCurrentDateOverlay() {
        $('.current-date-overlay').remove();
        // Implement overlay logic if needed
    }

    // Initial calendar load
    const month = new Date().getMonth() + 1;
    const year = new Date().getFullYear();
    loadCalendar(month, year);
});