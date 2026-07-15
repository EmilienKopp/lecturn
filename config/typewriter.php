<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Source groups
    |--------------------------------------------------------------------------
    | Each key maps to a source type scanned for TypeScript-exportable classes.
    | 'directories' are relative to app_path().
    | 'include'/'exclude' accept glob-style class name patterns (plain groups only).
    |
    | Two source group types:
    |   Plain groups (value_objects, dtos) — scanned with typescript-transformer's
    |   native engine; handles laravel-data Data classes, plain VOs/DTOs, and
    |   DataPacket subclasses found in these dirs.
    |
    |   Packets group — dedicated directory for DataPacket subclasses and
    |   BackedEnum routing types used as source/target.
    */
    'sources' => [
        'value_objects' => [
            'directories' => ['Domain/ValueObjects', 'Domain/Presentation/ValueObjects'],
            'include' => ['*'],
            'exclude' => [],
        ],
        'dtos' => [
            'directories' => ['Domain/DTOs', 'Http/Resources'],
            'include' => ['*'],
            'exclude' => [],
        ],
        'packets' => [
            'directories' => ['Domain/Packets', 'Http/Packets'],
            'enums'       => ['Domain/Enums'],
            'enabled'     => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output paths
    |--------------------------------------------------------------------------
    | Keys match the source group keys above, plus 'barrel' for the index file.
    | null values resolve to sensible defaults under resource_path('js/types/').
    */
    'output' => [
        'value_objects' => resource_path('js/types/generated/value-objects.ts'),
        'dtos'          => resource_path('js/types/generated/dtos.ts'),
        'packets'       => resource_path('js/types/generated/packets.ts'),
        // The default barrel path would overwrite the hand-written
        // resources/js/types/index.ts app barrel.
        'barrel'        => resource_path('js/types/generated/index.ts'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TypeScript asset publish path
    |--------------------------------------------------------------------------
    | Where vendor:publish --tag=typewriter-ts drops DataPacket.ts.
    | null resolves to resource_path('js/utils/DataPacket.ts').
    */
    'ts_asset_path' => null,
];
