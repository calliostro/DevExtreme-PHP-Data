<?php

require_once('DataController.php');

function GetParseParams($params, $assoc = false)
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

function GetParamsFromInput()
{
    $result = null;
    $content = file_get_contents('php://input');

    if ($content !== false) {
        $params = [];
        parse_str($content, $params);
        $result = GetParseParams($params, true);
    }

    return $result;
}

$response = null;
$controller = new DataController();
$controller->FillDbIfEmpty();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $params = GetParseParams($_GET);
        $response = $controller->Get($params);
        break;

    case 'POST':
        $params = GetParamsFromInput();
        $response = $controller->Post($params['values']);
        break;

    case 'PUT':
        $params = GetParamsFromInput();
        $response = $controller->Put($params['key'], $params['values']);
        break;

    case 'DELETE':
        $params = GetParamsFromInput();
        $response = $controller->Delete($params['key']);
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
