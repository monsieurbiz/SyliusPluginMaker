<?php

namespace MonsieurBiz\SyliusTemplateMaker;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use Twig\Environment;
use function Castor\finder;
use function Castor\fs;
use function Castor\io;
use function Castor\capture;
use function Castor\request;
use function Castor\run;

require_once __DIR__ . '/vendor/autoload.php';

#[AsTask(name: 'create', namespace: '', description: 'Create a new plugin')]
function create(
    #[AsArgument(description: 'Example: Sylius Bazinga Plugin')]
    ?string $defaultPluginLiteralName = null
): void {
    $vars = (object) [
        // Sylius Bazinga Plugin
        'pluginLiteralName' => $pluginLiteralName = io()->ask('What is the literal name of your plugin? (example Sylius Bazinga Plugin)', $defaultPluginLiteralName),
        // SyliusBazingaPlugin
        'pluginPascalCaseName' => $pluginPascalCaseName = io()->ask('What is the Pascal case name of your plugin?', str_replace(' ', '', $pluginLiteralName)),
        // MonsieurBizSyliusBazingaPlugin
        'pluginPascalCaseFullName' => $pluginPascalCaseFullName = io()->ask('What is the full Pascal case name of your plugin? (including namespace)', 'MonsieurBiz' . $pluginPascalCaseName),
        // sylius-bazinga-plugin
        'pluginKebabCaseName' => $pluginKebabCaseName = capture('echo ' . $pluginPascalCaseName . ' | sed -r "s/([A-Z])/-\\1/g" | tr "[:upper:]" "[:lower:]" | sed -r "s/^-//"'),
        // sylius_bazinga_plugin
        'pluginSnakeCaseName' => $pluginSnakeCaseName = capture('echo ' . $pluginPascalCaseName . ' | sed -r "s/([A-Z])/_\\1/g" | tr "[:upper:]" "[:lower:]" | sed -r "s/^_//"'),
        // Sylius Bazinga Plugin's description
        'pluginShortDescription' => io()->ask('What is the short description of your plugin?'),
        // Monsieur Biz
        'pluginAuthor' => io()->ask('Who is the author of your plugin?', 'Monsieur Biz'),
        // sylius@monsieurbiz.com
        'pluginAuthorEmail' => io()->ask('What is the email of the author of your plugin?', 'sylius@monsieurbiz.com'),
        // MonsieurBiz\SyliusBazingaPlugin
        'pluginNamespace' => $pluginNamespace = io()->ask('What is the namespace of your plugin?', 'MonsieurBiz\\' . $pluginPascalCaseName),
        // MonsieurBiz\\SyliusBazingaPlugin\\
        'pluginPsrNamespace' => str_replace('\\', '\\\\', $pluginNamespace . '\\'),
        // MIT
        'pluginLicense' => io()->ask('What is the license of your plugin? You can also use Proprietary license.', 'MIT'),
        // monsieurbiz/SyliusBazingaPlugin
        'pluginGithub' => io()->ask('What is the github repository of your plugin?', 'monsieurbiz/' . $pluginPascalCaseName),
        // monsieurbiz/sylius-bazinga-plugin
        'pluginComposerIdentifier' => io()->ask('What is the composer identifier of your plugin?', 'monsieurbiz/' . $pluginKebabCaseName),
        // monsieurbiz_sylius_bazinga_plugin
        'configFilesFilename' => io()->ask('What is the filename of your config files?', 'monsieurbiz_' . $pluginSnakeCaseName),
        // true|false
        'useMonsieurBizRecipes' => io()->confirm('Do you want to use Monsieur Biz\' recipes?', true),
        // true|false
        'isMonsieurBizPlugin' => io()->confirm('Is this a Monsieur Biz plugin?', true),
        // 1.12
        'minimumSyliusVersion' => io()->ask('What is the minimum Sylius version of your plugin?', '1.12'),
        // 1.13
        'defaultSyliusVersion' => io()->ask('What is the default Sylius version of your plugin?', '1.13'),
        // 1.14
        'limitSyliusVersion' => io()->ask('What is the limit Sylius version of your plugin? (Current Sylius version +1)', '1.14'),
        // 8.2
        'defaultPhpVersion' => io()->ask('What is the default PHP version of your plugin?', '8.2'),
        // MonsieurBizSyliusBazingaExtension
        'extensionName' => str_replace('Plugin', 'Extension', $pluginPascalCaseFullName),
        // monsieurbiz_bazinga
        'extensionAlias' => io()->ask('What is the alias of your extension?', 'monsieurbiz_' . strtr($pluginSnakeCaseName, ['_plugin' => '', 'sylius_' => ''])),
        // true|false
        'hasMigrations' => io()->confirm('Does your plugin have (or will have) migrations?', false),
    ];

    io()->writeln('Let\'s create your plugin now!');
    createPluginsFromVars($vars);
}

function createPluginsFromVars(object $vars): void
{
    // We need twig as a template engine
    $twig = new Environment(new \Twig\Loader\FilesystemLoader(__DIR__ . '/PluginTemplate'));

    if (fs()->exists($vars->pluginPascalCaseName)) {
        io()->warning('The plugin already exists.');
        if (!io()->confirm('Do you want to remove it?', false)) {
            return;
        }
        run('rm -rf ' . $vars->pluginPascalCaseName);
    }

    io()->progressStart();

    // Create directory
    fs()->mkdir($vars->pluginPascalCaseName);
    io()->progressAdvance();

    $templates = finder()->in(__DIR__ . '/PluginTemplate')->files();

    $formatFilename = fn ($filename) => strtr($filename, [
        '.twig' => '',
        'config_files_filename' => $vars->configFilesFilename,
        'extension_name' => $vars->extensionName,
        'plugin_pascal_case_full_name' => $vars->pluginPascalCaseFullName,
        '___' => '',
    ]);

    foreach ($templates->getIterator() as $file) {
        if (!empty($file->getRelativePath())) {
            fs()->mkdir($vars->pluginPascalCaseName . '/' . $formatFilename($file->getRelativePath()));
        }
        if ($file->getExtension() === 'twig') {
            fs()->appendToFile(
                $vars->pluginPascalCaseName . '/' . $formatFilename($file->getRelativePathname()),
                $twig->render($file->getRelativePathname(),
                (array) $vars
            ));
        } else {
            fs()->copy($file->getPathname(), $vars->pluginPascalCaseName . '/' . $formatFilename($file->getRelativePathname()));
        }
        io()->progressAdvance();
    }

    // No Migrations
    if (!$vars->hasMigrations) {
        fs()->remove($vars->pluginPascalCaseName . '/src/Migrations');
    }

    // Last but not least, create the license
    $licenceName = strtolower($vars->pluginLicense);
    if ($licenceName === 'proprietary') {
        $licenseContent = 'All rights reserved. This plugin is proprietary.';
    } else {
        $license = request('GET', 'https://raw.githubusercontent.com/github/choosealicense.com/gh-pages/_licenses/' . $licenceName . '.txt')->getContent();
        $licenseContent = trim(explode('---', $license)[2]);
        $licenseContent = strtr(
            $licenseContent,
            [
                '[year]' => date('Y'),
                '[fullname]' => $vars->pluginAuthor,
            ]
        );
    }
    fs()->appendToFile($vars->pluginPascalCaseName . '/LICENSE', $licenseContent);
    io()->progressAdvance();

    io()->progressFinish();

    io()->success('Your plugin has been initialized!');
}
