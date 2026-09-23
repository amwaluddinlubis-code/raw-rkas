@extends('errors.layout', [
    'code' => '429',
    'title' => 'Terlalu banyak permintaan.',
    'hint' => 'Tunggu sejenak sebelum mencoba lagi. Sistem membatasi permintaan beruntun demi stabilitas.',
])
