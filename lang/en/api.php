<?php

return [
    // Authentication Messages
    'registration_successful' => 'Registration successful. Please check your email for OTP verification.',
    'account_verified' => 'Account verified successfully.',
    'login_successful' => 'Login successful.',
    'logout_successful' => 'Logged out successfully.',
    'token_refreshed' => 'Token refreshed successfully.',
    'account_deleted' => 'Account deleted successfully.',
    'password_reset_successful' => 'Password reset successfully. You can now login with your new password.',
    'otp_sent' => 'If the email exists, an OTP has been sent to your email address.',

    // Error Messages
    'customer_not_found' => 'Customer not found.',
    'invalid_credentials' => 'Invalid email or password.',
    'account_not_verified' => 'Account is not verified. Please verify your email first.',
    'otp_invalid' => 'Invalid or expired OTP.',
    'email_already_exists' => 'The email has already been taken.',
    'validation_failed' => 'Validation failed.',
    'unauthorized' => 'Unauthorized. Please login first.',
    'server_error' => 'An error occurred. Please try again later.',

    // Field Labels
    'id' => 'ID',
    'name' => 'Name',
    'email' => 'Email',
    'phone' => 'Phone',
    'country_code' => 'Country Code',
    'birthdate' => 'Birthdate',
    'avatar' => 'Avatar',
    'foodics_customer_id' => 'Foodics Customer ID',
    'is_verified' => 'Is Verified',
    'verified' => 'Verified',
    'unverified' => 'Unverified',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',

    // Foodics Errors
    'foodics_unauthorized' => 'Foodics authentication failed. Please check your credentials.',
    'foodics_forbidden' => 'Access forbidden to Foodics resource.',
    'foodics_not_found' => 'Resource not found in Foodics.',
    'foodics_validation' => 'Foodics validation error.',
    'foodics_rate_limit' => 'Too many requests to Foodics. Please retry later.',
    'foodics_server_error' => 'Foodics server error. Please try again later.',
    'foodics_maintenance' => 'Foodics is currently under maintenance.',
    'foodics_timeout' => 'Request to Foodics timed out.',
    'foodics_mapping_error' => 'Error mapping Foodics data to local model.',
];

