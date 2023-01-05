<?php
declare(strict_types=1);

namespace PTS\Md\JsonSchemaValidator;

use RuntimeException;
use Symfony\Component\Yaml\Parser;

class JsonSchemaManager
{
	protected array $configTree = [];
	/** @var string[] */
	protected array $files = [];

	protected Parser $parserYaml;

	public function __construct(protected string $configsDir)
	{
		$this->parserYaml = new Parser;
	}

	/**
	 * Получает ветку конфигурации из дерева конфигурации
	 *
	 * @param string[] $names
	 * @param array|null $context
	 *
	 * @return array
	 */
	public function getConfig(array $names, array $context = null): array
	{
		$context = $context ?? $this->configTree;
		$levelName = array_shift($names);

		$newContext = $context[$levelName] ?? false;
		if ($newContext === false) {
			return [];
		}

		return count($names) ? $this->getConfig($names, $newContext) : $newContext;
	}

	public function addFile(string $path): void
	{
		if (in_array($path, $this->files, true)) {
			return;
		}

		if (!file_exists($path)) {
			throw new RuntimeException("Can`t find config file `$path`", 500);
		}

		$this->files[] = $path;

		$fileConfigs = $this->parserYaml->parseFile($path);
		foreach ($fileConfigs as $name => $validatorConfig) {
			$this->addConfig($name, $validatorConfig);
		}
	}

	public function addConfig(string $name, array $config): self
	{
		if ($this->configTree[$name] ?? null) {
			throw new RuntimeException("Validator `$name` already defined", 500);
		}

		$this->configTree[$name] = $config;
		return $this;
	}

	/**
	 * Добавить yml файл с конфигурацией относительно директории с конфигами
	 *
	 * @param string $configFile
	 * @return $this
	 * @throws RuntimeException
	 */
	public function addRelFile(string $configFile): self
	{
		$path = $this->configsDir . '/' . $configFile;
		$this->addFile($path);
		return $this;
	}
}
