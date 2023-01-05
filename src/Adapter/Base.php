<?php
declare(strict_types=1);

namespace PTS\Md\JsonSchemaValidator\Adapter;

use PTS\Md\JsonSchemaValidator\JsonSchemaManager;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use stdClass;

class Base
{
	protected JsonSchemaManager $schemaManager;
	protected Validator $validator;
	protected int $maxErrors = 5;

	public function __construct(JsonSchemaManager $schemaManager, int $maxErrors = 5)
	{
		$this->schemaManager = $schemaManager;
		$this->validator = new Validator;
		$this->maxErrors = $maxErrors;
	}

	public function getConfig(array $schemaName): array
	{
		return $this->schemaManager->getConfig($schemaName);
	}

	public function getSchemaManager(): JsonSchemaManager
	{
		return $this->schemaManager;
	}

	public function validate(object $data, array $schemaName): array
	{
		$config = $this->schemaManager->getConfig($schemaName);
		return $this->validateByConfig($data, $config);
	}

	public function validateByConfig(object $data, array $config): array
	{
		$schema = $this->createSchema($config);
		$result = $this->validator->schemaValidation($data, $schema, $this->maxErrors);
		return $this->addErrors($result);
	}

	public function addErrors(ValidationResult $result): array
	{
		$errors = [];
		foreach ($result->getErrors() as $error) {
			$message = $this->getMessageFromError($error);

			$fieldPath = $this->getErrorPath($error);
			$fieldPath = implode(' -> ', $fieldPath);

			$errors[$fieldPath] = $message;
		}

		return $errors;
	}

	protected function getMessageFromError(ValidationError $error): string
	{
		$message = $error->schema()->errorMessage ?? null;

		$fieldPath = $this->getErrorPath($error);
		$fieldPath = implode(' -> ', $fieldPath);

		if (!$message) {
			if ($fieldPath === 'required') {
				$message = implode(', ', $error->schema()->required);
			} else {
				$keywords = $error->keywordArgs();
				$message = $keywords
					? sprintf('Error on field: %s - %s', $fieldPath, print_r($keywords, true))
					: sprintf('Error on field: %s', $fieldPath);
			}
		}

		foreach ($error->subErrors() as $subError) {
			$subMessage = $this->getMessageFromError($subError);
			$message .= ' -> ' . $subMessage;
		}

		return $message;
	}

	protected function getErrorPath(ValidationError $error): array
	{
		$dataPointer = $error->dataPointer();
		$treeName = count($dataPointer) ? $dataPointer : [$error->keyword()];

		foreach ($error->subErrors() as $subError) {
			$sub = $this->getErrorPath($subError);
			array_push($treeName, ...$sub);
		}

		return $treeName;
	}

    /**
     * @throws \JsonException
     */
    protected function createSchema(array $config): Schema
	{
		$json = json_encode((object)$config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		return Schema::fromJsonString($json);
	}

    /**
     * @throws \JsonException
     */
    protected function convertToObject(array $params): object
	{
		if ($params === []) {
			return new stdClass;
		}

		$json = json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
	}
}