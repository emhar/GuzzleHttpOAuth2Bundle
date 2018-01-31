<?php

namespace Emhar\GuzzleHttpOAuth2Bundle;

use Emhar\GuzzleHttpOAuth2Bundle\DependencyInjection\Compiler\OAuth2MiddlewarePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EmharGuzzleHttpOAuth2Bundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new OAuth2MiddlewarePass());
    }
}