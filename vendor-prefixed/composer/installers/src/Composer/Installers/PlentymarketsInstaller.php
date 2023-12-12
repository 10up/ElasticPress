<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 12-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

class PlentymarketsInstaller extends BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array(
        'plugin'   => '{$name}/'
    );

    /**
     * Remove hyphen, "plugin" and format to camelcase
     */
    public function inflectPackageVars(array $vars): array
    {
        $nameBits = explode("-", $vars['name']);
        foreach ($nameBits as $key => $name) {
            $nameBits[$key] = ucfirst($name);
            if (strcasecmp($name, "Plugin") == 0) {
                unset($nameBits[$key]);
            }
        }
        $vars['name'] = implode('', $nameBits);

        return $vars;
    }
}
