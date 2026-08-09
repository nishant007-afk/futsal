<?php
require_once __DIR__ . '/../config/db.php';
http_error_page(500, 'Server error', 'Something went wrong on our end. Our team has been notified.');