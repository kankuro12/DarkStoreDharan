<?php

return [
    // Test OTP mode: signup OTPs (rider_signup, vendor_signup, ...) use the fixed code below and no SMS is sent.
    // Login and password-reset OTPs are never affected, so this can't be used to take over accounts.
    'test_mode' => (bool) env('OTP_TEST_MODE', false),
    'test_code' => (string) env('OTP_TEST_CODE', '123456'),
];
