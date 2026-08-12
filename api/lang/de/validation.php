<?php

/**
 * Partial on purpose: only the rules this application actually uses.
 * Laravel resolves any missing key through the fallback locale, so the
 * remaining ~90 stock messages come back in English rather than as raw keys.
 */
return [

    'required' => 'Das Feld :attribute ist erforderlich.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'boolean' => ':attribute muss wahr oder falsch sein.',
    'date' => ':attribute ist kein gültiges Datum.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'digits' => ':attribute muss :digits Ziffern haben.',
    'image' => ':attribute muss ein Bild sein.',
    'mimes' => ':attribute muss eine Datei des Typs :values sein.',

    'min' => [
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen haben.',
    ],

    'max' => [
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen haben.',
    ],

];
