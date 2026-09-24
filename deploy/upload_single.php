<?php
$ftp = ftp_connect('ftpupload.net', 21, 30);
if (!$ftp || !ftp_login($ftp, 'if0_42568501', 'HBX6u7ybjI3')) {
    die("FTP login failed\n");
}
ftp_pasv($ftp, true);

$action = $argv[1] ?? '';
$target = $argv[2] ?? '';

if ($action === 'delete') {
    $remote = 'htdocs/' . $target;
    if (@ftp_delete($ftp, $remote)) {
        echo "Deleted remote $remote\n";
    } else {
        echo "Failed or file didn't exist: $remote\n";
    }
} else {
    $local = $action;
    $remote = 'htdocs/' . $local;
    if (ftp_put($ftp, $remote, $local, FTP_BINARY)) {
        echo "Uploaded $local to $remote successfully\n";
    } else {
        echo "Failed to upload $local\n";
    }
}
ftp_close($ftp);
