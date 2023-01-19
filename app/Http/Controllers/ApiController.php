<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;


class ApiController extends Controller
{
    public function sendResponse($result, $responseCode = Response::HTTP_OK)
    {
        $data = [
            "status" => true,
            "data" => $result,
            "message" => null,
            "errors" => []
        ];
        return response()->json($data, $responseCode);
    }

    public function sendError($message, $errors = [], $responseCode = Response::HTTP_BAD_REQUEST)
    {
        $data = [
            "status" => false,
            "data" => null,
            "message" => $message,
            "errors" => $errors
        ];

        return response()->json($data, $responseCode);
    }
}
