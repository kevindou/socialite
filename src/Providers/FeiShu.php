<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\User;

/**
 * @see https://open.feishu.cn/document/uQjL04CN/ucDOz4yN4MjL3gzM
 */
class FeiShu extends Base
{
    public const NAME = 'feishu';
    protected string $baseUrl = 'https://open.feishu.cn/open-apis/';
    protected string $expiresInKey = 'refresh_expires_in';
    protected bool $isInternalApp = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->isInternalApp = $this->config->get('kind_of_app', 'internal') == 'internal' ? true : false
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . 'authen/v1/index');
    }

    protected function getCodeFields(): array
    {
        return [
            'redirect_uri' => $this->redirectUrl,
            'app_id' => $this->getClientId(),
        ];
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl . 'authen/v1/access_token';
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function tokenFromCode(string $code): array
    {
        return $this->normalizeAccessTokenResponse($this->getTokenFromCode($code));
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws AuthorizeFailedException
     *
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getTokenFromCode(string $code): array
    {
        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'json' => [
                    'app_access_token' => $this->getAppAccessToken(),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );
        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['data'])) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }

        return $this->normalizeAccessTokenResponse($response['data']);
    }

    /**
     * @param  string  $token
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/authen/v1/user_info',
            [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
                'query' => array_filter(
                    [
                        'user_access_token' => $token,
                    ]
                ),
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['data'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['data'];
    }

    /**
     * @param array $user
     *
     * @return User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User(
            [
                'id' => $user['user_id'] ?? null,
                'name' => $user['name'] ?? null,
                'nickname' => $user['name'] ?? null,
                'avatar' => $user['avatar_url'] ?? null,
                'email' => $user['email'] ?? null,
            ]
        );
    }

    public function withInternalAppMode(): self
    {
        $this->isInternalApp = true
        return $this
    }

    public function withDefaultMode(): self
    {
        $this->isInternalApp = false
        return $this
    }

    /**
     * set 'app_ticket' in config
     */
    public function withAppTicket(string $appTicket): self
    {
        $this->config->set('app_ticket', appTicket)
        return $this
    }

    /**
     * get app_access_token
     * 应用维度授权凭证，开放平台可据此识别调用方的应用身份
     * 分内建和自建
     */
    protected function getAppAccessToken(): string
    {
        $url = $this->baseUrl . 'auth/v3/app_access_token';
        $params = [
            'json' => [
                'app_id' => $this->config->get('client_id'),
                'app_secret' => $this->config->get('client_secret'),
                'app_ticket' => $this->config->get('app_ticket'),
            ],
        ];

        if ($this->isInternalApp) {
            $url = $this->baseUrl . 'auth/v3/app_access_token/internal';
            $params = [
                'json' => [
                    'app_id' => $this->config->get('client_id'),
                    'app_secret' => $this->config->get('client_secret'),
                ],
            ];
        }

        if (!$this->isInternalApp && !$this->config->has('app_ticket')) {
            throw new AuthorizeFailedException('You are using defualt mode, please config \'app_ticket\' frist');
        }

        $response = $this->getHttpClient()->post($url, $params);
        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['app_access_token'])) {
            throw new AuthorizeFailedException('Invalid \'app_access_token\' response', $response);
        }

        return $response['app_access_token'];
    }

    /**
     * get tenant_access_token
     * 应用的企业授权凭证，开放平台据此识别调用方的应用身份和企业身份
     * 分内建和自建
     */
    protected function getTenantAccessToken() : string
    {
        $url = $this->baseUrl . 'auth/v3/tenant_access_token';
        $params = [
            'json' => [
                'app_id' => $this->config->get('client_id'),
                'app_secret' => $this->config->get('client_secret'),
                'app_ticket' => $this->config->get('app_ticket'),
            ],
        ];

        if ($this->isInternalApp) {
            $url = $this->baseUrl . 'auth/v3/tenant_access_token/internal';
            $params = [
                'json' => [
                    'app_id' => $this->config->get('client_id'),
                    'app_secret' => $this->config->get('client_secret'),
                ],
            ];
        } 

        if (!$this->isInternalApp && !$this->config->has('app_ticket')) {
            throw new AuthorizeFailedException('You are using defualt mode, please config \'app_ticket\' frist');
        }
            
        $response = $this->getHttpClient()->post($url, $params);
        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['tenant_access_token'])) {
            throw new AuthorizeFailedException('Invalid tenant_access_token response', $response);
        }
        return $response['tenant_access_token'];
    }
}
