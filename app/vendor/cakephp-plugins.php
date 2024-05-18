<?php
$baseDir = dirname(dirname(__FILE__));

return [
    'plugins' => [
        'Bake' => $baseDir . '/vendor/cakephp/bake/',
        'Cake/TwigView' => $baseDir . '/vendor/cakephp/twig-view/',
        'CoreAssigner' => $baseDir . '/plugins/CoreAssigner/',
        'CoreEnroller' => $baseDir . '/plugins/CoreEnroller/',
        'CoreJob' => $baseDir . '/plugins/CoreJob/',
        'CoreServer' => $baseDir . '/plugins/CoreServer/',
        'DebugKit' => $baseDir . '/vendor/cakephp/debug_kit/',
        'Migrations' => $baseDir . '/vendor/cakephp/migrations/',
        'TestWidget' => $baseDir . '/plugins/TestWidget/',
    ],
];
