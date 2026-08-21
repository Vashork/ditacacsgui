<?php

declare(strict_types=1);

namespace tgui\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ChangeHeaderMiddleware extends Middleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Application-Name', 'tacacsgui')
            ->withHeader('Author-Name', 'Alexey Mochalin')
            ->withHeader('Application-Version', APIVER);
    }
}
