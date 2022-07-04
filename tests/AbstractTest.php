<?php

declare(strict_types=1);

namespace App\Tests;

use Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use function count;
use function is_int;

abstract class AbstractTest extends WebTestCase
{
    protected const EMAIL = 'user@example.com';
    protected const PASSWORD = 'user_password';

    protected const ADMIN_EMAIL = 'admin@example.com';
    protected const ADMIN_PASSWORD = 'admin_password';

    protected static AbstractBrowser $client;

//    protected function setUp(): void
//    {
//        static::$client = static::createClient();
//    }

    // Для пропуска по-умолчанию всех тестов
//    protected function setUp(): void
//    {
//        $this->markTestSkipped();
//    }

    protected static function getClient($reinitialize = false, array $options = [], array $server = []): AbstractBrowser
    {
//        if ($reinitialize) {
            static::$client = static::createClient($options, $server);
//        }

        return static::$client;
    }

    /**
     * Shortcut
     */
    protected static function getEntityManager()
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    public function assertResponseOk(?Response $response = null, ?string $message = null, string $type = 'text/html')
    {
        $this->failOnResponseStatusCheck($response, 'isOk', $message, $type);
    }

    public function assertResponseRedirect(
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, 'isRedirect', $message, $type);
    }

    public function assertResponseNotFound(
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, 'isNotFound', $message, $type);
    }

    public function assertResponseForbidden(
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, 'isForbidden', $message, $type);
    }

    public function assertResponseCode(
        int       $expectedCode,
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, $expectedCode, $message, $type);
    }
    /**
     * @param Response $response
     * @param string   $type
     *
     * @return string
     */
    public function guessErrorMessageFromResponse(Response $response, string $type = 'text/html')
    {
        try {
            $crawler = new Crawler();
            $crawler->addContent($response->getContent(), $type);

            if (!count($crawler->filter('title'))) {
                $add = '';
                $content = $response->getContent();

                if ('application/json' === $response->headers->get('Content-Type')) {
                    $data = json_decode($content);
                    if ($data) {
                        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $add = ' FORMATTED';
                    }
                }
                $title = '[' . $response->getStatusCode() . ']' . $add .' - ' . $content;
            } else {
                $title = $crawler->filter('title')->text();
            }
        } catch (Exception $e) {
            $title = $e->getMessage();
        }

        return trim($title);
    }

    private function failOnResponseStatusCheck(
        Response $response = null,
        $func = null,
        ?string $message = null,
        string $type = 'text/html'
    ) {
        if (null === $func) {
            $func = 'isOk';
        }

        if (null === $response && self::$client) {
            $response = self::$client->getResponse();
        }

        try {
            if (is_int($func)) {
                $this->assertEquals($func, $response->getStatusCode());
            } else {
                $this->assertTrue($response->{$func}());
            }

            return;
        } catch (Exception $e) {
            // nothing to do
        }

        $err = $this->guessErrorMessageFromResponse($response, $type);
        if ($message) {
            $message = rtrim($message, '.') . ". ";
        }

        if (is_int($func)) {
            $template = "Failed asserting Response status code %s equals %s.";
        } else {
            $template = "Failed asserting that Response[%s] %s.";
            $func = preg_replace('#([a-z])([A-Z])#', '$1 $2', $func);
        }

        $message .= sprintf($template, $response->getStatusCode(), $func, $err);

        $max_length = 100;
        if (mb_strlen($err, 'utf-8') < $max_length) {
            $message .= " " . $this->makeErrorOneLine($err);
        } else {
            $message .= " " . $this->makeErrorOneLine(mb_substr($err, 0, $max_length, 'utf-8') . '...');
            $message .= "\n\n" . $err;
        }

        $this->fail($message);
    }

    private function makeErrorOneLine($text)
    {
        return preg_replace('#[\n\r]+#', ' ', $text);
    }

    protected static function parseJsonResponse(AbstractBrowser $client)
    {
        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function login(AbstractBrowser $client, string $email, string $password): array
    {
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(200);

        $responseData = self::parseJsonResponse($client);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $responseData['token']);

        return $responseData;
    }

    protected function logout($client): void
    {
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
    }
}
