<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 12-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

/**
 * Class DolibarrInstaller
 *
 * @package Composer\Installers
 * @author  Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 */
class DolibarrInstaller extends BaseInstaller
{
    //TODO: Add support for scripts and themes
    /** @var array<string, string> */
    protected $locations = array(
        'module' => 'htdocs/custom/{$name}/',
    );
}
