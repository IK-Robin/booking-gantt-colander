<?php
// db.php - Sample connection file (adjust with your DB credentials)
$mysqli = new mysqli("localhost", "root", "", "new_rv_booking");

$query = "SELECT id, lot_id, post_id, user_id, check_in, check_out, total_price, status FROM wp_rvbs_bookings WHERE status = 'confirmed'";
$result = $mysqli->query($query);

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
header('Content-Type: application/json');
echo json_encode($bookings);

?>
