<?php

/**
 * Slot Hold / Concurrency Protection (5-minute lease)
 */
function cleanup_expired_slot_holds(): void
{
    global $conn;
    try {
        $conn->query('DELETE FROM slot_holds WHERE expires_at <= NOW()');
    } catch (Throwable $e) {
        error_log('cleanup_expired_slot_holds notice: ' . $e->getMessage());
    }
}

function is_slot_held(int $ground_id, string $booking_date, string $start_time, ?int $ignore_user_id = null): bool
{
    global $conn;
    try {
        cleanup_expired_slot_holds();
        if ($ignore_user_id !== null) {
            $stmt = $conn->prepare('SELECT id FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND user_id != ? AND expires_at > NOW()');
            $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $ignore_user_id);
        } else {
            $stmt = $conn->prepare('SELECT id FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND expires_at > NOW()');
            $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
        }
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    } catch (Throwable $e) {
        error_log('is_slot_held error: ' . $e->getMessage());
        return false;
    }
}

function acquire_slot_hold(int $ground_id, string $booking_date, string $start_time, int $user_id, int $hold_seconds = 300): array
{
    global $conn;
    try {
        cleanup_expired_slot_holds();

        if (slot_is_taken($ground_id, $booking_date, $start_time)) {
            return ['ok' => false, 'error' => 'That time slot is already booked.'];
        }

        if (is_slot_held($ground_id, $booking_date, $start_time, $user_id)) {
            return ['ok' => false, 'error' => 'Someone is currently checking out this slot. Try again in a couple of minutes.'];
        }

        $token = bin2hex(random_bytes(24));

        $stmt = $conn->prepare(
            'INSERT INTO slot_holds (ground_id, booking_date, start_time, user_id, hold_token, expires_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                user_id = IF(expires_at <= NOW() OR user_id = VALUES(user_id), VALUES(user_id), user_id),
                hold_token = IF(expires_at <= NOW() OR user_id = VALUES(user_id), VALUES(hold_token), hold_token),
                expires_at = IF(expires_at <= NOW() OR user_id = VALUES(user_id), VALUES(expires_at), expires_at)'
        );
        $stmt->bind_param('issisi', $ground_id, $booking_date, $start_time, $user_id, $token, $hold_seconds);
        if ($stmt->execute()) {
            $stmt->close();
            // Verify this user successfully claimed or refreshed the hold
            $vStmt = $conn->prepare('SELECT user_id, hold_token FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND start_time = ?');
            $vStmt->bind_param('iss', $ground_id, $booking_date, $start_time);
            $vStmt->execute();
            $curHold = $vStmt->get_result()->fetch_assoc();
            $vStmt->close();
            if ($curHold && (int)$curHold['user_id'] === $user_id && $curHold['hold_token'] === $token) {
                return ['ok' => true, 'token' => $token, 'expires_at' => $curHold['expires_at'] ?? null];
            }
        }
    } catch (Throwable $e) {
        error_log('acquire_slot_hold error: ' . $e->getMessage());
    }
    return ['ok' => false, 'error' => 'Someone is currently checking out this slot. Try again in a couple of minutes.'];
}

function release_slot_hold(int $ground_id, string $booking_date, string $start_time, int $user_id): void
{
    global $conn;
    try {
        $stmt = $conn->prepare('DELETE FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND user_id = ?');
        $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $user_id);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('release_slot_hold error: ' . $e->getMessage());
    }
}

/**
 * Asynchronous Background Email Queue
 */
function queue_email(string $recipient, string $subject, string $body_html): bool
{
    global $conn;
    $recipient = strtolower(trim($recipient));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    try {
        $stmt = $conn->prepare('INSERT INTO email_queue (recipient, subject, body_html, status) VALUES (?, ?, ?, "pending")');
        $stmt->bind_param('sss', $recipient, $subject, $body_html);
        return $stmt->execute();
    } catch (Throwable $e) {
        error_log('queue_email error: ' . $e->getMessage());
        return false;
    }
}
