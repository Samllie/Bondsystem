<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Composite placeholder templates
    |--------------------------------------------------------------------------
    |
    | Values may contain nested [[Placeholder Name]] tokens. They are resolved
    | recursively by PlaceholderRenderer before DOCX generation.
    |
    */

    'confirmation_city' => 'City of Makati',

    'jurat_templates' => [
        'government_bold' => 'SUBSCRIBED AND SWORN',
        'government_rest_before_date' => 'to before me this ',
        'government_rest_before_city' => ' at the ',
        'government_rest_before_tin' => ', affiant exhibited to me his/her Taxpayer’s Identification No. ',
        'government_rest_after_tin' => '.',
    ],

    'endorsement_template' => 'W/ENDT.NO. [[Endorsement No.]]',

    'placeholder_renderer' => [
        'max_passes' => 5,
    ],

];
