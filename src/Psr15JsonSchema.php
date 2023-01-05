<?php

declare(strict_types=1);

namespace PTS\Md\JsonSchemaValidator;

use Closure;
use PTS\Md\JsonSchemaValidator\Adapter\Psr7Adapter;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class Psr15JsonSchema implements MiddlewareInterface
{
    public function __construct(
        protected Psr7Adapter $validator,
        protected LoggerInterface $logger,
        protected Closure $resolver,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $validatorName = call_user_func($this->resolver, $request);
        if ($validatorName === null) {
            return $next->handle($request);
        }

        $errors = $this->validator->validateRequest($request, $validatorName);
        if (count($errors)) {
            return new JsonResponse(['errors' => ['validator' => $errors]], 400);
        }

        $response = $next->handle($request);

        $errors = $this->validator->validateResponse($response, $validatorName);
        $countErrors = count($errors);
        if ($countErrors) {
            $this->logger->error('Ошибка валидации json ответа', [
                'errors' => $errors,
                'name' => $validatorName
            ]);
            $response = $response->withHeader('x-vre', $countErrors);
        }

        return $response;
    }
}