<?php

function totp_base32_encode($binary)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $encoded = '';

    for ($i = 0; $i < strlen($binary); $i++) {
        $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
    }

    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }

        $encoded .= $alphabet[bindec($chunk)];
    }

    return $encoded;
}

function totp_base32_decode($secret)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $bits = '';
    $binary = '';

    for ($i = 0; $i < strlen($secret); $i++) {
        $position = strpos($alphabet, $secret[$i]);

        if ($position === false) {
            continue;
        }

        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }

    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $binary .= chr(bindec($byte));
        }
    }

    return $binary;
}

function totp_generate_secret()
{
    return totp_base32_encode(random_bytes(20));
}

function totp_code($secret, $timeSlice = null)
{
    $timeSlice = $timeSlice ?? floor(time() / 30);
    $secretKey = totp_base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $secretKey, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );

    return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
}

function totp_verify($secret, $code, $window = 1)
{
    $code = preg_replace('/\D/', '', (string)$code);

    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $currentTimeSlice = floor(time() / 30);

    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $currentTimeSlice + $i), $code)) {
            return true;
        }
    }

    return false;
}

function totp_otpauth_uri($issuer, $accountName, $secret)
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountName)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}
