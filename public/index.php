<?php

declare(strict_types=1);

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use Slim\Factory\ServerRequestCreatorFactory;
use App\Bootstrap\Bootstrap;

require_once __DIR__ . '/../src/Bootstrap/bootstrap.php';

//devnote: wrap in function to avoid polluting global namespace and ensure 
//          language server picks up on unset variables
(function () {

    //Bootstrap the Slim application (including settings, env, vendor autoloading etc)
    $x = Bootstrap::run();


    // Create Request object from globals
    $serverRequestCreator = ServerRequestCreatorFactory::create();
    $request = $serverRequestCreator->createServerRequestFromGlobals();

    // Create Error Handler
    $errorHandler = new HttpErrorHandler($x->app->getCallableResolver(), $x->app->getResponseFactory());

    // Create Shutdown Handler
    $shutdownHandler = new ShutdownHandler($request, $errorHandler, $x->settings->get('displayErrorDetails'));
    register_shutdown_function($shutdownHandler);

    // Add Routing Middleware
    $x->app->addRoutingMiddleware();

    // Add Body Parsing Middleware
    $x->app->addBodyParsingMiddleware();

    // Add Error Middleware
    $errorMiddleware = $x->app->addErrorMiddleware(
        $x->settings->get('displayErrorDetails'),
        $x->settings->get('logError'),
        $x->settings->get('logErrorDetails')
    );


    $errorMiddleware->setDefaultErrorHandler($errorHandler);

    // Run App & Emit Response
    $response = $x->app->handle($request);
    $responseEmitter = new ResponseEmitter();
    $responseEmitter->emit($response);
})();
