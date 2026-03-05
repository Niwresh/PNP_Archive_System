<?php
$conn = new mysqli("localhost","root","","PNP_Archive_db");

if($conn->connect_error){
   die("Connection Failed");
}
?>
