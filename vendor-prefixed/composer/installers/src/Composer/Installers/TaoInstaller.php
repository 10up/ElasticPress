<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 31-August-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

/**
 * An installer to handle TAO extensions.
 */
class TaoInstaller extends BaseInstaller
{
    const EXTRA_TAO_EXTENSION_NAME = 'tao-extension-name';

    /** @var array<string, string> */
    protected $locations = array(
        'extension' => '{$name}'
    );
    
    public function inflectPackageVars(array $vars): array
    {
        $extra = $this->package->getExtra();

        if (array_key_exists(self::EXTRA_TAO_EXTENSION_NAME, $extra)) {
            $vars['name'] = $extra[self::EXTRA_TAO_EXTENSION_NAME];
            return $vars;
        }

        $vars['name'] = str_replace('extension-', '', $vars['name']);
        $vars['name'] = str_replace('-', ' ', $vars['name']);
        $vars['name'] = lcfirst(str_replace(' ', '', ucwords($vars['name'])));

        return $vars;
    }
}
