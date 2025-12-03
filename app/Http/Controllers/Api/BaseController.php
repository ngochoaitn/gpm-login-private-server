<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class BaseController extends Controller
{
    /**
     * Return json response with unicode
     *
     * @param  bool  $success
     * @param  string  $message
     * @param  object  $data
     *
     */
    protected function getJsonResponse($success, $message, $data){
        $resp = ['success' => $success, 'message' => $message, 'data' => $data];
        return json_encode($resp, JSON_UNESCAPED_UNICODE);
    }
}
