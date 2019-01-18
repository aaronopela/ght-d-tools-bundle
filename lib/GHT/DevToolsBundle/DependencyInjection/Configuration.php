<?php

namespace GHT\DevToolsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines configuration settings.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('d_tools');
        $rootNode = method_exists($treeBuilder, 'getRootNode') ? $treeBuilder->getRootNode() : $treeBuilder->root('d_tools');

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('bundle')
                    ->info('The target bundle or directory')
                    ->defaultNull()
                ->end()
                ->scalarNode('path')
                    ->info('The bundle path when it cannot be guessed (i.e. parent of the Resources directory)')
                    ->defaultNull()
                ->end()
                ->scalarNode('translations_path')
                    ->info('The translations path when it cannot be guessed (i.e. not in a Resources directory)')
                    ->defaultNull()
                ->end()
                ->arrayNode('translation_update')
                    ->info('The translation:update command, used by d:trans:refresh and d:trans:add')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('primary_locale')
                            ->info('If set, all other locales will not auto-generate new translation units')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('conversions')
                            ->info('If both types of a conversion are true, defaults to the "as_char" conversion and the "as_entity" conversion is ignored')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('amp_as_char')
                                    ->info('Should ampersand HTML entities be converted to characters')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('amp_as_entity')
                                    ->info('Should bare ampersands be converted to HTML entities (ignores ampersands in HTML entities)')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('ltgt_as_char')
                                    ->info('Should less than / greater than HTML entities be converted to characters')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('ltgt_as_entity')
                                    ->info('Should less than / greater than characters be converted to HTML entities')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('nbsp_as_char')
                                    ->info('Should non-breaking space HTML entities be converted to character')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('nbsp_as_entity')
                                    ->info('Should non-breaking space characters be converted to HTML entities')
                                    ->defaultFalse()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('defaults')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('locales')
                                    ->info('All the locales to be updated at once')
                                    ->prototype('scalar')->end()
                                    ->defaultValue(array('en'))
                                ->end()
                                ->scalarNode('prefix')
                                    ->info('Override the default prefix')
                                    ->defaultValue('__')
                                ->end()
                                ->booleanNode('no_prefix')
                                    ->info('If set, no prefix is added to the translations')
                                    ->defaultFalse()
                                ->end()
                                ->scalarNode('output_format')
                                    ->info('Override the default output format')
                                    ->defaultValue('xlf')
                                ->end()
                                ->enumNode('mode')
                                    ->info('Should the messages be dumped in the console or should the update be done')
                                    ->values(array('dump-messages', 'force'))
                                    ->defaultValue('force')
                                ->end()
                                ->booleanNode('no_backup')
                                    ->info('Should backup be disabled')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('clean')
                                    ->info('Should clean not found messages')
                                    ->defaultFalse()
                                ->end()
                                ->scalarNode('domain')
                                    ->info('Specify the domain to update')
                                    ->defaultValue('messages')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('translation_add')
                    ->info('The d:trans:add command')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('defaults')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('domain')
                                    ->info('The translation domain')
                                    ->defaultValue('messages')
                                ->end()
                                ->scalarNode('locale')
                                    ->info('The translation locale')
                                    ->defaultValue('en')
                                ->end()
                                ->scalarNode('file_format')
                                    ->info('The translation file format')
                                    ->defaultValue('xlf')
                                ->end()
                                ->booleanNode('refresh')
                                    ->info('Should the translation refresh be run after adding new translations')
                                    ->defaultFalse()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
