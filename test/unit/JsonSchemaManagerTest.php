<?php
declare(strict_types=1);

namespace Test\PTS\Md\JsonSchemaValidator\unit;

use PTS\Md\JsonSchemaValidator\JsonSchemaManager;
use PHPUnit\Framework\TestCase;

class JsonSchemaManagerTest extends TestCase
{

	protected JsonSchemaManager $manager;

	public function setUp(): void
	{
		parent::setUp();
		$configDir = realpath(__DIR__ . '/../config');
		$this->manager = new JsonSchemaManager($configDir);
	}

	public function testGetConfig(): void
	{
		$config = $this->manager->getConfig(['some']);
		static::assertSame([], $config);
	}

	public function testAddRelFile(): void
	{
		$this->manager->addRelFile('profile.yml');
		$config = $this->manager->getConfig(['profile']);
		static::assertCount(1, $config);
	}

	public function testAddFile(): void
	{
		$file = realpath(__DIR__ . '/../config/profile.yml');
		$this->manager->addFile($file);
		$config = $this->manager->getConfig(['profile']);
		static::assertCount(1, $config);
	}

	public function testMultiLoad(): void
	{
		$this->manager->addRelFile('profile.yml');
		$this->manager->addRelFile('profile.yml');

		$file = realpath(__DIR__ . '/../config/profile.yml');
		$this->manager->addFile($file);
		$this->manager->addFile($file);

		$config = $this->manager->getConfig(['profile']);
		static::assertCount(1, $config);
	}

	public function testBadFile(): void
	{
		$dir = realpath(__DIR__ . '/../config/');

		$message = sprintf("Can`t find config file `%s`", $dir . '/profile4.yml');
		$this->expectException('RuntimeException');
		$this->expectExceptionMessage($message);
		$this->manager->addRelFile('profile4.yml');
	}

	public function testDuplicateDefinition(): void
	{
		$this->expectException('RuntimeException');
		$this->expectExceptionMessage('Validator `profile` already defined');

		$this->manager->addRelFile('profile.yml');
		$this->manager->addRelFile('profile2.yml');
	}
}