<?php

declare(strict_types=1);

namespace tgui\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OldInputMiddleware extends Middleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Store old input in session for form repopulation
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['old'] = $request->getParsedBody() ?? [];
        }
        
        return $handler->handle($request);
    }
}
