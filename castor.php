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
        'pluginLiteralName' => $pluginLiteralName = io()->ask('What is the literal name of your plugin? (example Sylius Bazinga Plugin)', $defaultPluginLiteralName),
        'pluginPascalCaseName' => $pluginPascalCaseName = io()->ask('What is the Pascal case name of your plugin?', str_replace(' ', '', $pluginLiteralName)),
        'pluginPascalCaseFullName' => $pluginPascalCaseFullName = io()->ask('What is the full Pascal case name of your plugin? (including namespace)', 'MonsieurBiz' . $pluginPascalCaseName),
        'pluginKebabCaseName' => $pluginKebabCaseName = capture('echo ' . $pluginPascalCaseName . ' | sed -r "s/([A-Z])/-\\1/g" | tr "[:upper:]" "[:lower:]" | sed -r "s/^-//"'),
        'pluginSnakeCaseName' => $pluginSnakeCaseName = capture('echo ' . $pluginPascalCaseName . ' | sed -r "s/([A-Z])/_\\1/g" | tr "[:upper:]" "[:lower:]" | sed -r "s/^_//"'),
        'pluginShortDescription' => io()->ask('What is the short description of your plugin?'),
        'pluginAuthor' => io()->ask('Who is the author of your plugin?', 'Monsieur Biz'),
        'pluginAuthorEmail' => io()->ask('What is the email of the author of your plugin?', 'sylius@monsieurbiz.com'),
        'pluginNamespace' => $pluginNamespace = io()->ask('What is the namespace of your plugin?', 'MonsieurBiz\\' . $pluginPascalCaseName),
        'pluginPsrNamespace' => str_replace('\\', '\\\\', $pluginNamespace . '\\'),
        'pluginLicense' => io()->ask('What is the license of your plugin?', 'MIT'),
        'pluginGithub' => io()->ask('What is the github repository of your plugin?', 'monsieurbiz/' . $pluginPascalCaseName),
        'pluginComposerIdentifier' => io()->ask('What is the composer identifier of your plugin?', 'monsieurbiz/' . $pluginKebabCaseName),
        'configFilesFilename' => io()->ask('What is the filename of your config files?', 'monsieurbiz_' . $pluginSnakeCaseName),
        'useMonsieurBizRecipes' => io()->confirm('Do you want to use Monsieur Biz\' recipes?', true),
        'isMonsieurBizPlugin' => io()->confirm('Is this a Monsieur Biz plugin?', true),
        'defaultSyliusVersion' => io()->ask('What is the default Sylius version of your plugin?', '1.12'),
        'defaultPhpVersion' => io()->ask('What is the default PHP version of your plugin?', '8.2'),
        'extensionName' => str_replace('Plugin', 'Extension', $pluginPascalCaseFullName),
        'extensionAlias' => io()->ask('What is the alias of your extension?', 'monsieurbiz_' . strtr($pluginSnakeCaseName, ['_plugin' => '', 'sylius_' => ''])),
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
    $license = request('GET', 'https://raw.githubusercontent.com/github/choosealicense.com/gh-pages/_licenses/' . strtolower($vars->pluginLicense) . '.txt')->getContent();
    $licenseContent = trim(explode('---', $license)[2]);
    $licenseContent = strtr(
        $licenseContent,
        [
            '[year]' => date('Y'),
            '[fullname]' => $vars->pluginAuthor,
        ]
    );
    fs()->appendToFile($vars->pluginPascalCaseName . '/LICENSE', $licenseContent);
    io()->progressAdvance();

    io()->progressFinish();

    io()->success('Your plugin has been initialized!');
}
