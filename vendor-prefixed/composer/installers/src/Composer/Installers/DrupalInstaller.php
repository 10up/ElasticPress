<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 01-November-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

class DrupalInstaller extends BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array(
        'core'             => 'core/',
        'module'           => 'modules/{$name}/',
        'theme'            => 'themes/{$name}/',
        'library'          => 'libraries/{$name}/',
        'profile'          => 'profiles/{$name}/',
        'database-driver'  => 'drivers/lib/Drupal/Driver/Database/{$name}/',
        'drush'            => 'drush/{$name}/',
        'custom-theme'     => 'themes/custom/{$name}/',
        'custom-module'    => 'modules/custom/{$name}/',
        'custom-profile'   => 'profiles/custom/{$name}/',
        'drupal-multisite' => 'sites/{$name}/',
        'console'          => 'console/{$name}/',
        'console-language' => 'console/language/{$name}/',
        'config'           => 'config/sync/',
    );
}
