<?php

declare(strict_types=1);

namespace App\Common;

use App\Common\Settings;
use \Slim\ResponseEmitter;
use Psr\Http\Message\ResponseInterface;

class Responder extends \Slim\ResponseEmitter
{
    protected ResponseEmitter $responseEmitter;
    protected Settings $settings;
    protected ResponseInterface $response;

    protected function __construct(?Settings $settings = null)
    {
        $this->responseEmitter = new ResponseEmitter();

        $this->settings = $settings ?? new Settings([]);
    }

    protected function setDefaultHeaders(): self
    {
        //An sspecially important is the "Access-Control-Allow-Origin" which controls
        //the CORS policy. By default, it's set to the origin of the request, i.e. allow all origins
        $origin = (string) $this->settings->get('allowed_origin') ?? $_SERVER['HTTP_ORIGIN'] ?? '';

        $this->response = $this->response //palun: unsure if this is needed? doesn't it change in situ?
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization',
            )
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');

        return $this;
    }


    protected function clearBuffer()
    {
        // Log anything left in the buffer prior to calling emit (which needs to use it)
        $buffer = ob_get_contents();
        if ($buffer) {
            trigger_error("Output buffer:\n$buffer\n", E_USER_NOTICE);
            ob_clean();
        }
    }

    public function send(ResponseInterface $response)
    {
        $this->response = $response;
        $this->setDefaultHeaders();
        $this->clearBuffer();
        $this->responseEmitter->emit($response);
    }

    public static function respond(ResponseInterface $response, ?Settings $settings = null)
    {
        $responder = new self($settings);
        $responder->send($response);
    }
}
