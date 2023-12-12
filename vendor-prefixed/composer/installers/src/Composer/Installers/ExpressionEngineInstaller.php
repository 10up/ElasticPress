<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 12-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

use Composer\Package\PackageInterface;

class ExpressionEngineInstaller extends BaseInstaller
{
    /** @var array<string, string> */
    private $ee2Locations = array(
        'addon'   => 'system/expressionengine/third_party/{$name}/',
        'theme'   => 'themes/third_party/{$name}/',
    );

    /** @var array<string, string> */
    private $ee3Locations = array(
        'addon'   => 'system/user/addons/{$name}/',
        'theme'   => 'themes/user/{$name}/',
    );

    public function getLocations(string $frameworkType): array
    {
        if ($frameworkType === 'ee2') {
            $this->locations = $this->ee2Locations;
        } else {
            $this->locations = $this->ee3Locations;
        }

        return $this->locations;
    }
}
