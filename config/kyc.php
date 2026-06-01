<?php

return [

    'connection' => env('KYC_DB_CONNECTION', 'kycsystem'),

    'table' => env('KYC_CLIENTS_TABLE', 'clients'),

    'obligee_type' => env('KYC_OBLIGEE_TYPE', 'obligee'),

    'columns' => [
        'id' => env('KYC_COLUMN_ID', 'id'),
        'client_type' => env('KYC_COLUMN_CLIENT_TYPE', 'client_type'),
        'company_name' => env('KYC_COLUMN_COMPANY_NAME', 'company_name'),
        'address' => env('KYC_COLUMN_ADDRESS', 'address'),
        'business_address' => env('KYC_COLUMN_BUSINESS_ADDRESS', 'business_address'),
        'business_ctm' => env('KYC_COLUMN_BUSINESS_CTM', 'business_ctm'),
        'business_province' => env('KYC_COLUMN_BUSINESS_PROVINCE', 'business_province'),
        'contact_person' => env('KYC_COLUMN_CONTACT_PERSON', 'contact_person'),
        'email' => env('KYC_COLUMN_EMAIL', 'email'),
        'phone_number' => env('KYC_COLUMN_PHONE', 'phone_number'),
    ],

];
