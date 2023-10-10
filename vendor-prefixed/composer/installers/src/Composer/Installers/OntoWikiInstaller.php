<?php
/**
 * @license MIT
 *
 * Modified by Taylor Lovett on 10-October-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace ElasticPress\Vendor_Prefixed\Composer\Installers;

class OntoWikiInstaller extends BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array(
        'extension' => 'extensions/{$name}/',
        'theme' => 'extensions/themes/{$name}/',
        'translation' => 'extensions/translations/{$name}/',
    );

    /**
     * Format package name to lower case and remove ".ontowiki" suffix
     */
    public function inflectPackageVars(array $vars): array
    {
        $vars['name'] = strtolower($vars['name']);
        $vars['name'] = $this->pregReplace('/.ontowiki$/', '', $vars['name']);
        $vars['name'] = $this->pregReplace('/-theme$/', '', $vars['name']);
        $vars['name'] = $this->pregReplace('/-translation$/', '', $vars['name']);

        return $vars;
    }
}
