<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Gantt Booking Calendar</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2>Gantt-style Booking Calendar</h2>
  <div id="calendar-header"></div>
  <div id="calendar-body"></div>

  <script>
    const bookingsData = [
      {"id":"5","lot_id":"16","post_id":"86","user_id":"4","check_in":"2025-03-24","check_out":"2025-03-25","total_price":"12.00","status":"confirmed"},
      {"id":"6","lot_id":"16","post_id":"86","user_id":"6","check_in":"2025-04-05","check_out":"2025-06-07","total_price":"756.00","status":"confirmed"},
      {"id":"7","lot_id":"19","post_id":"116","user_id":"7","check_in":"2025-04-08","check_out":"2025-04-28","total_price":"400.00","status":"confirmed"},
      {"id":"8","lot_id":"23","post_id":"122","user_id":"8","check_in":"2025-04-05","check_out":"2025-04-06","total_price":"20.00","status":"confirmed"},
      {"id":"9","lot_id":"19","post_id":"116","user_id":"8","check_in":"2025-04-24","check_out":"2025-04-26","total_price":"40.00","status":"confirmed"},
      {"id":"10","lot_id":"23","post_id":"122","user_id":"4","check_in":"2025-04-07","check_out":"2025-04-08","total_price":"20.00","status":"confirmed"},
      {"id":"11","lot_id":"23","post_id":"122","user_id":"4","check_in":"2025-04-15","check_out":"2025-04-17","total_price":"40.00","status":"confirmed"},
      {"id":"12","lot_id":"23","post_id":"122","user_id":"9","check_in":"2025-04-28","check_out":"2025-05-01","total_price":"60.00","status":"confirmed"},
      {"id":"13","lot_id":"23","post_id":"122","user_id":"4","check_in":"2025-04-11","check_out":"2025-04-12","total_price":"20.00","status":"confirmed"},
      {"id":"14","lot_id":"28","post_id":"127","user_id":"10","check_in":"2025-04-08","check_out":"2025-04-12","total_price":"172.00","status":"confirmed"},
      {"id":"15","lot_id":"28","post_id":"127","user_id":"11","check_in":"2025-04-15","check_out":"2025-04-16","total_price":"43.00","status":"confirmed"},
      {"id":"16","lot_id":"30","post_id":"132","user_id":"12","check_in":"2025-04-09","check_out":"2025-04-10","total_price":"20.00","status":"confirmed"},
      {"id":"17","lot_id":"31","post_id":"133","user_id":"13","check_in":"2025-04-10","check_out":"2025-04-12","total_price":"40.00","status":"confirmed"},
      {"id":"18","lot_id":"12","post_id":"82","user_id":"10","check_in":"2025-04-10","check_out":"2025-04-10","total_price":"10.00","status":"confirmed"}
    ];

    const daysToShow = 40;
    const startDate = new Date("2025-04-01");

    function formatDate(date) {
      return date.toISOString().split('T')[0];
    }

    function addDays(date, days) {
      const copy = new Date(date);
      copy.setDate(copy.getDate() + days);
      return copy;
    }

    function getDateRange(start, days) {
      return Array.from({ length: days }, (_, i) => addDays(start, i));
    }

    function renderHeader(dates) {
      const header = document.getElementById("calendar-header");
      header.innerHTML = '<div class="label-col">Lot ID</div>';
      dates.forEach(d => {
        const cell = document.createElement("div");
        cell.className = "calendar-cell header-cell";
        cell.textContent = d.toISOString().slice(5, 10);
        header.appendChild(cell);
      });
    }

    function renderBody(bookings, dates) {
      const body = document.getElementById("calendar-body");
      const lotGroups = {};

      bookings.forEach(b => {
        if (!lotGroups[b.lot_id]) lotGroups[b.lot_id] = [];
        lotGroups[b.lot_id].push(b);
      });

      Object.entries(lotGroups).forEach(([lotId, bookings]) => {
        const row = document.createElement("div");
        row.className = "calendar-row";

        const label = document.createElement("div");
        label.className = "label-col";
        label.textContent = "Lot " + lotId;
        row.appendChild(label);

        const rowCells = document.createElement("div");
        rowCells.className = "row-cells";
        rowCells.style.position = "relative";

        // Create blank cells for grid
        dates.forEach(() => {
          const cell = document.createElement("div");
          cell.className = "calendar-cell";
          rowCells.appendChild(cell);
        });

        // Add bookings as bars
        bookings.forEach(b => {
          const checkIn = new Date(b.check_in);
          const checkOut = new Date(b.check_out);
          const offset = Math.max(0, Math.floor((checkIn - startDate) / (1000 * 60 * 60 * 24)));
          const duration = Math.max(1, Math.floor((checkOut - checkIn) / (1000 * 60 * 60 * 24)));

          if (offset >= daysToShow) return;

          const bar = document.createElement("div");
          bar.className = "booking-bar";
          bar.style.left = `${offset * 40}px`;
          bar.style.width = `${duration * 40}px`;
          bar.textContent = `#${b.id} (${b.check_in} → ${b.check_out})`;

          rowCells.appendChild(bar);
        });

        row.appendChild(rowCells);
        body.appendChild(row);
      });
    }

    const dates = getDateRange(startDate, daysToShow);
    renderHeader(dates);
    renderBody(bookingsData, dates);
  </script>
</body>
</html>
