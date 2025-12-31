<?php

declare(strict_types=1);

return [
    'output' => [
        'base_path' => base_path('postman'),
        'collections_dir' => 'collections',
        'environments_dir' => 'environments',
        'collection_filename' => 'Laravel API.postman_collection.json',
    ],

    'routes' => [
        'api_only' => true,

        // If api_only=true, this is a best-effort filter (looks at "api" middleware and/or /api prefix)
        'include_middleware' => ['api'],
        'exclude_middleware' => ['web'],

        'include_prefixes' => [],
        'exclude_prefixes' => ['telescope', 'horizon'],

        'include_names' => [],
        'exclude_names' => ['/^debugbar\./'],

        'exclude_fallback' => true,
    ],

    'organization' => [
        'group_by' => 'prefix', // prefix|controller|none
        'sort_by' => 'uri',      // uri|method|name
        'sort_direction' => 'asc',
        'folder_depth' => 2,
    ],

    'request_generation' => [
        'infer_body_from_form_request' => true,
        'generate_example_values' => true,
        'parameterize_route_params' => true, // /users/{user} -> /users/{{user}}
        'generate_query_params' => false, // optional future feature
        'include_docs' => false, // optional future feature
    ],

    'auth' => [
        'mode' => 'bearer', // none|bearer|basic
        'include_auth' => true,

        // If detect_from_middleware=true, routes with auth middleware get auth, others get none.
        'detect_from_middleware' => true,
        'auth_middleware' => ['auth', 'auth:api', 'auth:sanctum'],

        'bearer_token_var' => 'authToken',
        'basic_user_var' => 'authUser',
        'basic_pass_var' => 'authPass',
    ],

    'environments' => [
        'Local' => [
            'baseUrl' => 'http://localhost:8000',
            'authToken' => '',
            'authUser' => '',
            'authPass' => '',
        ],
        'Production' => [
            'baseUrl' => 'https://api.example.com',
            'authToken' => '',
            'authUser' => '',
            'authPass' => '',
        ],
    ],

    'tests' => [
        'enabled' => false,
        'default_success_status' => 200,
    ],
];
