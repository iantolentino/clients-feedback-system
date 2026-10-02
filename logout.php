<?php
require_once __DIR__.'/bootstrap.php';
startAppSession(); session_unset(); session_destroy(); redirect('login.php');
