<?php
$baseDir = dirname(dirname(__FILE__));

return [
    'plugins' => [
        'Authentication' => $baseDir . '/vendor/cakephp/authentication/',
        'Bake' => $baseDir . '/vendor/cakephp/bake/',
        'Cake/TwigView' => $baseDir . '/vendor/cakephp/twig-view/',
        'CoreApi' => $baseDir . '/plugins/CoreApi/',
        'CoreAssigner' => $baseDir . '/plugins/CoreAssigner/',
        'CoreDetour' => $baseDir . '/plugins/CoreDetour/',
        'CoreEnroller' => $baseDir . '/plugins/CoreEnroller/',
        'CoreJob' => $baseDir . '/plugins/CoreJob/',
        'CoreNormalizer' => $baseDir . '/plugins/CoreNormalizer/',
        'CoreServer' => $baseDir . '/plugins/CoreServer/',
        'DebugKit' => $baseDir . '/vendor/cakephp/debug_kit/',
        'EnvSource' => $baseDir . '/plugins/EnvSource/',
        'LdapConnector' => $baseDir . '/plugins/LdapConnector/',
        'Migrations' => $baseDir . '/vendor/cakephp/migrations/',
        'OrcidSource' => $baseDir . '/plugins/OrcidSource/',
        'SshKeyAuthenticator' => $baseDir . '/plugins/SshKeyAuthenticator/',
        'TermsAgreer' => $baseDir . '/plugins/TermsAgreer/',
        'TestWidget' => $baseDir . '/plugins/TestWidget/',
    ],
];
