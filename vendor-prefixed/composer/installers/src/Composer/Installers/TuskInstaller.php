<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 10-October-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

/**
 * Composer installer for 3rd party Tusk utilities
 * @author Drew Ewing <drew@phenocode.com>
 */
class TuskInstaller extends BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array(
        'task'    => '.tusk/tasks/{$name}/',
        'command' => '.tusk/commands/{$name}/',
        'asset'   => 'assets/tusk/{$name}/',
    );
}
