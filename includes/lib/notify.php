<?php

function notify_user(int $user_id, string $title, string $body = '', string $icon = 'fa-bell', string $link = ''): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, body, icon, link) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issss', $user_id, $title, $body, $icon, $link);
    $stmt->execute();
}

/**
 * Professional, modern HTML email template for GoalSpace.
 * Bulletproof inline CSS compatible with all email clients (Gmail, Apple Mail, Outlook).
 * Zero em dashes, clean typography, responsive max-width.
 */
function goalspace_email_html(string $title, string $greeting, string $bodyHtml, string $footerNote = '', string $previewText = ''): string
{
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $contactUrl = absolute_url('pages/page.php?slug=contact');
    $siteUrl = absolute_url('');
    $preview = $previewText !== '' ? $previewText : $title;

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $esc($title) . '</title>
<style>
@media only screen and (max-width: 600px) {
    .email-container { width: 100% !important; border-radius: 0 !important; border-left: none !important; border-right: none !important; }
    .email-outer { padding: 0 !important; }
    .email-body { padding: 24px 20px !important; }
    .email-header { padding: 20px 20px 16px !important; }
    .email-footer { padding: 18px 20px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<!-- Hidden preview snippet for inbox list -->
<div style="display:none;font-size:1px;color:#f8fafc;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
    ' . $esc($preview) . '
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="email-outer" style="background-color:#f8fafc;padding:36px 12px;">
<tr>
<td align="center">
    <table role="presentation" width="520" cellpadding="0" cellspacing="0" class="email-container" style="max-width:520px;width:100%;background-color:#ffffff;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;">
        <!-- Header -->
        <tr>
            <td class="email-header" style="padding:24px 32px 18px;border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <a href="' . $siteUrl . '" style="text-decoration:none;display:inline-block;">
                                <span style="color:#0f172a;font-size:18px;font-weight:800;letter-spacing:-0.4px;">GoalSpace</span><span style="display:inline-block;width:6px;height:6px;background-color:#10b981;border-radius:50%;margin-left:3px;vertical-align:baseline;"></span>
                            </a>
                        </td>
                        <td align="right">
                            <span style="color:#94a3b8;font-size:12px;font-weight:500;">Security</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Main Body -->
        <tr>
            <td class="email-body" style="padding:28px 32px 24px;">
                <p style="margin:0 0 14px;color:#475569;font-size:15px;font-weight:600;">' . $esc($greeting) . '</p>
                
                ' . $bodyHtml . '
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td class="email-footer" style="padding:18px 32px;background-color:#fafbfc;border-top:1px solid #f1f5f9;">
                ' . ($footerNote !== '' ? '<p style="margin:0 0 8px;color:#64748b;font-size:12px;line-height:1.5;">' . $footerNote . '</p>' : '') . '
                <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.5;">
                    GoalSpace &middot; Fast futsal reservations &middot; 
                    <a href="' . $contactUrl . '" style="color:#10b981;text-decoration:none;font-weight:600;">Support</a> &middot; 
                    <a href="' . $siteUrl . '" style="color:#10b981;text-decoration:none;font-weight:600;">Website</a>
                </p>
            </td>
        </tr>
    </table>
</td>
</tr>
</table>
</body>
</html>';
}

/**
 * Clean, professional transactional booking email.
 */
function booking_email_html(string $heading, array $rows = [], string $note = '', string $name = ''): string
{
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $greeting = $name !== '' ? 'Hi ' . $esc($name) . ',' : 'Hello,';

    $table = '';
    if ($rows) {
        $cells = '';
        $i = 0;
        foreach ($rows as $k => $v) {
            $valStr = (string)$v;
            if (filter_var($valStr, FILTER_VALIDATE_URL)) {
                $valHtml = '<a href="' . $esc($valStr) . '" style="display:inline-block;padding:7px 16px;background-color:#10b981;color:#ffffff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600;">' . ($esc($k) === 'Book now' ? 'Book slot now &rarr;' : 'Open &rarr;') . '</a>';
            } elseif (stripos($k, 'ref') !== false) {
                $valHtml = '<code style="background-color:#f1f5f9;color:#0f172a;font-family:Consolas,monospace;font-size:13px;font-weight:700;padding:3px 7px;border-radius:4px;letter-spacing:0.5px;">' . $esc($valStr) . '</code>';
            } else {
                $valHtml = $esc($valStr);
            }

            $cells .= '<tr>'
                . '<td style="padding:10px 14px;color:#64748b;width:140px;font-size:13.5px;border-bottom:1px solid #f1f5f9;vertical-align:middle;">' . $esc($k) . '</td>'
                . '<td style="padding:10px 14px;color:#0f172a;font-size:14px;font-weight:600;border-bottom:1px solid #f1f5f9;vertical-align:middle;">' . $valHtml . '</td>'
                . '</tr>';
            $i++;
        }
        $table = '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">'
            . $cells
            . '</table>';
    }

    $noteBlock = $note !== '' ? '<p style="margin:16px 0 0;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;color:#475569;font-size:13px;line-height:1.5;">'
        . $esc($note) . '</p>' : '';

    $bodyHtml = '<h2 style="margin:0 0 12px;color:#0f172a;font-size:18px;font-weight:700;">' . $esc($heading) . '</h2>'
        . $table
        . $noteBlock;

    $footerNote = '';
    return goalspace_email_html($heading, $greeting, $bodyHtml, $footerNote);
}

/**
 * Send a transactional booking email. Uses in-app style, safe when SMTP is offline.
 *
 * @return bool true if a mail was actually sent (SMTP configured + ack)
 */
function send_booking_email(string $to, string $subject, string $heading, array $rows = [], string $note = '', string $name = ''): bool
{
    return send_mail($to, $subject, booking_email_html($heading, $rows, $note, $name), true);
}

/**
 * Announce legal page updates to users.
 * Sends ONE combined email per user (every selected page in a single message)
 * and ONE in-app notification per user (shown in the notifications tab).
 * No em dashes are used in the messages. Human-friendly tone.
 *
 * @param string[] $slugs  legal page slugs (privacy, terms, about, help, contact)
 * @param string   $date   humanized date, e.g. "August 7, 2026"
 * @param string   $scope  "all", "admins", "managers" or "users"
 * @return int  number of users notified
 */
function notify_policy_update(array $slugs, string $date = '', string $scope = 'all'): int
{
    global $conn;
    $allowed = [
        'privacy' => 'Privacy Policy',
        'terms'   => 'Terms of Service',
        'about'   => 'About GoalSpace',
        'help'    => 'Help and Support',
        'contact' => 'Contact',
    ];
    $chosen = [];
    foreach ($slugs as $s) {
        if (isset($allowed[$s]) && !in_array($s, $chosen, true)) {
            $chosen[$s] = $allowed[$s];
        }
    }
    if (empty($chosen)) {
        return 0;
    }
    if ($date === '') {
        $date = date('F j, Y');
    }

    $labels = [];
    $lines  = [];
    foreach ($chosen as $slug => $label) {
        $labels[] = $label;
        $lines[]  = '• ' . $label . ': ' . absolute_url('pages/page.php?slug=' . $slug);
    }
    $labelText = implode(', ', $labels);
    $summary   = 'GoalSpace has updated the following: ' . $labelText . ' on ' . $date;
    $firstSlug = array_keys($chosen)[0];

    $where = '';
    if ($scope === 'admins')      { $where = "WHERE role = 'admin'"; }
    elseif ($scope === 'managers'){ $where = "WHERE role = 'manager'"; }
    elseif ($scope === 'users')  { $where = "WHERE role = 'user'"; }
    // scope === 'all' applies no filter

    $res = $conn->query('SELECT id,email,name FROM users ' . $where . ' LIMIT 500');
    if ($res === false) {
        return 0;
    }

    $sent = 0;
    while ($u = $res->fetch_assoc()) {
        // 1) in-app notification -> appears in the notifications tab (bell)
        notify_user((int)$u['id'], 'Legal pages updated', $summary, 'fa-circle-info', 'pages/page.php?slug=' . $firstSlug);

        // 2) ONE combined email per user (both policies in the same message)
        // Capped at 500 recipients per run.
        $subject = 'GoalSpace update: ' . $labelText;
        $userName = $u['name'] ?: 'there';
        $greeting = 'Hi ' . $userName . ',';
        
        $policyLinksHtml = '';
        foreach ($chosen as $slug => $label) {
            $policyLinksHtml .= '<li style="margin-bottom:8px;"><a href="' . absolute_url('pages/page.php?slug=' . $slug) . '" style="color:#15803d;font-weight:700;text-decoration:none;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        $bodyHtml = '<p style="margin:0 0 14px;color:#2c3e34;font-size:15px;line-height:1.6;">GoalSpace has updated the following information:</p>'
            . '<ul style="margin:0 0 18px;padding-left:20px;color:#15803d;font-size:14px;line-height:1.8;">'
            . $policyLinksHtml
            . '</ul>'
            . '<p style="margin:0 0 14px;color:#55685d;font-size:13.5px;line-height:1.6;">These updates take effect on <strong>' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</strong>. We believe in being transparent about how our platform operates, so please take a moment to review them when you can.</p>';

        $footerNote = 'If you have any questions, our support team is always here to help.';
        $htmlMsg = goalspace_email_html($subject, $greeting, $bodyHtml, $footerNote);
        queue_email($u['email'], $subject, $htmlMsg);
        $sent++;
    }
    return $sent;
}

function notify_announcement(string $subject, string $message, string $scope = 'all', bool $sendMail = true): int
{
    global $conn;
    $where = '';
    if ($scope === 'admins')      { $where = "WHERE role = 'admin'"; }
    elseif ($scope === 'managers'){ $where = "WHERE role = 'manager'"; }
    elseif ($scope === 'users')  { $where = "WHERE role = 'user'"; }

    // Capped per run to avoid SMTP timeouts on large bases.
    $res = $conn->query('SELECT id,email,name FROM users ' . $where . ' LIMIT 500');
    if ($res === false) {
        return 0;
    }

    $sent = 0;
    while ($u = $res->fetch_assoc()) {
        notify_user((int)$u['id'], $subject, $message, 'fa-bullhorn');
        if ($sendMail) {
            $greeting = 'Hello, ' . ($u['name'] ?: 'valued member') . ',';
            $bodyHtml = '<p style="margin:0 0 16px;color:#2c3e34;font-size:15px;line-height:1.65;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
                . '<p style="margin:0;color:#55685d;font-size:13.5px;line-height:1.6;">Thank you for being part of the GoalSpace community. We look forward to seeing you on the pitch soon.</p>';
            $footerNote = 'If you have any questions, our support team is always here to help.';
            $htmlBody = goalspace_email_html($subject, $greeting, $bodyHtml, $footerNote);
            queue_email($u['email'], $subject, $htmlBody);
        }
        $sent++;
    }
    return $sent;
}

function user_notifications(int $user_id, int $limit = 20): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function unread_notification_count(int $user_id): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

/**
 * Standard empty-state block: soft icon chip + title + optional text and action.
 * Used across bookings, favorites, notifications, search results and dashboards.
 */
function empty_state(string $icon, string $title, string $text = '', ?string $action_url = null, ?string $action_label = null, string $action_class = 'btn btn-outline btn-sm'): void
{
    echo '<div class="empty reveal">';
    echo '<span class="big"><i class="' . e($icon) . '"></i></span>';
    echo '<h3>' . e($title) . '</h3>';
    if ($text !== '') {
        echo '<p>' . e($text) . '</p>';
    }
    if ($action_url !== null && $action_label !== null) {
        $resolvedUrl = preg_match('#^(https?://|/)#', $action_url) ? $action_url : base_url($action_url);
        echo '<a href="' . e($resolvedUrl) . '" class="' . e($action_class) . '">' . e($action_label) . '</a>';
    }
    echo '</div>';
}

function notification_time(?string $created_at): string
{
    if (!$created_at) {
        return '';
    }
    $ts = strtotime($created_at);
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 172800) {
        return 'yesterday';
    }
    return date('M j', $ts);
}

function notification_icon_color(string $icon): string
{
    $map = [
        'fa-calendar-check' => 'green',
        'fa-store' => 'blue',
        'fa-sack-dollar' => 'gold',
        'fa-qrcode' => 'green',
        'fa-circle-xmark' => 'red',
        'fa-bell' => 'brand',
        'fa-calendar-xmark' => 'red',
    ];
    return $map[$icon] ?? 'brand';
}

function mark_notifications_read(int $user_id): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}
