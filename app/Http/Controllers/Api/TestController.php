<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class TestController extends BaseController
{
    /**
     * Test endpoint - returns all headers and body
     *
     * @param Request $request
     * @return string
     */
    public function testHeader(Request $request)
    {
        try {
            // Get all headers
            $headers = [];
            foreach ($request->headers->all() as $key => $value) {
                $headers[$key] = $value;
            }

            // Get body content
            $body = $request->all();

            // Get raw body if it's not form data
            $rawBody = $request->getContent();

            $data = [
                'method' => $request->getMethod(),
                'headers' => $headers,
                'body' => $body,
                'raw_body' => $rawBody,
                'url' => $request->url(),
                'query_string' => $request->getQueryString(),
                'client_ip' => $request->getClientIp(),
                'user_agent' => $request->userAgent(),
            ];

            return $this->getJsonResponse(true, 'OK', $data);
        } catch (\Exception $ex) {
            return $this->getJsonResponse(false, $ex->getMessage(), null);
        }
    }
}
