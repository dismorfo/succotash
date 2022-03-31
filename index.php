<?php

/**
 * @file
 * index.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/include/common.php';
require_once __DIR__ . '/include/datasources.php';

use Bramus\Router\Router;

$router = new Router();

$menu = [];

$menu['/'] = [
  'label' => 'Home - Viewer',
  'verbs' => [
    'GET' => [
      'file' => './routes/home.php',
      'callback' => 'home',
      'delivery' => 'html',
    ],
  ],
];

$menu["/(\w+)"] = [
  'label' => 'Mirador',
  'verbs' => [
    'GET' => [
      'file' => './routes/mirador.php',
      'callback' => 'mirador',
      'delivery' => 'html',
    ],
  ],
];

// Custom 404 Handler.
$router->set404(function () {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
  echo '404, route not found!';
});

$request_method = $_SERVER['REQUEST_METHOD'];

// Register routes.
foreach ($menu as $route => $leaf) {
  if (
    isset($leaf['verbs']) &&
    isset($leaf['verbs'][$request_method])
  ) {
    $verb = strtolower($request_method);
    $router->$verb($route, function (...$params) use ($leaf, $request_method) {
      include_once $leaf['verbs'][$request_method]['file'];
      if (
        function_exists($leaf['verbs'][$request_method]['delivery']) &&
        function_exists($leaf['verbs'][$request_method]['callback'])
      ) {
        call_user_func(
          $leaf['verbs'][$request_method]['delivery'],
          call_user_func(
            $leaf['verbs'][$request_method]['callback'],
            $params
          )
        );
      }
    });
  }
}

$router->run();
