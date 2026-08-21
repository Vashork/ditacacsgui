<?php

declare(strict_types=1);

namespace tgui\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController extends Controller
{
    public function getHome(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = [
            'info' => [
                'general' => [
                    'type' => 'get',
                    'object' => 'auth',
                    'action' => 'signin',
                    'time' => time()
                ],
                'version' => [
                    'TACVER' => TACVER,
                    'APIVER' => APIVER,
                ],
                'user' => [
                    'id' => (isset($_SESSION['uid'])) ? $_SESSION['uid'] : 'empty',
                ],
            ],
            'error' => [
                'error' => [
                    'status' => false,
                ]
            ],
        ];

        // Check user auth
        $this->auth->check();

        // Check error
        if (isset($_SESSION['error']['status']) && $_SESSION['error']['status']) {
            $data['error'] = $_SESSION['error'];
            return $this->json($response, $data, 401);
        }

        return $this->json($response, $data, 200);
    }

    public function postHome(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = ['info' => 'unset'];
        return $this->json($response, $data, 200);
    }
}
