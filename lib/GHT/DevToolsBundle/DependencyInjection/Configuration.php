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
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('d_tools');

        $rootNode
            ->children()
                ->scalarNode('bundle')
                    ->info('The target bundle or directory')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('doctrine_generate_entities')
                    ->info('The doctrine:generate:entities command, used by d:ent:refresh')
                    ->children()
                        ->arrayNode('defaults')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('path')
                                    ->info('The path where to generate entities when it cannot be guessed')
                                ->end()
                                ->booleanNode('no_backup')
                                    ->info('Do not backup existing entities files')
                                    ->defaultFalse()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('translation_update')
                    ->info('The translation:update command, used by d:trans:refresh and d:trans:add')
                    ->children()
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
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('d_trans_add')
                    ->info('The d:trans:add command')
                    ->children()
                        ->arrayNode('defaults')
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
