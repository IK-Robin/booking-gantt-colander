jQuery(document).ready(function ($) {
  // Helper function to generate random color
  function getRandomColor() {
    const letters = "0123456789ABCDEF";
    let color = "#";
    for (let i = 0; i < 6; i++) {
      color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
  }

  // Initialize Flatpickr
  flatpickr(".rvbs-date-picker", {
    dateFormat: "Y-m-d",
    minDate: "today",
    onChange: function (selectedDates, dateStr, instance) {
      updateTotalPrice();
      checkAvailability();
    },
  });

  // Open modal
  $("#add-new-booking").on("click", function () {
    $("#booking-form").trigger("reset");
    $("#lot_id").val("");
    $("#lot_price").val("");
    $("#total_price").val("");
    $("#availability-message").empty();
    $("label[for='total_price']").text("Total Price: $0.00");
    // $("#booking-form button[type='submit']").prop("disabled", true);
    $("#booking-modal").show();
  });

  // Close modal
  $("#close-modal").on("click", function () {
    $("#booking-modal").hide();
  });

  // Update total price
  function updateTotalPrice() {
    const $form = $("#booking-form");
    const lotId = $form.find("#lot_id").val();
    const checkIn = $form.find("#check_in").val();
    const checkOut = $form.find("#check_out").val();

    if (lotId) {
      const price = $form.find(`#lot_id option[value="${lotId}"]`).data("price");
      $form.find("#lot_price").val(price ? price.toFixed(2) : "");

      if (checkIn && checkOut) {
        const nights = Math.ceil(
          (new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24)
        );
        const totalPrice = price * nights;
        $form.find("#total_price").val(totalPrice.toFixed(2));
        $("label[for='total_price']").text(`Total Price: $${totalPrice.toFixed(2)}`);
      } else {
        $form.find("#total_price").val("");
        $("label[for='total_price']").text("Total Price: $0.00");
      }
    } else {
      $form.find("#lot_price").val("");
      $form.find("#total_price").val("");
      $("label[for='total_price']").text("Total Price: $0.00");
    }
  }

  // Check availability
  function checkAvailability() {
    const $form = $("#booking-form");
    const lotId = $form.find("#lot_id").val();
    const checkIn = $form.find("#check_in").val();
    const checkOut = $form.find("#check_out").val();
    const price = $form.find(`#lot_id option[value="${lotId}"]`).data("price");
    const nights = Math.ceil( 
      (new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24)
    );
    if (lotId && checkIn && checkOut) {
      $.ajax({
        url: rvbs_gantt.ajax_url,
        type: "POST",
        data: {
          action: "rvbs_check_availability",
          lot_id: lotId,
          check_in: checkIn,
          check_out: checkOut,
          nonce: rvbs_gantt.nonce,
        },
        success: function (response) {
          const $message = $("#availability-message");
          const $submit = $form.find('button[type="submit"]');
          if (response.success) {
            $message.html('<span style="color:green;">Available</span>');
            $submit.prop("disabled", false);
            $("label[for='total_price']").text(
              `Total Price : $${price} * ${nights}  `
            );

          } else {
            $message.html('<span style="color:red;">' + response.data + "</span>");
            // add the total price to the message label
           
            $("label[for='total_price']").text(
              `Total Price : $${price} * ${nights}  `
            );

            // $submit.prop("disabled", true);
          }
        },
        error: function (xhr, status, error) {
          console.log("Availability error:", xhr, status, error);
          $("#availability-message").html(
            '<span style="color:red;">Error checking availability</span>'
          );
          // $form.find('button[type="submit"]').prop("disabled", true);
        },
      });
    } else {
      $("#availability-message").empty();
      // $form.find('button[type="submit"]').prop("disabled", true);
      // check if any lotid is not selected and show a message under the input field
      if (!lotId) {
        $("#lot_id").addClass("error");
        $("#lot_id").next(".error-message").remove();
        $("#lot_id").after('<span class="error-message" style="color:red;">Please select a lot</span>');
      } else {
        $("#lot_id").removeClass("error");
        $("#lot_id").next(".error-message").remove();
      }
      if (!checkIn) {
        $("#check_in").addClass("error");
        $("#check_in").next(".error-message").remove();
        $("#check_in").after('<span class="error-message" style="color:red;">Please select a check-in date</span>');
      } else {
        $("#check_in").removeClass("error");
        $("#check_in").next(".error-message").remove();
      }
    }
  }

  // Handle lot selection
  $("#lot_id").on("change", function () {
    const $form = $("#booking-form");
    const lotId = $(this).val();
    if (lotId) {
      const price = $form.find(`#lot_id option[value="${lotId}"]`).data("price");
      $form.find("#lot_price").val(price ? price.toFixed(2) : "");
    } else {
      $form.find("#lot_price").val("");
    }
    updateTotalPrice();
    checkAvailability();
  });


// here i want to hide the allerts message under the input fields when the user click on the input field and remove the error class from the input field
  $(".rvbs-date-picker").on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });
  $("#lot_id").on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });
  $("#user_id").on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });
  $("#check_in").on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });
  $("#check_out").on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });
  $("#total_price").on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });
  $("#status").on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });


  // Save booking or submit a new booking
  $("#booking-form").on("submit", function (e) {
    e.preventDefault();
    const bookingId = $("#booking_id").val();
    const action = bookingId ? "rvbs_update_booking" : "rvbs_add_booking";
    const data = {
      action: action,
      booking_id: bookingId,
      lot_id: $("#lot_id").val(),
      user_id: $("#user_id").val(),
      check_in: $("#check_in").val(),
      check_out: $("#check_out").val(),
      total_price: $("#total_price").val(),
      status: $("#status").val() || "pending",
      nonce: rvbs_gantt.nonce,
    };

// check all required fields are filled and show a message if the input fild is empty and the message should be under the input field
    
    const requiredFields = ["#lot_id", "#user_id", "#check_in", "#check_out", ];
    let allFilled = true;
    requiredFields.forEach((field) => {
      const $field = $(field);
      if (!$field.val()) {
        allFilled = false;
        $field.addClass("error");
        $field.next(".error-message").remove();
        $field.after('<span class="error-message" style="color:red;">This field is required</span>');
        // retun if any field is empty and not submit the form
        
      } else {
        $field.removeClass("error");
        $field.next(".error-message").remove();
      }
    });

    if (!allFilled) {
      return;
    }

    $.ajax({
      url: rvbs_gantt.ajax_url,
      type: "POST",
      data: data,
      success: function (response) {
        if (response.success) {
          alert(response.data);
          $("#booking-modal").hide();
          const month = $(".prev-month").data("month") + 1;
          const year = $(".prev-month").data("year");
          loadCalendar(month, year);
        } else {
          alert(response.data);
        }
      },
      error: function (xhr, status, error) {
        console.log("Save booking error:", xhr, status, error);
        alert("Error saving booking");
      },
    });
  });

  // User search
  $("#user_search").on("keyup", function () {
    let keyword = $(this).val();
    if (keyword.length < 2) return;

    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "rvbs_user_search",
        keyword: keyword,
      },
      success: function (response) {
        let resultsDiv = $("#user-search-results");
        resultsDiv.empty().show();

        if (response.length > 0) {
          response.forEach((user) => {
            resultsDiv.append(
              `<div class="user-option" data-id="${user.id}">${user.name} (${user.email})</div>`
            );
          });
          $("#new-user-fields").hide();
        } else {
          resultsDiv.hide();
          $("#new-user-fields").show();
        }
      },
      error: function (xhr, status, error) {
        console.log("User search error:", xhr, status, error);
      },
    });
  });

  // Select user
  $(document).on("click", ".user-option", function () {
    $("#user_search").val($(this).text());
    $("#user_id").val($(this).data("id"));
    $("#user-search-results").hide();
    $("#new-user-fields").hide();
  });

  // Cancel booking
  $("#cancel-booking").on("click", function () {
    const bookingId = $("#booking_id").val();
    if (!bookingId) return;

    if (confirm("Are you sure you want to cancel this booking?")) {
      $.ajax({
        url: rvbs_gantt.ajax_url,
        type: "POST",
        data: {
          action: "rvbs_cancel_booking",
          booking_id: bookingId,
          nonce: rvbs_gantt.nonce,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data);
            $("#booking-modal").hide();
            const month = $(".prev-month").data("month") + 1;
            const year = $(".prev-month").data("year");
            loadCalendar(month, year);
          } else {
            alert(response.data);
          }
        },
        error: function (xhr, status, error) {
          console.log("Cancel booking error:", xhr, status, error);
          alert("Error cancelling booking");
        },
      });
    }
  });

  // Edit booking
  $(document).on("click", ".booked", function () {
    const bookingId = $(this).data("booking-id");
    $.ajax({
      url: rvbs_gantt.ajax_url,
      type: "POST",
      data: {
        action: "rvbs_get_booking",
        booking_id: bookingId,
        nonce: rvbs_gantt.nonce,
      },
      success: function (response) {
        if (response.success) {
          const booking = response.data;
          $("#booking_id").val(booking.id);
          $("#lot_id").val(booking.lot_id);
          $("#user_id").val(booking.user_id);
          $("#check_in").val(booking.check_in);
          $("#check_out").val(booking.check_out);
          $("#total_price").val(booking.total_price);
          $("#status").val(booking.status);
          // Update lot_price based on selected lot
          const price = $(`#lot_id option[value="${booking.lot_id}"]`).data("price");
          $("#lot_price").val(price ? price.toFixed(2) : "");
          $("label[for='total_price']").text(
            `Total Price: $${parseFloat(booking.total_price).toFixed(2)}`
          );
          $("#booking-modal").show();
        } else {
          alert("Error loading booking details");
        }
      },
      error: function (xhr, status, error) {
        console.log("Edit booking error:", xhr, status, error);
        alert("Error loading booking details");
      },
    });
  });

  // Navigation
  $(".prev-month, .next-month").on("click", function () {
    const month = $(this).data("month");
    const year = $(this).data("year");
    loadCalendar(month, year);
  });

  // Filters
  $("#rv-lot-filter, #status-filter").on("change", function () {
    const month = $(".prev-month").data("month") + 1;
    const year = $(".prev-month").data("year");
    loadCalendar(month, year);
  });

  function loadCalendar(month, year) {
    const lotId = $("#rv-lot-filter").val();
    const status = $("#status-filter").val();

    $.ajax({
      url: rvbs_gantt.ajax_url,
      type: "POST",
      data: {
        action: "rvbs_load_calendar",
        month: month,
        year: year,
        lot_id: lotId,
        status: status,
        nonce: rvbs_gantt.nonce,
      },
      success: function (response) {
        if (response.success) {
          renderCalendarGrid(response.data.calendar_data);
          $(".current-month").text(response.data.month_display);
          $(".prev-month")
            .data("month", month - 1)
            .data("year", year);
          $(".next-month")
            .data("month", parseInt(month) + 1)
            .data("year", year);
          addCurrentDateOverlay();
        } else {
          alert("Error loading calendar");
        }
      },
      error: function (xhr, status, error) {
        console.log("Load calendar error:", xhr, status, error);
        alert("Error loading calendar");
      },
    });
  }

  function renderCalendarGrid(data) {
    const $container = $(".calendar-container");
    $container.empty();

    const $gridContainer = $('<div class="rvbs-gantt-grid"></div>')
      .attr("data-current-month", data.month)
      .attr("data-current-year", data.year)
      .attr("data-days-in-month", data.days_in_month);

    const $headerRow = $('<div class="rvbs-gantt-row rvbs-gantt-header"></div>');
    $headerRow.append('<div class="rvbs-gantt-cell header-cell serial-no">Serial No</div>');
    $headerRow.append('<div class="rvbs-gantt-cell header-cell rv-lot-title">RV Lot Title</div>');
    $headerRow.append('<div class="rvbs-gantt-cell header-cell status">Status</div>');

    data.days.forEach((day) => {
      const $dayHeader = $('<div class="rvbs-gantt-cell header-cell day-header"></div>')
        .attr("data-day", day.day)
        .text(day.day);
      if (day.is_today) {
        $dayHeader.addClass("today");
      }
      $headerRow.append($dayHeader);
    });

    $gridContainer.append($headerRow);

    data.lots.forEach((lot) => {
      const $row = $('<div class="rvbs-gantt-row"></div>');
      $row.append(`<div class="rvbs-gantt-cell serial-no">${lot.serial}</div>`);
      $row.append(`<div class="rvbs-gantt-cell rv-lot-title">${lot.title}</div>`);

      const $statusCell = $('<div class="rvbs-gantt-cell status"></div>');
      const $statusSelect = $('<select class="lot-status"></select>').attr("data-lot-id", lot.id);
      $statusSelect.append(
        `<option value="available" ${lot.is_available ? "selected" : ""}>Available</option>`
      );
      $statusSelect.append(
        `<option value="unavailable" ${!lot.is_available ? "selected" : ""}>Unavailable</option>`
      );
      $statusCell.append($statusSelect);
      $row.append($statusCell);

      for (let day = 1; day <= data.days_in_month; day++) {
        const dayData = data.days[day - 1];
        const $dayCell = $('<div class="rvbs-gantt-cell day-cell"></div>')
          .attr("data-day", day)
          .attr("data-lot-id", lot.id);

        if (!lot.is_available) {
          const $unavailable = $('<div class="unavailable"></div>').text("Unavailable");
          $dayCell.append($unavailable);
          $row.append($dayCell);
          continue;
        }

        const bookingsForDay = lot.bookings.filter((booking) => {
          return day >= booking.start_day && day <= booking.end_day;
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

            const bookingInfo = `Lot Title: ${lot.title}\nUser ID: ${
              booking.user_id
            }\nCheck-in: ${booking.check_in}\nCheck-out: ${
              booking.check_out
            }\nTotal Price: $${booking.total_price}\nStatus: ${
              isExpiredToday
                ? "Expired Today"
                : booking.is_expired
                ? "Expired"
                : booking.status.charAt(0).toUpperCase() + booking.status.slice(1)
            }`;

            const isCheckIn =
              new Date(booking.check_in).getDate() === day &&
              new Date(booking.check_in).getMonth() + 1 === data.month &&
              new Date(booking.check_in).getFullYear() === data.year
                ? " check-in"
                : "";
            const isCheckOut =
              new Date(booking.check_out).getDate() === day &&
              new Date(booking.check_out).getMonth() + 1 === data.month &&
              new Date(booking.check_out).getFullYear() === data.year
                ? " check-out"
                : "";
            const isExpiredClass = booking.is_expired ? " expired" : "";

            const bookingColor = booking.is_expired ? "#d3d3d3" : getRandomColor();

            const remainingStartDay = booking.start_day;
            if (remainingStartDay <= booking.end_day) {
              setTimeout(() => {
                let cell_width = $dayCell.outerWidth();
                const remainingDays = booking.end_day - remainingStartDay + 1;
                const remainingWidth = remainingDays * cell_width - 2;
                const remainingLeft = (remainingStartDay - booking.start_day) * cell_width;
                const $bookingDiv = $(
                  `<div class="booked${isCheckIn}${isCheckOut}${isExpiredClass}"></div>`
                )
                  .attr("data-booking-id", booking.id)
                  .attr("data-start-day", booking.start_day)
                  .attr("data-end-day", booking.end_day)
                  .css("width", `${remainingWidth}px`)
                  .css("left", `${remainingLeft}px`)
                  .attr("data-title", bookingInfo);
                $bookingDiv.css("background-color", bookingColor);
                $bookingDiv.append(
                  `<span class="booking-dates">${booking.check_in} to ${booking.check_out}</span>`
                );
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

  function addCurrentDateOverlay() {
    $(".current-date-overlay").remove();

    const $container = $(".rvbs-gantt-grid");
    const currentMonth = parseInt($container.data("current-month"));
    const currentYear = parseInt($container.data("current-year"));
    const today = new Date();
    const todayMonth = today.getMonth() + 1;
    const todayYear = today.getFullYear();
    const todayDate = today.getDate();

    if (currentMonth === todayMonth && currentYear === todayYear) {
      const fixedColumnsWidth =
        $(".rvbs-gantt-cell.serial-no").outerWidth() +
        $(".rvbs-gantt-cell.rv-lot-title").outerWidth();
      const $dayCell = $('.rvbs-gantt-cell.day-header[data-day="1"]');
      const cellWidth = $dayCell.length ? $dayCell.outerWidth() : 0;
      const overlayPosition = fixedColumnsWidth + (todayDate - 1) * cellWidth;

      const $overlay = $('<div class="current-date-overlay"></div>').css("left", overlayPosition + "px");
      $container.append($overlay);
    }
  }

  function updateBookingWidths() {
    const $dayCell = $(".rvbs-gantt-cell.day-cell").first();
    const cellWidth = $dayCell.length ? $dayCell.outerWidth() : 0;

    $(".booked").each(function () {
      const $booking = $(this);
      const startDay = parseInt($booking.attr("data-start-day"));
      const endDay = parseInt($booking.attr("data-end-day"));
      const remainingDays = endDay - startDay + 1;
      const remainingWidth = remainingDays * cellWidth - 2;
      const remainingLeft = 0;

      $booking.css({
        width: `${remainingWidth}px`,
        left: `${remainingLeft}px`,
      });
    });
  }

  // Tooltip handling
  let $tooltip = null;

  $(document).on("mouseenter", ".booked", function (e) {
    if (!$tooltip) {
      $tooltip = $('<div class="rvbs-tooltip"></div>').appendTo(".rvbs-gantt");
    }

    const bookingInfo = $(this).attr("data-title").split("\n");
    const lotTitle = bookingInfo[0].replace("Lot Title: ", "");
    const userId = bookingInfo[1].replace("User ID: ", "");
    const checkIn = bookingInfo[2].replace("Check-in: ", "");
    const checkOut = bookingInfo[3].replace("Check-out: ", "");
    const totalPrice = bookingInfo[4].replace("Total Price: $", "");
    const status = bookingInfo[5].replace("Status: ", "");

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
      left: left + "px",
      top: top + "px",
      visibility: "visible",
    });
  });

  $(document).on("mousemove", ".booked", function (e) {
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
        left: left + "px",
        top: top + "px",
      });
    }
  });

  $(document).on("mouseleave", ".booked", function () {
    if ($tooltip) {
      $tooltip.css("visibility", "hidden");
    }
  });

  // Update overlay and booking widths on window resize and load
  $(window).on("resize load", function () {
    addCurrentDateOverlay();
    updateBookingWidths();
  });

  // Initial load
  const month = new Date().getMonth() + 1;
  const year = new Date().getFullYear();
  loadCalendar(month, year);
});