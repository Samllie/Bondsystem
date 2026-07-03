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

    'jurat_templates' => [
        'government_bold' => 'SUBSCRIBED AND SWORN',
        'government_rest' => 'to before me this [[Date in words]] at Makati city, affiant exhibited to me his/her Taxpayer’s Identification No. [[Tin]].',
    ],

    'endorsement_template' => 'W/ENDT.NO. [[Endorsement No.]]',

    'placeholder_renderer' => [
        'max_passes' => 5,
    ],

];
