<?php
function get_conn(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = mysqli_connect("localhost", "root", "", "job_tracker");
        if (!$conn) die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}
?>