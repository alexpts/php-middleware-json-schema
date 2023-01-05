<?php

declare(strict_types=1);

namespace Test\PTS\Md\JsonSchemaValidator\unit;

use PTS\Md\JsonSchemaValidator\Adapter\Psr7Adapter;
use PTS\Md\JsonSchemaValidator\JsonSchemaManager;
use PHPUnit\Framework\TestCase;

class Psr7AdapterTest extends TestCase
{

    public function testGetSchemaManager(): void
    {
        $configDir = realpath(__DIR__ . '/../config');
        $manager = new JsonSchemaManager($configDir);
        $manager->addRelFile('profile.yml');

        $adapter = new Psr7Adapter($manager);
        $schema = $adapter->getSchemaManager()->getConfig(['profile']);
        static::assertCount(1, $schema);

        $expected = [
            'request' => [
                'body' => [],
                'headers' => [],
                'cookies' => [],
                'query' => [
                    'required' => ['id']
                ],
                'attributes' => []
            ]
        ];
        static::assertSame($expected, $schema);
    }

    /**
     * @throws \JsonException
     */
    public function testValidate(): void
    {
        $configDir = realpath(__DIR__ . '/../config');
        $manager = new JsonSchemaManager($configDir);
        $manager->addRelFile('profile.yml');

        $adapter = new Psr7Adapter($manager);

        $body = json_decode('{"id":1}', false, 512, JSON_THROW_ON_ERROR);
        $errors = $adapter->validate($body, ['profile', 'request', 'query']);
        static::assertCount(0, $errors);

        static::assertCount(1, $adapter->getSchemaManager()->getConfig(['profile']));
    }
}