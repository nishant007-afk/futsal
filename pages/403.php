<?php
require_once __DIR__ . '/../config/db.php';
http_error_page(403, 'Access denied', 'You don\'t have permission to view this page.');