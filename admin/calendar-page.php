<div class="wrap">
    <h1>RV Gantt Booking Calendar</h1>
    <div id="gantt-container">
        <div id="gantt-header">
            <div class="cell">RV Lot</div>
            <?php
            $start = strtotime('2025-04-01');
            for ($i = 0; $i < 30; $i++) {
                echo '<div class="cell">' . date('d', strtotime("+$i day", $start)) . '</div>';
            }
            ?>
        </div>
        <div id="gantt-body">
            <!-- Booking rows will be populated by JS -->
        </div>
    </div>
</div>
