<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Proxy;

class CreateProxyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'raw_proxy' => [
                'required',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    // Basic validation for proxy format
                    if (!$this->isValidProxyFormat($value)) {
                        $fail('The raw_proxy must be in a valid proxy format (e.g., host:port or protocol://host:port).');
                    }
                },
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'raw_proxy.required' => 'Proxy address is required.',
            'raw_proxy.max' => 'Proxy address cannot exceed 500 characters.',
        ];
    }

    /**
     * Validate proxy format
     *
     * @param string $proxy
     * @return bool
     */
    private function isValidProxyFormat($proxy)
    {
        // Allow various proxy formats:
        // host:port
        // protocol://host:port
        // protocol://username:password@host:port

        $patterns = [
            // Basic host:port
            '/^[a-zA-Z0-9\.\-]+:\d+$/',
            // protocol://host:port
            '/^(https?|socks[45]):\/\/[a-zA-Z0-9\.\-]+:\d+$/',
            // protocol://username:password@host:port
            '/^(https?|socks[45]):\/\/[^@:]+:[^@:]+@[a-zA-Z0-9\.\-]+:\d+$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $proxy)) {
                return true;
            }
        }

        return false;
    }
}
