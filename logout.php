<?php
// logout.php - Çıkış İşlemi
session_start();
session_destroy();
header("Location: index.php");
exit();
?>