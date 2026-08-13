<?php

use Illuminate\Support\Facades\Route;
use Apirelio\Laravel\Middleware\TrackApiRequest;

Route::middleware(TrackApiRequest::class)
    ->get('/api/invoices/{invoice}', static fn (string $invoice): array => [
        'id' => $invoice,
        'status' => 'paid',
    ]);

