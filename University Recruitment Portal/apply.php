<?php
include 'connection.php';
$res = mysqli_query($conn, "SELECT job_id FROM jobs ORDER BY RAND() LIMIT 1");
$row = mysqli_fetch_assoc($res);
$job_id = $row['job_id'];
$name   = mysqli_real_escape_string($conn, $_POST['name']);
$email  = mysqli_real_escape_string($conn, $_POST['email']);
$phone  = mysqli_real_escape_string($conn, $_POST['phone']);
$message= mysqli_real_escape_string($conn, $_POST['message']);
$dir    = 'uploads/';
$file   = time().'_'.basename($_FILES['cv']['name']);
$path   = $dir . $file;
move_uploaded_file($_FILES['cv']['tmp_name'], $path);
$sql = "INSERT INTO job_apps (job_id, name, email, phone, cv_path, message) VALUES
($job_id, '$name', '$email', '$phone', '$path', '$message')";
if (mysqli_query($conn, $sql)) {
    header("Location: success.php");
    exit;
} else {
    header("Location: error.php");
    exit;
}
?>
