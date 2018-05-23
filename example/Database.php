<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "invoicep";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

$sql = "Select item_product_id,item_quantity, item_price,item_discount_amount, item_tax_rate_id from  ip_invoice_items where invoice_id=4";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // output data of each row
	$cart = array();
    while($row = $result->fetch_assoc()) {
        echo "id: " . $row["item_product_id"]. " - Name: " . $row["item_quantity"]. " " . $row["item_tax_rate_id"]. "<br>";
    }
} 
$conn->close();
?>
