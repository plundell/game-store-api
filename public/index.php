<?php

declare(strict_types=1);

use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use Slim\Factory\ServerRequestCreatorFactory;
use App\Bootstrap\Bootstrap;
use App\Common\Responder;

require_once __DIR__ . '/../src/Bootstrap/bootstrap.php';

//devnote: wrap in function to avoid polluting global namespace and ensure 
//          language server picks up on unset variables
(function () {

    //Bootstrap the Slim application (including settings, env, vendor autoloading etc)
    $x = Bootstrap::app();

    //Register all routes so we're able to handle requests
    $x->registerRoutes();

    //PHP gives access to the request via $_SERVER, but we want a fancy object, so create it
    $request = ServerRequestCreatorFactory::create()->createServerRequestFromGlobals();

    // Create Error Handler
    $errorHandler = new HttpErrorHandler($x->app->getCallableResolver(), $x->app->getResponseFactory());

    // Create Shutdown Handler
    $shutdownHandler = new ShutdownHandler($request, $errorHandler, $x->settings);
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

    //Now we're ready to handle the request

    // Handle the request
    $response = $x->app->handle($request);

    //Send the reponse (this sets headers and everything)
    Responder::respond($response, $x->settings);
})();
