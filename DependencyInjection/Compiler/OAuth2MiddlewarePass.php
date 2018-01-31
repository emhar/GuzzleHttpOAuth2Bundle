<?php

/*
 * This file is part of the FOSRestBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Emhar\GuzzleHttpOAuth2Bundle\DependencyInjection\Compiler;

use GuzzleHttp\HandlerStack;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class OAuth2MiddlewarePass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function process(ContainerBuilder $container)
    {
        $clients = $container->getParameter('emhar_guzzle_http_o_auth2.clients');
        /* @var $clients array */
        foreach ($clients as $serviceId => $oAuthInfo) {
            $definition = $container->getDefinition($serviceId);

            $clientOption = $definition->getArgument(0);
            if (!isset($clientOption['handler'])) {
                $handlerStackId = $serviceId . '_handler_stack';
                $handlerStackDefinition = $this->createHandlerStackDefinition($container, $handlerStackId);
                $handlerStackReference = new Reference($serviceId . '_handler_stack');
                $clientOption['handler'] = $handlerStackReference;
            } else {
                $handlerStackDefinition = $container->getDefinition($clientOption['handler']);
            }
            $this->createOnBeforeMethodCall($container, $handlerStackDefinition, $oAuthInfo);
            $this->createOnFailureMethodCall($container, $handlerStackDefinition, $oAuthInfo);

            $clientOption['auth'] = 'oauth2';
            $definition->replaceArgument(0, $clientOption);
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $id
     * @return Definition
     */
    private function createHandlerStackDefinition(ContainerBuilder $container, $id)
    {
        $handlerStackDefinition = new Definition(HandlerStack::class);
        $handlerStackDefinition->setFactory(array(HandlerStack::class, 'create'));
        $container->addDefinitions(array($id => $handlerStackDefinition));
        return $handlerStackDefinition;
    }

    /**
     * @param ContainerBuilder $container
     * @param Definition $handlerStackDefinition
     * @param array $oAuthInfo
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    private function createOnBeforeMethodCall(ContainerBuilder $container, Definition $handlerStackDefinition, $oAuthInfo)
    {
        $id = $oAuthInfo['middleware_service_id'] . '_on_before_closure';
        $onBeforeDefinition = new Definition('Closure');
        $onBeforeDefinition->setFactory(array(new Reference($oAuthInfo['middleware_service_id']), 'onBefore'));
        $container->addDefinitions(array($id => $onBeforeDefinition));
        $handlerStackDefinition->addMethodCall(
            'push',
            array(new Reference($id))
        );
    }

    /**
     * @param ContainerBuilder $container
     * @param Definition $handlerStackDefinition
     * @param array $oAuthInfo
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    private function createOnFailureMethodCall(ContainerBuilder $container, Definition $handlerStackDefinition, $oAuthInfo)
    {
        $id = $oAuthInfo['middleware_service_id'] . '_on_failure_closure';
        $onFailureDefinition = new Definition(\Closure::class, array(5));
        $onFailureDefinition->setFactory(array(new Reference($oAuthInfo['middleware_service_id']), 'onFailure'));
        $container->addDefinitions(array($id => $onFailureDefinition));
        $handlerStackDefinition->addMethodCall(
            'push',
            array(new Reference($id))
        );
    }
}
