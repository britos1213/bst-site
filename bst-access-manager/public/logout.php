<?php
require_once dirname(__DIR__).'/app/helpers/auth.php';
auth_logout();
header('Location: login.php');
