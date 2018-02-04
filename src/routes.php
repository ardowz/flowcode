<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get("/flowTranslate", function (Request $request, Response $response, array $args) {
    // Sample log message
//    $this->logger->info("Slim-Skeleton '/' route");

    //how to get params
//    $myvar1 = $request->getParam('myvar'); //checks both _GET and _POST [NOT PSR-7 Compliant]
//    $myvar2 = $request->getParsedBody()['myvar']; //checks _POST  [IS PSR-7 compliant]
//    $myvar3 = $request->getQueryParams()['myvar']; //checks _GET [IS PSR-7 compliant]
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post("/flowTranslate", function (Request $request, Response $response, array $args) {
    // Sample log message
//    $this->logger->info("Slim-Skeleton '/' route");
    var_dump($request->getParam("hello"));
    exit();

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
