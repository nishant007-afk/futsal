<?php
require_once __DIR__ . '/../config/db.php';
http_error_page(404, 'Page not found', 'That link is broken or the page has moved.');