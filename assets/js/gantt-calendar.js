jQuery(document).ready(function ($) {
  // Helper function to generate random color
  let is_edit_booking = false;
  let is_add_new_booking = false;
  let user_not_found = false;

  function getRandomColor() {
    const letters = "0123456789ABCDEF";
    let color = "#";
    for (let i = 0; i < 6; i++) {
      color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
  }

  // Initialize Flatpickr for check-in and check-out
const datePickers = flatpickr(".rvbs-date-picker", {
  dateFormat: "Y-m-d",
  minDate: "today",
  disable: [],

  onChange: function () {
    updateTotalPrice();
    checkAvailability();
  },

onOpen: function (selectedDates, dateStr, instance) {
  const bookingId = $("#booking_id").val();
  const checkIn = $("#check_in").val();
  const checkOut = $("#check_out").val();

  // ❌ Remove this auto-setting of both dates on open
  // instance.setDate([checkIn, checkOut], false);

  const lotId = $("#lot_id").val();
  if (lotId) {
    setTimeout(() => {
      fetchUnavailableDates(lotId);
    }, 100);
  }
}


});

// Updated fetchUnavailableDates
// old working code 
// function fetchUnavailableDates(lotId) {
//   const checkInValue = $("#check_in").val();
//   const checkOutValue = $("#check_out").val();

//   $.ajax({
//     url: rvbs_gantt.ajax_url,
//     type: "POST",
//     data: {
//       action: "rvbs_get_booked_dates",
//       lot_id: lotId,
//       booking_id: $("#booking_id").val(),
//       nonce: rvbs_gantt.nonce,
//     },
//     success: function (response) {
//       if (response.success && response.data.disabled_dates) {
//         datePickers.forEach((picker) => {
//           // 1. Disable dates
//           picker.set("disable", response.data.disabled_dates.map((d) => new Date(d)));

//           // 2. Set check-in / check-out if editing
//           if (is_edit_booking) {
//             if (picker.element.id === "check_in" && checkInValue) {
//               picker.setDate(checkInValue, false);
//               $("#check_in").val(checkInValue);
//             } else if (picker.element.id === "check_out" && checkOutValue) {
//               picker.setDate(checkOutValue, false);
//               $("#check_out").val(checkOutValue);
//             }
//           }

//           picker.redraw();
//         });
//       }
//     },
//     error: function (xhr, status, error) {
//       console.error("Fetch unavailable dates error:", xhr, status, error);
//     },
//   });
// }

function fetchUnavailableDates(lotId) {
  const bookingId = $("#booking_id").val();
  let editableDates = [];

  // Gather editable dates if editing an existing booking
  if (is_edit_booking && bookingId) {
    const bookingBlock = $(`.booked[data-booking-id="${bookingId}"]`);
    const checkIn = bookingBlock.data("check-in");
    const checkOut = bookingBlock.data("check-out");

    if (checkIn && checkOut) {
      const start = new Date(checkIn);
      const end = new Date(checkOut);
      let loop = new Date(start);

      while (loop <= end) {
        editableDates.push(flatpickr.formatDate(loop, "Y-m-d"));
        loop.setDate(loop.getDate() + 1);
      }
    }
  }

  $.ajax({
    url: rvbs_gantt.ajax_url,
    type: "POST",
    data: {
      action: "rvbs_get_booked_dates",
      lot_id: lotId,
      booking_id: bookingId,
      nonce: rvbs_gantt.nonce,
    },
    success: function (response) {
      if (response.success && response.data.disabled_dates) {
        let disabledDates = is_edit_booking
          ? response.data.disabled_dates.filter((d) => !editableDates.includes(d))
          : response.data.disabled_dates;

        datePickers.forEach((picker) => {
          // Disable all dates that are booked, except editable ones
          picker.set("disable", disabledDates.map((d) => new Date(d)));

          // Highlight editable (originally booked) dates in red
          picker.set("onDayCreate", function (dObj, dStr, fp, dayElem) {
            const dateStr = dayElem.dateObj
              ? flatpickr.formatDate(dayElem.dateObj, "Y-m-d")
              : null;

            if (editableDates.includes(dateStr)) {
              dayElem.style.backgroundColor = "#f44336"; // Red
              dayElem.style.color = "#fff";
              dayElem.style.borderRadius = "6px";
              dayElem.style.fontWeight = "bold";
              dayElem.title = "Your original booking date";
            }
          });

          picker.redraw();
        });
      }
    },
    error: function (xhr, status, error) {
      console.error("Fetch unavailable dates error:", xhr, status, error);
    },
  });
}




  // Open modal for new booking
  $("#add-new-booking").on("click", function () {
    $("#booking-form").trigger("reset");
    $("#lot_id").val("");
    $("#lot_price").val("");
    $("#total_price").val("");
    $("#availability-message").empty();
    $("label[for='total_price']").text("Total Price: $0.00");
    $("#booking-modal").show();
    is_add_new_booking = true;
    is_edit_booking = false;
  });

  // Close modal
  $("#close-modal").on("click", function () {
    $("#booking-modal").hide();
    is_add_new_booking = false;
    is_edit_booking = false;
//  $("#booking-form").trigger("reset");
//     datePickers.forEach((picker) => {
//       picker.clear();
//       picker.set("disable", []);
//       picker.redraw();
//     });
// Clear Flatpickr date pickers
  datePickers.forEach((picker) => {
    picker.clear();                    // Remove selected dates
    picker.set("disable", []);        // Clear disabled dates
    picker.set("onDayCreate", null);  // Remove any custom highlight styling
    picker.redraw();                  // Redraw the calendar UI
  });
  });

  // Update total price
  function updateTotalPrice() {
    const $form = $("#booking-form");
    const lotId = $form.find("#lot_id").val();
    const checkIn = $form.find("#check_in").val();
    const checkOut = $form.find("#check_out").val();

    if (lotId) {
      const price = $form
        .find(`#lot_id option[value="${lotId}"]`)
        .data("price");
      $form.find("#lot_price").val(price ? price.toFixed(2) : "");

      if (checkIn && checkOut) {
        const nights = Math.ceil(
          (new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24)
        );
        const totalPrice = price * nights;
        $form.find("#total_price").val(totalPrice.toFixed(2));
        $("label[for='total_price']").text(
          `Total Price: $${totalPrice.toFixed(2)}`
        );
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
    const bookingId = $form.find("#booking_id").val();
    const adults = $form.find("#adults").val() || 0;
    const children = $form.find("#children").val() || 0;
    const pets = $form.find("#pets").val() || 0;
    const lengthFt = $form.find("#length_ft").val() || 0;
    const price = $form
      .find(`#lot_id option[value="${lotId}"]`)
      .data("price");
    const nights =
      checkIn && checkOut
        ? Math.ceil(
            (new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24)
          )
        : 0;

    if (lotId && checkIn && checkOut) {
      let action = is_add_new_booking
        ? "rvbs_check_availability"
        : "rvbs_check_availability_edit";

      $.ajax({
        url: rvbs_gantt.ajax_url,
        type: "POST",
        data: {
          action: action,
          lot_id: lotId,
          booking_id: bookingId,
          check_in: checkIn,
          check_out: checkOut,
          adults: adults,
          children: children,
          pets: pets,
          length_ft: lengthFt,
          nonce: rvbs_gantt.nonce,
        },
        success: function (response) {
          const $message = $("#availability-message");
          const $submit = $form.find('button[type="submit"]');

          if (is_edit_booking) {
            if (
              response.success &&
              (response.data.html === "available" ||
                response.data.booking_status === "shortened" ||
                response.data.booking_status === "extended")
            ) {
              $message.html(
                `<span style="color:green;">${
                  response.data.message || "Available"
                }</span>`
              );
              $submit
                .prop("disabled", false)
                .text(is_add_new_booking ? "Add to Cart" : "Update");
              if (
                is_edit_booking &&
                response.data.booking_status !== "shortened" &&
                response.data.booking_status !== "extended"
              ) {
                $submit.prop("disabled", true).text("Unavailable");
              }
            } else {
              $message.html(
                `<span style="color:red;">${
                  response.data.message ||
                  response.data ||
                  "Not available for this date"
                }</span>`
              );
              $submit.prop("disabled", true).text("Unavailable");
            }
          } else if (is_add_new_booking) {
            if (response.success && response.data === "Dates are available.") {
              $message.html(
                `<span style="color:green;">${
                  response.data.message || "Available"
                }</span>`
              );
              $submit.prop("disabled", false).text("Book Now");
            } else {
              $message.html(
                `<span style="color:red;">${
                  response.data.message ||
                  response.data ||
                  "Not available for this date"
                }</span>`
              );
              $submit.prop("disabled", true).text("Unavailable");
            }
          }

          if (price && nights) {
            $("label[for='total_price']").text(
              `Total Price: $${(price * nights).toFixed(2)}`
            );
          }
        },
        error: function (xhr, status, error) {
          console.error("Availability error:", xhr, status, error);
          const $message = $("#availability-message");
          const $submit = $form.find('button[type="submit"]');
          $message.html(
            '<span style="color:red;">Error checking availability</span>'
          );
          $submit.prop("disabled", true).text("Unavailable");
        },
      });
    } else {
      const $message = $("#availability-message");
      const $submit = $form.find('button[type="submit"]');
      $message.empty();
      $submit.prop("disabled", true).text("Unavailable");

      if (!lotId) {
        $("#lot_id").addClass("error");
        $("#lot_id")
          .next(".error-message")
          .remove();
        $("#lot_id").after(
          '<span class="error-message" style="color:red;">Please select a lot</span>'
        );
      } else {
        $("#lot_id").removeClass("error");
        $("#lot_id")
          .next(".error-message")
          .remove();
      }

      if (!checkIn) {
        $("#check_in").addClass("error");
        $("#check_in")
          .next(".error-message")
          .remove();
        $("#check_in").after(
          '<span class="error-message" style="color:red;">Please select a check-in date</span>'
        );
      } else {
        $("#check_in").removeClass("error");
        $("#check_in")
          .next(".error-message")
          .remove();
      }

      if (!checkOut) {
        $("#check_out").addClass("error");
        $("#check_out")
          .next(".error-message")
          .remove();
        $("#check_out").after(
          '<span class="error-message" style="color:red;">Please select a check-out date</span>'
        );
      } else {
        $("#check_out").removeClass("error");
        $("#check_out")
          .next(".error-message")
          .remove();
      }
    }
  }

  // Handle lot selection
  $("#lot_id").on("change", function () {
    const $form = $("#booking-form");
    const lotId = $(this).val();
    if (lotId) {
      const price = $form
        .find(`#lot_id option[value="${lotId}"]`)
        .data("price");
      $form.find("#lot_price").val(price ? price.toFixed(2) : "");
    } else {
      $form.find("#lot_price").val("");
    }
    updateTotalPrice();
    checkAvailability();
  });

  // Clear error messages on input focus
  $(
    ".rvbs-date-picker, #lot_id, #user_id, #check_in, #check_out, #total_price, #status"
  ).on("focus", function () {
    $(this).removeClass("error");
    $(this).next(".error-message").remove();
  });

  // Save booking or submit a new booking
  $("#booking-form").on("submit", function (e) {
    e.preventDefault();
    const bookingId = $("#booking_id").val();
    let action = is_edit_booking ? "rvbs_update_booking" : "rvbs_add_booking";

    let data = {
      action: action,
      booking_id: bookingId,
      lot_id: $("#lot_id").val(),
      user_id: $("#user_id").val(),
      check_in: $("#check_in").val(),
      check_out: $("#check_out").val(),
      total_price: $("#total_price").val(),
      status: $("#booking_status").val() || "pending",
      nonce: rvbs_gantt.nonce,
    };

    if (user_not_found) {
      const user_name = $("#new_user_name").val();
      const user_email = $("#new_user_email").val();
      if (user_name && user_email) {
        data.user_name = user_name;
        data.user_email = user_email;
      }
    }

    const requiredFields = ["#lot_id", "#user_id", "#check_in", "#check_out"];
    let allFilled = true;
    requiredFields.forEach((field) => {
      const $field = $(field);
      if (!$field.val()) {
        allFilled = false;
        $field.addClass("error");
        $field.next(".error-message").remove();
        $field.after(
          '<span class="error-message" style="color:red;">This field is required</span>'
        );
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
          $("#new-user-fields").hide();
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
          $("#user_id").val("");
          if (!$("#user_id").val()) {
            user_not_found = true;
          }
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

    // remove the error message first if there is any
    $(".error-message").remove();

    const bookingId = $(this).data("booking-id");
    const booking_post_id = $(this).data("booking-post-id");
    const checkInDate = $(this).data("check-in");
    const checkOutDate = $(this).data("check-out");
    const booking_status = $(this).data("status");
    console.log("Edit booking - ID:", bookingId, "Check-in:", checkInDate, "Check-out:", checkOutDate,'Status:',booking_status);


    // add the booking status immediately when click on the edit button booking block 
    $("#booking_status").val(booking_status);
       $("#check_in").val(checkInDate);
          $("#check_out").val(checkOutDate);


    is_edit_booking = true;
    is_add_new_booking = false;

    $("#booking_id").val(bookingId);

    // Set input fields and Flatpickr dates
 if (checkInDate && checkOutDate) {
  datePickers.forEach((picker) => {
    if (picker.element.id === "check_in") {
      picker.setDate(checkInDate, false);
    } else if (picker.element.id === "check_out") {
      picker.setDate(checkOutDate, false);
    }
  });
} else {
  console.warn("Check-in or check-out date missing", $(this).get(0).outerHTML);
}
    $("#booking-modal").show();

    $.ajax({
      url: rvbs_gantt.ajax_url,
      type: "POST",
      data: {
        action: "rvbs_get_booking",
        booking_id: bookingId,
        booking_post_id: booking_post_id,
        nonce: rvbs_gantt.nonce,
      },
      success: function (response) {
      
        if (response.success) {
          const booking = response.data;

          // Populate form fields
          const lotId = booking.post_id.toString();
          if ($(`#lot_id option[value="${lotId}"]`).length) {
            $("#lot_id").val(lotId).trigger("change");
          } else {
            console.warn("Lot ID not found in the dropdown:", lotId);
          }

          $("#user_id").val(booking.user_id);
          $("#user_search").prop("disabled", false);
          $("#user_search").val(
            booking.user_name + " (" + booking.user_email + ")"
          );
       
          $("#total_price").val(booking.total_price);
          
         

          // Update Flatpickr with AJAX response dates
          // datePickers.forEach((picker) => {
          //   if (picker.element.id === "check_in") {
          //     picker.setDate(booking.check_in, false);
          //   } else if (picker.element.id === "check_out") {
          //     picker.setDate(booking.check_out, false);
          //   }
          // });

          const price = $(`#lot_id option[value="${lotId}"]`).data("price");
          $("#lot_price").val(price ? price.toFixed(2) : "");

          $("label[for='total_price']").text(
            `Total Price: $${parseFloat(booking.total_price).toFixed(2)}`
          );

          // checkAvailability();

          // Fetch unavailable dates
          if (lotId) {
            // fetchUnavailableDates(lotId);
          }
        } else {
          alert("Error loading booking details: " + response.data);
        }
      },
      error: function (xhr, status, error) {
        console.error("Edit booking error:", xhr, status, error);
        alert("Error loading booking details");
      },
    });
  });

  // Close edit modal
$(document).on("click", "#close-modal", function (e) {
  e.preventDefault();

  const $modal = $("#booking-modal");

  // reset form fields
  const form = $modal.find("form")[0];
  if (form) form.reset();

  // clear errors/messages & search boxes
  $modal.find(".error-message").remove();
  $modal.find("#user-search-results").hide().empty();
  $modal.find("#new-user-fields").hide();

  // if using flatpickr, clear values (optional)
  $modal.find(".rvbs-date-picker").each(function () {
    if (this._flatpickr) this._flatpickr.clear();
    else $(this).val("");
  });

  $modal.hide();

  is_edit_booking = false;
  is_add_new_booking = false;
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
          console.log(response)
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
        $statusSelect.append(`<option value="available" ${lot.is_available ? "selected" : ""}>Available</option>`);
        $statusSelect.append(`<option value="unavailable" ${!lot.is_available ? "selected" : ""}>Unavailable</option>`);
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
                            const remainingDays = booking.end_day - booking.start_day + 1;
                            const remainingWidth = remainingDays * cell_width - 2;
                            const remainingLeft = (remainingStartDay - booking.start_day) * cell_width;
                            const $bookingDiv = $(`<div class="booked${isCheckIn}${isCheckOut}${isExpiredClass}"></div>`)
                                .attr("data-booking-id", booking.id)
                                .attr("data-booking-post-id", booking.post_id)
                                .attr("data-check-in", booking.check_in)
                                .attr("data-check-out", booking.check_out)
                                .attr("data-start-day", booking.start_day)
                                .attr("data-end-day", booking.end_day)
                                .attr("data-status", booking.status)
                                .css("width", `${remainingWidth}px`)
                                .css("left", `${remainingLeft}px`)
                                .attr("data-title", bookingInfo);
                            $bookingDiv.css("background-color", booking.color_hex);
                            $bookingDiv.append(`<span class="booking-dates">${booking.check_in} to ${booking.check_out}</span>`);
                            $dayCell.append($bookingDiv);
                            console.log(booking);
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

      const $overlay = $('<div class="current-date-overlay"></div>').css(
        "left",
        overlayPosition + "px"
      );
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