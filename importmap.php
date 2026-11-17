<?php

/**
 * The import map of the application (Asset Mapper). The pages of the bundle load no script: the two
 * entries are the DaisyUI plugin and its theme helper that assets/styles/app.css hands to Tailwind,
 * installed into assets/vendor by "importmap:install" and pinned here.
 *
 * @return array<string, array{version: string, type?: 'js'|'css'|'json'}>
 */
return [
    'daisyui' => ['version' => '5.7.38'],
    'daisyui/theme' => ['version' => '5.7.38'],
];
