<?php

namespace Emhar\GuzzleHttpOAuth2Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('emhar_guzzle_http_o_auth2');

        $rootNode
            ->children()
                ->arrayNode('clients')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('oauth_client_service')->end()
                            ->scalarNode('login_url')->end()
                            ->scalarNode('client_id')->end()
                            ->scalarNode('client_secret')->end()
                            ->scalarNode('cache_service')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
