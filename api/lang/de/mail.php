<?php

/**
 * Austrian German. Formal "Sie" throughout — this is business correspondence
 * from an agency to its customers, so "du" would be wrong.
 */
return [

    'common' => [
        'footer' => '© :year QuantumLogic',
        'rights' => '© :year QuantumLogic. Alle Rechte vorbehalten.',
        'support' => 'Bei Fragen wenden Sie sich bitte an unser Support-Team.',
        'reply' => 'Bei Fragen antworten Sie einfach auf diese E-Mail.',
    ],

    'otp' => [
        'subject' => 'Ihr Bestätigungscode',
        'title' => 'Ihr Bestätigungscode',
        'heading' => 'Bestätigen Sie Ihre Identität',
        'intro' => 'Verwenden Sie den folgenden Code, um Ihre Identität zu bestätigen:',
        'validity' => 'Der Code ist 10 Minuten gültig, bis :time.',
    ],

    'welcome' => [
        'subject' => 'Willkommen bei QuantumLogic – Konto bestätigen',
        'title' => 'Willkommen bei QuantumLogic',
        'heading' => 'Willkommen bei QuantumLogic',
        'created' => 'Ihr Konto wurde erstellt.',
        'next_step' => 'Sie können sich jetzt anmelden, um Ihre Leistungen, Tickets und Rechnungen zu verfolgen.',
        'cta' => 'Zu Ihrem Konto',
    ],

    'reset' => [
        'subject' => 'Passwort zurücksetzen',
        'title' => 'Passwort zurücksetzen',
        'heading' => 'Passwort zurücksetzen',
        'intro' => 'Wenn Sie Ihr Passwort vergessen haben oder es zurücksetzen möchten, verwenden Sie den folgenden Link.',
        'cta' => 'Passwort zurücksetzen',
        'ignore' => 'Wenn Sie kein neues Passwort angefordert haben, können Sie diese E-Mail ignorieren. Nur wer Zugriff auf Ihr Postfach hat, kann Ihr Passwort zurücksetzen.',
    ],

    'roles' => [
        'client' => 'Kunde/Kundin',
        'agent' => 'Agent/Agentin',
        'admin' => 'Administrator/Administratorin',
    ],

    'new_user' => [
        'subject' => 'Willkommen bei QuantumLogic',
        'title' => 'Willkommen bei QuantumLogic',
        'heading' => 'Willkommen bei QuantumLogic',
        'greeting' => 'Hallo :name,',
        'confirmed' => 'Ihre Registrierung als :role wurde bestätigt. Ihre Zugangsdaten finden Sie unten:',
        'advice' => 'Bitte verwenden Sie diese Daten für die Anmeldung. Ändern Sie Ihr Passwort aus Sicherheitsgründen nach der ersten Anmeldung.',
        'username' => 'Benutzername',
        'password' => 'Passwort',
        'cta' => 'Bei Ihrem Konto anmelden',
        'ignore' => 'Wenn Sie keine Registrierung als :role angefordert haben, ignorieren Sie bitte diese E-Mail und kontaktieren Sie unser Support-Team.',
    ],

    'waitlist' => [
        'subject' => 'Willkommen bei QuantumLogic – Beta-Zugang und Warteliste',
        'title' => 'Willkommen bei QuantumLogic',
        'heading' => 'Willkommen bei QuantumLogic',
        'greeting' => 'Hallo :name,',
        'registered' => 'Danke für Ihre Registrierung bei QuantumLogic. Die Registrierung war erfolgreich und Sie stehen nun auf unserer Warteliste für die Beta-Version.',
        'status' => 'QuantumLogic wird laufend weiterentwickelt. Der Zugang wird schrittweise freigeschaltet, beginnend mit bestehenden Kundinnen und Kunden.',
        'notify' => 'Wir melden uns per E-Mail, sobald Ihr Zugang freigeschaltet ist oder es Neuigkeiten gibt.',
        'closing' => 'Danke, dass Sie Teil von QuantumLogic sind.',
    ],

    'payment' => [
        'subject' => 'Zahlung bestätigt',
        'title' => 'Zahlung bestätigt',
        'heading' => 'Zahlung bestätigt',
        'billing_period' => 'Abrechnungszeitraum',
        'amount_paid' => 'Bezahlter Betrag',
        'transaction_id' => 'Transaktions-ID',
        'cta' => 'Zum Dashboard',
    ],

];
