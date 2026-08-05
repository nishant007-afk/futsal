<?php

define('MAIL_FROM', 'your-noreply@example.com');
define('MAIL_FROM_NAME', 'GoalSpace');

define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-smtp-username');
define('SMTP_PASS', 'your-smtp-password');

function mail_configured(): bool
{
    return SMTP_USER !== '' && SMTP_USER !== 'your-email@gmail.com' && SMTP_PASS !== '';
}

function send_mail(string $to, string $subject, string $body): bool
{
    if (!mail_configured()) {
        return false;
    }

    $conn = stream_socket_client(
        'tcp://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        15
    );
    if (!$conn) {
        return false;
    }
    stream_set_timeout($conn, 15);

    $read = function () use ($conn) {
        $line = '';
        while (substr($line, 3, 1) !== ' ') {
            $chunk = fgets($conn);
            if ($chunk === false) {
                break;
            }
            $line = $chunk;
        }
        return $line;
    };
    $cmd = function ($command) use ($conn) {
        fwrite($conn, $command . "\r\n");
        return $conn;
    };

    $read();
    $cmd('EHLO localhost');
    $read();
    $cmd('STARTTLS');
    $read();
    $crypto = stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$crypto) {
        fclose($conn);
        return false;
    }
    $cmd('EHLO localhost');
    $read();
    $cmd('AUTH LOGIN');
    $read();
    $cmd(base64_encode(SMTP_USER));
    $read();
    $cmd(base64_encode(SMTP_PASS));
    $auth = $read();
    if (strpos($auth, '235') !== 0) {
        fclose($conn);
        return false;
    }

    $cmd('MAIL FROM:<' . MAIL_FROM . '>');
    $read();
    $cmd('RCPT TO:<' . $to . '>');
    $read();
    $cmd('DATA');
    $read();

    $subjectEncoded = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8')
        : '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
    $headers .= 'To: <' . $to . '>' . "\r\n";
    $headers .= 'Subject: ' . $subjectEncoded . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
    $message = $headers . "\r\n" . $body;
    $message = str_replace("\r\n.", "\r\n..", $message);

    $cmd($message);
    $cmd('.');
    $done = $read();
    fclose($conn);

    return strpos($done, '250') === 0;
}
