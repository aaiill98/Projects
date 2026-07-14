<?php
// connection.php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "taif_slim";

// إنشاء الاتصال
$conn = new mysqli($host, $user, $pass, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// ضبط الترميز
$conn->set_charset("utf8mb4");

?>
