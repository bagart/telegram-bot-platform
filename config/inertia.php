<?php

// Module Inertia page sources live inside their packages (declared as
// frontendPages in config/tg_modules.php) — resolve them alongside the host
// pages so component existence checks pass for module routes in tests.
$modulePagePaths = array_values(array_filter(
    glob(base_path('misc/BAGArt/*/resources/js/pages')) ?: [],
));

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ensure_pages_exist' => false,

    'page_paths' => array_merge(
        [resource_path('js/pages')],
        $modulePagePaths,
    ),

    'page_extensions' => [
        'js',
        'jsx',
        'svelte',
        'ts',
        'tsx',
        'vue',
    ],

    'ssr' => [
        'enabled' => true,
        'url' => 'http://127.0.0.1:13714',
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => array_merge(
            [resource_path('js/pages')],
            $modulePagePaths,
        ),

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

];
