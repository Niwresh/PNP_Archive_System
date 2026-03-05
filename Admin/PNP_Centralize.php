<?php
$conn = new mysqli("localhost","root","","PNP_Admin_db");

if($conn->connect_error){
   die("Connection Failed");
}
?>
