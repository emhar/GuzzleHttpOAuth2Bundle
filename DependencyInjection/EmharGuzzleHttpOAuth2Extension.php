<?php

namespace Emhar\GuzzleHttpOAuth2Bundle\DependencyInjection;

use Emhar\GuzzleHttpOAuth2Bundle\Middleware\OAuthMiddleware;
use Sainsburys\Guzzle\Oauth2\GrantType\ClientCredentials;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class EmharGuzzleHttpOAuth2Extension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $clients = $config['clients'] ?: array();
        foreach ($clients as $serviceId => $oAuthInfo) {
            $oAuthConfig = [
                ClientCredentials::CONFIG_CLIENT_SECRET => $oAuthInfo['client_secret'],
                ClientCredentials::CONFIG_CLIENT_ID => $oAuthInfo['client_id'],
                ClientCredentials::CONFIG_TOKEN_URL => $oAuthInfo['login_url']
            ];

            $clientCredentialId = $serviceId . '_client_credential';
            $clientCredentialDefinition = new Definition(ClientCredentials::class, array(
                new Reference($oAuthInfo['oauth_client_service']),
                $oAuthConfig
            ));
            $clientCredentialDefinition->setPublic(false);

            $middlewareId = $serviceId . '_oauth_middleware';
            $middlewareDefinition = new Definition(OAuthMiddleware::class, array(
                new Reference($oAuthInfo['oauth_client_service']),
                new Reference($clientCredentialId),
                null,
                isset($oAuthInfo['cache_service']) ? new Reference($oAuthInfo['cache_service']) : null
            ));
            $middlewareDefinition->setPublic(false);


            $container->addDefinitions(array(
                $clientCredentialId => $clientCredentialDefinition,
                $middlewareId => $middlewareDefinition
            ));
            $clients[$serviceId]['middleware_service_id'] = $middlewareId;
        }
        $container->setParameter('emhar_guzzle_http_o_auth2.clients', $clients);
    }
}