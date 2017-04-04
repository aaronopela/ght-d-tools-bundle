GHT D-Tools Bundle
=========================

This bundle simplifies the running of common Symfony development commands using configurations for default options, and adds some other useful options and features.

# Installation

## Get the Composer package

To install with [Composer](https://getcomposer.org/), run `composer require greenhollowtech/ght-d-tools-bundle`.

## Add the GHTDevToolsBundle to your Symfony application

```php
// app/AppKernel.php

    public function registerBundles()
    {
        $bundles = array(
            // your normal bundles ...
        );

        if (strpos($this->getEnvironment(), 'dev') !== false) {
            $bundles = array_merge($bundles, array(
                new GHT\DevToolsBundle\GHTDevToolsBundle(),
                // other dev only bundles ...
            ));
        }

        return $bundles;
    }
```

# Configuration

## Minimum Configuration

```yml
d_tools:
    bundle: ExampleBundle
```

## All Configuration Options

```yml
d_tools:
    # The target bundle or directory
    bundle: ExampleBundle

    # The doctrine:generate:entities command, used by d:ent:refresh
    doctrine_generate_entities:
        defaults:
            # The path where to generate entities when it cannot be guessed
            path: ~
            # Do not backup existing entities files
            no_backup: false

    # The translation:update command, used by d:trans:refresh and d:trans:add
    translation_update:
        defaults:
            # All the locales to be updated at once
            locales: [ 'en' ]
            # Override the default prefix
            prefix: '__'
            # If set, no prefix is added to the translations
            no_prefix: false
            # Override the default output format
            output_format: 'xlf'
            # Should the messages be dumped in the console or should the update be done
            mode: 'force' # or 'dump-messages'
            # Should backup be disabled
            no_backup: false
            # Should clean not found messages
            clean: false
            # Specify the domain to update
            domain: ~

    # The d:trans:add command
    translation_add:
        defaults:
            # The translation domain
            domain: 'messages'
            # The translation locale
            locale: 'en'
            # The translation file format
            file_format: 'xlf'
            # Should the translation refresh be run after adding new translations
            refresh: false
```

# Usage

## Generate Entities

```
bin/console d:ent:refresh
```

## Refresh Translations

Translations are sorted alphabetically, `CDATA` wrapping happens, and missing translations trigger prompts given the set or default prefix.
```
bin/console d:trans:refresh
```

## Add Translations

Just add translation tokens.  Translation text will be prompted on the next refresh.
```
bin/console d:trans:add -t trans.one -t trans.two
```

Translation text can be set simultaneously.
```
bin/console d:trans:add -t trans.one:One -t trans.two:Two\ words -t "trans.three:This works too"
```

If the refresh is not configured as a default, translations for multiple languages can be added before the refresh happens.
```
bin/console d:trans:add -t trans.hello:Hola -l es
bin/console d:trans:add -t trans.hello:Shalom -l he
bin/console d:trans:add -t trans.hello:Hello -r
```
