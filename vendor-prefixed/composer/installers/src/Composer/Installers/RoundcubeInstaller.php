<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 10-October-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

class RoundcubeInstaller extends BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array(
        'plugin' => 'plugins/{$name}/',
    );

    /**
     * Lowercase name and changes the name to a underscores
     */
    public function inflectPackageVars(array $vars): array
    {
        $vars['name'] = strtolower(str_replace('-', '_', $vars['name']));

        return $vars;
    }
}
