<?php

namespace BnplPartners\Factoring004\OAuth;

use BadMethodCallException;
use BnplPartners\Factoring004\AbstractTestCase;
use BnplPartners\Factoring004\Exception\OAuthException;
use BnplPartners\Factoring004\Transport\GuzzleTransport;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

class OAuthTokenManagerTest extends AbstractTestCase
{
    /**
     * @return void
     */
    public function testWithEmptyBaseUri()
    {
        $this->expectException(InvalidArgumentException::class);

        new OAuthTokenManager('', 'test', 'test');
    }

    /**
     * @return void
     */
    public function testWithEmptyUsername()
    {
        $this->expectException(InvalidArgumentException::class);

        new OAuthTokenManager('http://example.com', '', 'test');
    }

    /**
     * @return void
     */
    public function testWithEmptyPassword()
    {
        $this->expectException(InvalidArgumentException::class);

        new OAuthTokenManager('http://example.com', 'test', '');
    }

    /**
     * @throws \BnplPartners\Factoring004\Exception\OAuthException
     * @return void
     */
    public function testGetAccessToken()
    {
        $username = 'test';
        $password = 'password';
        $data = compact('username', 'password');
        $responseData = [
            'access' => 'dGVzdA==',
            'accessExpiresAt' => 300,
            'refresh' => 'dGVzdDE=',
            'refreshExpiresAt' => 3600,
        ];

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(function (RequestInterface $request) use ($data) {
                return $request->getMethod() === 'POST'
                    && $request->getUri()->getAuthority() === 'example.com'
                    && $request->getUri()->getScheme() === 'http'
                    && $request->getUri()->getPath() === OAuthTokenManager::ACCESS_PATH
                    && $request->getHeaderLine('Content-Type') === 'application/json'
                    && strval($request->getBody()) === json_encode($data);
            }))
            ->willReturn(new Response(200, [], json_encode($responseData)));

        $manager = new OAuthTokenManager('http://example.com', $username, $password, $this->createTransport($client));

        $this->assertEquals(OAuthToken::createFromArray($responseData), $manager->getAccessToken());
    }

    /**
     * @throws \BnplPartners\Factoring004\Exception\OAuthException
     * @return void
     */
    public function testGetAccessTokenFailed()
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->withAnyParameters()
            ->willThrowException($this->createStub(TransferException::class));

        $manager = new OAuthTokenManager('http://example.com', 'a62f2225bf70bfaccbc7f1ef2a397836717377de', 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', $this->createTransport($client));

        $this->expectException(OAuthException::class);
        $manager->getAccessToken();
    }

    /**
     * @throws \BnplPartners\Factoring004\Exception\OAuthException
     * @return void
     */
    public function testGetAccessTokenFailedWithUnexpectedResponse()
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->withAnyParameters()
            ->willReturn(new Response(400, [], json_encode([])));

        $manager = new OAuthTokenManager('http://example.com', 'a62f2225bf70bfaccbc7f1ef2a397836717377de', 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', $this->createTransport($client));

        $this->expectException(OAuthException::class);
        $manager->getAccessToken();
    }

    /**
     * @throws \BnplPartners\Factoring004\Exception\OAuthException
     * @return void
     */
    public function testRefreshToken()
    {
        $refreshToken = 'dG9rZW4=';
        $responseData = [
            'access' => 'dGVzdA==',
            'accessExpiresAt' => 300,
            'refresh' => 'dGVzdDE=',
            'refreshExpiresAt' => 3600,
        ];

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(function (RequestInterface $request) use ($refreshToken) {
                return $request->getMethod() === 'POST'
                    && $request->getUri()->getAuthority() === 'example.com'
                    && $request->getUri()->getScheme() === 'http'
                    && $request->getUri()->getPath() === OAuthTokenManager::REFRESH_PATH
                    && $request->getHeaderLine('Content-Type') === 'application/json'
                    && strval($request->getBody()) === json_encode(compact('refreshToken'));
            }))
            ->willReturn(new Response(200, [], json_encode($responseData)));

        $manager = new OAuthTokenManager(
            'http://example.com',
            'test',
            'password',
            $this->createTransport($client)
        );

        $this->assertEquals(OAuthToken::createFromArray($responseData), $manager->refreshToken($refreshToken));
    }

    /**
     * @throws \BnplPartners\Factoring004\Exception\OAuthException
     * @return void
     */
    public function testRefreshTokenFailed()
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->withAnyParameters()
            ->willThrowException($this->createStub(TransferException::class));

        $manager = new OAuthTokenManager(
            'http://example.com',
            'test',
            'password',
            $this->createTransport($client)
        );

        $this->expectException(OAuthException::class);
        $manager->refreshToken('dG9rZW4=');
    }

    /**
     * @throws \BnplPartners\Factoring004\Exception\OAuthException
     * @return void
     */
    public function testRefreshTokenFailedWithUnexpectedResponse()
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->withAnyParameters()
            ->willReturn(new Response(400, [], json_encode([])));

        $manager = new OAuthTokenManager(
            'http://example.com',
            'test',
            'password',
            $this->createTransport($client)
        );

        $this->expectException(OAuthException::class);
        $manager->refreshToken('dG9rZW4=');
    }

    /**
     * @return void
     */
    public function testRevokeToken()
    {
        $manager = new OAuthTokenManager(
            'http://example.com',
            'a62f2225bf70bfaccbc7f1ef2a397836717377de',
            'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4'
        );

        $this->expectException(BadMethodCallException::class);

        $manager->revokeToken();
    }

    /**
     * @return \BnplPartners\Factoring004\Transport\TransportInterface
     */
    public function createTransport(ClientInterface $client)
    {
        return new GuzzleTransport($client);
    }
}

