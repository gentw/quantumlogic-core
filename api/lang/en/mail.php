<?php

/**
 * Copy for the transactional emails in resources/views/emails/.
 *
 * English is the fallback locale, so every key must exist here. `de` and `sq`
 * may be partial — Laravel falls back per key, not per file.
 */
return [

    'common' => [
        'footer' => '© :year QuantumLogic',
        'rights' => '© :year QuantumLogic. All rights reserved.',
        'support' => 'If you have any questions, please contact our support team.',
        'reply' => 'If you have any questions, just reply to this email.',
    ],

    'otp' => [
        'subject' => 'Your verification code',
        'title' => 'Your verification code',
        'heading' => 'Confirm your identity',
        'intro' => 'To verify your identity, use the code below:',
        'validity' => 'The code is valid for 10 minutes, until :time.',
    ],

    'welcome' => [
        'subject' => 'Welcome to QuantumLogic – verify your account',
        'title' => 'Welcome to QuantumLogic',
        'heading' => 'Welcome to QuantumLogic',
        'created' => 'Your account has been created.',
        'next_step' => 'You can now sign in to follow your services, tickets and invoices.',
        'cta' => 'Go to your account',
    ],

    'reset' => [
        'subject' => 'Reset your password',
        'title' => 'Reset your password',
        'heading' => 'Reset password',
        'intro' => 'If you have lost your password or want to reset it, use the link below to get started.',
        'cta' => 'Reset password',
        'ignore' => 'If you did not request a password reset you can ignore this email. Only someone with access to your inbox can reset your password.',
    ],

    'roles' => [
        'client' => 'a client',
        'agent' => 'an agent',
        'admin' => 'an administrator',
    ],

    'new_user' => [
        'subject' => 'Welcome to QuantumLogic',
        'title' => 'Welcome to QuantumLogic',
        'heading' => 'Welcome to QuantumLogic',
        'greeting' => 'Hello :name,',
        'confirmed' => 'Your registration as :role has been confirmed. Your sign-in details are below:',
        'advice' => 'Please use these details to sign in. For your own security, change your password after signing in for the first time.',
        'username' => 'Username',
        'password' => 'Password',
        'cta' => 'Sign in to your account',
        'ignore' => 'If you did not ask to be registered as :role, please ignore this email and contact our support team.',
    ],

    'waitlist' => [
        'subject' => 'Welcome to QuantumLogic – beta access and waiting list',
        'title' => 'Welcome to QuantumLogic',
        'heading' => 'Welcome to QuantumLogic',
        'greeting' => 'Hello :name,',
        'registered' => 'Thank you for registering with QuantumLogic. Your registration was successful and you have been added to our waiting list for the beta.',
        'status' => 'QuantumLogic is in active development and access is being enabled gradually, starting with existing customers.',
        'notify' => 'We will email you as soon as your access is activated, or when there is something important to share.',
        'closing' => 'Thank you for being part of QuantumLogic.',
    ],

    'payment' => [
        'subject' => 'Payment confirmed',
        'title' => 'Payment confirmed',
        'heading' => 'Payment confirmed',
        'billing_period' => 'Billing period',
        'amount_paid' => 'Amount paid',
        'transaction_id' => 'Transaction ID',
        'cta' => 'Go to dashboard',
    ],

];
