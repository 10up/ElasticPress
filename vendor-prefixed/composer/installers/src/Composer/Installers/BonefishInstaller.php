<?php

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

class BonefishInstaller extends BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array(
        'package'    => 'Packages/{$vendor}/{$name}/'
    );
}
