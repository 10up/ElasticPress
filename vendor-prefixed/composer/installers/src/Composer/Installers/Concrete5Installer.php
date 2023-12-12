<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 12-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

class Concrete5Installer extends BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array(
        'core'       => 'concrete/',
        'block'      => 'application/blocks/{$name}/',
        'package'    => 'packages/{$name}/',
        'theme'      => 'application/themes/{$name}/',
        'update'     => 'updates/{$name}/',
    );
}
