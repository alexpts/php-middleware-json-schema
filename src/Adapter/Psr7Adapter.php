<?php
declare(strict_types=1);

namespace PTS\Md\JsonSchemaValidator\Adapter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Psr7Adapter extends Base
{

    /**
     * @return string[]
     * @throws \JsonException
     */
    public function validateResponse(ResponseInterface $response, string $schemaName): array
	{
		$body = (string)$response->getBody();
		$statusCode = $response->getStatusCode();

		$map = [
			'body' => $body === '' ? '{}' : $body,
			'headers' => $response->getHeaders(),
		];

		$errors = [];
		foreach ($map as $name => $params) {
			$configValidator = $this->getConfig([$schemaName, 'response', $statusCode, $name]);
			if (count($configValidator) === 0) {
				continue;
			}

			$params = $name === 'body'
				? json_decode($body, false, 512, JSON_THROW_ON_ERROR)
				: $this->convertToObject($params);

			$validatorErrors = $this->validateByConfig($params, $configValidator);
			if ($validatorErrors) {
				$errors[$name] = print_r($validatorErrors, true);
			}
		}

		return $errors;
	}

    /**
     * @return string[]
     * @throws \JsonException
     */
	public function validateRequest(ServerRequestInterface $request, string $schemaName): array
	{
		$map = [
			'query' => $request->getQueryParams(),
			'body' => $request->getParsedBody() ?? [],
			'headers' => $request->getHeaders(),
			'cookies' => $request->getCookieParams(),
			'attributes' => $request->getAttributes(),
		];

		$errors = [];
		foreach ($map as $name => $params) {
			$configValidator = $this->getConfig([$schemaName, 'request', $name]);
			if (count($configValidator) === 0) {
				continue;
			}

			$params = $this->convertToObject($params);
			$this->validateByConfig($params, $configValidator);
			$validatorErrors = $this->validateByConfig($params, $configValidator);
			if ($validatorErrors) {
				$errors[$name] = $validatorErrors;
			}
		}

		return $errors;
	}
}
