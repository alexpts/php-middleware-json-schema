<?php
declare(strict_types=1);

namespace Test\PTS\Md\JsonSchemaValidator\unit;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use PTS\Md\JsonSchemaValidator\Adapter\Psr7Adapter;
use PTS\Md\JsonSchemaValidator\JsonSchemaManager;
use PTS\Md\JsonSchemaValidator\Psr15JsonSchema;

class Psr15JsonSchemaTest extends TestCase
{
	protected Psr15JsonSchema $md;
	protected RequestHandlerInterface $next;
	protected JsonSchemaManager $manager;

	public function setUp(): void
	{
		parent::setUp();

		$configDir = realpath(__DIR__ . '/../config');
		$this->manager = new JsonSchemaManager($configDir);
		$psr7Validator = new Psr7Adapter($this->manager);
		$logger = $this->createMock(LoggerInterface::class);

		$this->md = new Psr15JsonSchema($psr7Validator, $logger, static function(ServerRequestInterface $request) {
			return $request->getAttribute('validator');
		});

		$this->next = $this->getMockBuilder(RequestHandlerInterface::class)
			->onlyMethods(['handle'])
			->getMock();
		$this->next->method('handle')->willReturn(new JsonResponse(['ok' => 1]));

	}

	public function testDontHaveValidator(): void
	{
		$request = new ServerRequest([], [], '/', 'GET');
		$response = $this->md->process($request, $this->next);
		static::assertSame(200, $response->getStatusCode());
		static::assertFalse($response->hasHeader('x-vre'));
	}

	public function testRequestErrors(): void
	{
		$request = new ServerRequest([], [], '/', 'GET');
		$request = $request->withAttribute('validator', 'profile');
		$this->manager->addRelFile('profile.yml');

		$response = $this->md->process($request, $this->next);
		static::assertSame(400, $response->getStatusCode());
		static::assertFalse($response->hasHeader('x-vre'));

		$responseData = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		static::assertSame(['errors' => [
			'validator' => [
				'query' => [
					'required' => 'id'
				]
			]
		]], $responseData);
	}

	public function testRequestWithoutErrors(): void
	{
		$body = new Stream('php://temp');
		$request = new ServerRequest([], [], '/', 'GET', $body, [], [], ['id' => 1]);
		$request = $request->withAttribute('validator', 'profile');
		$this->manager->addRelFile('profile.yml');

		$response = $this->md->process($request, $this->next);
		static::assertSame(200, $response->getStatusCode());
		static::assertFalse($response->hasHeader('x-vre'));
	}

	public function testResponseErrors(): void
	{
		$request = new ServerRequest([], [], '/', 'GET');
		$request = $request->withAttribute('validator', 'profile-response');
		$this->manager->addConfig('profile-response', [
			'response' => [
				200 => [
					'body' => [
						'required' => ['ok', 'status']
					]
				]
			]
		]);

		$response = $this->md->process($request, $this->next);
		static::assertSame(200, $response->getStatusCode());
		static::assertTrue($response->hasHeader('x-vre'));
	}

	public function testResponseWithoutErrors(): void
	{
		$request = new ServerRequest([], [], '/', 'GET');
		$request = $request->withAttribute('validator', 'profile-response');
		$this->manager->addConfig('profile-response', [
			'response' => [
				200 => [
					'body' => [
						'required' => ['ok']
					]
				]
			]
		]);

		$response = $this->md->process($request, $this->next);
		static::assertSame(200, $response->getStatusCode());
		static::assertFalse($response->hasHeader('x-vre'));
	}

	public function testResponseSubError(): void
	{
		$data = ['data' => ['id' => 1]];
		$this->next = $this->getMockBuilder(RequestHandlerInterface::class)
			->onlyMethods(['handle'])
			->getMock();
		$this->next->method('handle')->willReturn(new JsonResponse($data));

		$request = new ServerRequest([], [], '/', 'GET');
		$request = $request->withAttribute('validator', 'profile-response');
		$this->manager->addConfig('profile-response', [
			'response' => [
				200 => [
					'body' => [
						'required' => ['data'],
						'properties' => [
							'data' => [
								'required' => ['id'],
								'type' => 'object',
								'properties' => [
									'id' => [
										'type' => 'integer',
										'minimum' => 100
									]
								]
							]
						]
					]
				]
			]
		]);

		$response = $this->md->process($request, $this->next);
		static::assertSame(200, $response->getStatusCode());
		static::assertTrue($response->hasHeader('x-vre'));
	}
}