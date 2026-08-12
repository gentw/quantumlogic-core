<?php

/**
 * Partial on purpose: only the rules this application actually uses.
 * Laravel resolves any missing key through the fallback locale, so the
 * remaining ~90 stock messages come back in English rather than as raw keys.
 */
return [

    'required' => 'Fusha :attribute është e detyrueshme.',
    'email' => ':attribute duhet të jetë një adresë emaili e vlefshme.',
    'unique' => ':attribute është tashmë në përdorim.',
    'confirmed' => 'Konfirmimi i :attribute nuk përputhet.',
    'numeric' => ':attribute duhet të jetë numër.',
    'integer' => ':attribute duhet të jetë numër i plotë.',
    'boolean' => ':attribute duhet të jetë e vërtetë ose e rreme.',
    'date' => ':attribute nuk është datë e vlefshme.',
    'in' => 'Vlera e zgjedhur për :attribute është e pavlefshme.',
    'exists' => 'Vlera e zgjedhur për :attribute është e pavlefshme.',
    'digits' => ':attribute duhet të ketë :digits shifra.',
    'image' => ':attribute duhet të jetë imazh.',
    'mimes' => ':attribute duhet të jetë skedar i tipit: :values.',

    'min' => [
        'numeric' => ':attribute duhet të jetë të paktën :min.',
        'string' => ':attribute duhet të ketë të paktën :min karaktere.',
    ],

    'max' => [
        'numeric' => ':attribute nuk duhet të kalojë :max.',
        'string' => ':attribute nuk duhet të kalojë :max karaktere.',
    ],

];
