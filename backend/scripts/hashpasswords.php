<?php
$password= "admin123";
$hashedPass = password_hash($password, PASSWORD_DEFAULT);
echo($hashedPass)

?>
