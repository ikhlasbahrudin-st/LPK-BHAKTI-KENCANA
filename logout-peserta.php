<?php
session_start();
unset($_SESSION['peserta_logged_in']);
unset($_SESSION['peserta_id']);
unset($_SESSION['peserta_username']);
unset($_SESSION['peserta_nama']);
session_destroy();

header("Location: login-peserta.php");
exit;
?>