<?php

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

class DecibelInstaller extends BaseInstaller
{
    /** @var array */
    /** @var array<string, string> */
    protected $locations = array(
        'app'    => 'app/{$name}/',
    );
}
