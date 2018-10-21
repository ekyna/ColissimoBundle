<?php

namespace Ekyna\Bundle\ColissimoBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ekyna_colissimo');

        $rootNode
            ->children()
                ->scalarNode('login')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('password')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('cache')
                    ->defaultTrue()
                ->end()
                ->booleanNode('debug')
                    ->defaultValue('%kernel.debug%')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
