<?php

require_once('DataController.php');

function getParseParams($params, $assoc = false)
{
    if (!is_array($params)) {
        return $params;
    }

    $result = [];

    foreach ($params as $key => $value) {
        $result[$key] = json_decode($params[$key], $assoc);
        if ($result[$key] === null) {
            $result[$key] = $params[$key];
        }
    }

    return $result;
}

function getParamsFromInput()
{
    $result = null;
    $content = file_get_contents('php://input');

    if ($content !== false) {
        $params = [];
        parse_str($content, $params);
        $result = getParseParams($params, true);
    }

    return $result;
}

$response = null;
$controller = new DataController();
$controller->fillDbIfEmpty();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $params = getParseParams($_GET);
        $response = $controller->get($params);
        break;

    case 'POST':
        $params = getParamsFromInput();
        $response = $controller->post($params['values']);
        break;

    case 'PUT':
        $params = getParamsFromInput();
        $response = $controller->put($params['key'], $params['values']);
        break;

    case 'DELETE':
        $params = getParamsFromInput();
        $response = $controller->delete($params['key']);
        break;
}

unset($controller);

if (isset($response) && !is_string($response)) {
    header('Content-type: application/json');
    echo json_encode($response);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['message' => $response, 'code' => 500]);
}
