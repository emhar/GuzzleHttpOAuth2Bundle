<?php

namespace Emhar\GuzzleHttpOAuth2Bundle\Middleware;

use GuzzleHttp\ClientInterface;
use M6Web\Bundle\GuzzleHttpBundle\Cache\CacheInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Sainsburys\Guzzle\Oauth2\AccessToken;
use Sainsburys\Guzzle\Oauth2\GrantType\ClientCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\GrantTypeBase;
use Sainsburys\Guzzle\Oauth2\GrantType\GrantTypeInterface;
use Sainsburys\Guzzle\Oauth2\GrantType\PasswordCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\RefreshTokenGrantTypeInterface;
use Sainsburys\Guzzle\Oauth2\Middleware\OAuthMiddleware as BaseOAuthMiddleware;

class OAuthMiddleware extends BaseOAuthMiddleware
{
    /**
     * @var CacheInterface|null
     */
    protected $cache;

    /**
     * Create a new Oauth2 subscriber.
     *
     * @param ClientInterface $client
     * @param GrantTypeInterface $grantType
     * @param RefreshTokenGrantTypeInterface $refreshTokenGrantType
     * @param CacheInterface $cache
     */
    public function __construct(
        ClientInterface $client,
        GrantTypeInterface $grantType = null,
        RefreshTokenGrantTypeInterface $refreshTokenGrantType = null,
        CacheInterface $cache = null
    )
    {
        parent::__construct($client, $grantType, $refreshTokenGrantType);
        $this->cache = $cache;
    }

    public function onFailure($limit)
    {
        $calls = 0;

        return function (callable $handler) use (&$calls, $limit) {
            return function (RequestInterface $request, array $options) use ($handler, &$calls, $limit) {
                /* @var PromiseInterface */
                $promise = $handler($request, $options);

                return $promise->then(
                    function (ResponseInterface $response) use ($request, $options, &$calls, $limit) {
                        if (
                            $response->getStatusCode() == 401 &&
                            isset($options['auth']) &&
                            'oauth2' == $options['auth'] &&
                            $this->grantType instanceof GrantTypeInterface &&
                            $this->grantType->getConfigByName(GrantTypeBase::CONFIG_TOKEN_URL) != $request->getUri()->getPath()
                        ) {
                            if($calls > 0 && $this->cache){
                                $this->cache->remove($this->getTokenKey());
                                $this->accessToken = null;
                            }
                            ++$calls;
                            if ($calls > $limit) {
                                return $response;
                            }

                            if ($token = $this->getAccessToken()) {
                                $response = $this->client->send($request->withHeader('Authorization', 'Bearer '.$token->getToken()), $options);
                            }
                        }

                        return $response;
                    }
                );
            };
        };
    }


    /**
     * Get the access token.
     *
     * @return AccessToken|null Oauth2 access token
     */
    public function getAccessToken()
    {
        if ($this->accessToken instanceof AccessToken && !$this->accessToken->isExpired()) {
            return $this->accessToken;
        }
        if ($this->cache && $this->grantType) {
            $token = json_decode($this->cache->get($this->getTokenKey()), true);
            /* @var $token AccessToken|null */
            if($token && !empty($token)){
                $this->accessToken = new AccessToken(
                    $token['token'],
                    $token['type'],
                    $token['data']
                );
            }
        }
        return parent::getAccessToken();
    }

    /**
     * Get a new access token.
     *
     * @return AccessToken|null
     */
    protected function acquireAccessToken()
    {
        parent::acquireAccessToken();
        if ($this->cache && $this->accessToken) {
            $this->cache->set(
                $this->getTokenKey(),
                json_encode(array(
                    'token' =>$this->accessToken->getToken(),
                    'type' => $this->accessToken->getType(),
                    'data' => $this->accessToken->getData()
                ),
                $this->accessToken->getExpires()->getTimestamp() - (new \DateTime())->getTimestamp()
            ));
        }
        return $this;
    }

    /**
     * @return string
     */
    protected function getTokenKey()
    {
        $key = 'oauth_token';
        if($this->grantType instanceof ClientCredentials){
            $key .= '_'.$this->grantType->getConfigByName(GrantTypeBase::CONFIG_CLIENT_ID);
        }
        return $key;
    }
}