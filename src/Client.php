<?php

namespace mii\rest;

use mii\core\Component;

class Client extends Component
{
    public string $base_uri = '';
    public string $user_agent = '';

    public ?string $username = null;
    public ?string $password = null;

    public array $params = [];
    public array $headers = [];

    private string $lastError = '';

    public ?array $curl_options = null;

    public function get($url, $params = null, $headers = []): Response
    {
        return $this->execute('GET', $url, $params, $headers);
    }

    public function post($url, $params = null, $headers = []): Response
    {
        return $this->execute('POST', $url, $params, $headers);
    }

    public function put($url, $params = null, $headers = []): Response
    {
        return $this->execute('PUT', $url, $params, $headers);
    }

    public function delete($url, $params = null, $headers = []): Response
    {
        return $this->execute('DELETE', $url, $params, $headers);
    }

    public function execute($method, $url, $params = null, $headers = []): Response
    {
        $curl_handle = curl_init();

        $curlopt = [
            CURLOPT_HEADER => ['Expect:'],
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => $this->user_agent,
        ];

        if ($this->username && $this->password) {
            $curlopt[CURLOPT_USERPWD] = "{$this->username}:{$this->password}";
        }

        if (\count($this->headers) || \count($headers)) {
            $curlopt[CURLOPT_HTTPHEADER] = [];
            $headers = array_merge($this->headers, $headers);
            foreach ($headers as $key => $values) {
                foreach (\is_array($values) ? $values : [$values] as $value) {
                    $curlopt[CURLOPT_HTTPHEADER][] = "$key:$value";
                }
            }
        }

        // Allow passing parameters as a pre-encoded string (or something that
        // allows casting to a string). Parameters passed as strings will not be
        // merged with parameters specified in the default options.
        if (\is_array($params)) {
            $params = array_merge($this->params, $params);
            $parameters_string = http_build_query($params);

        } else
            $parameters_string = (string)$params;

        if (strtoupper($method) == 'POST') {
            $curlopt[CURLOPT_POST] = TRUE;
            $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
        } elseif (strtoupper($method) != 'GET') {
            $curlopt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
        } elseif ($parameters_string) {
            $url .= strpos($url, '?') ? '&' : '?';
            $url .= $parameters_string;
        }

        if($this->base_uri) {
            $url = rtrim($this->base_uri, '/') . '/' . ltrim($url, '/');
        }

        $curlopt[CURLOPT_URL] = $url;

        if ($this->curl_options) {
            // array_merge would reset our numeric keys.
            foreach ($this->curl_options as $key => $value) {
                $curlopt[$key] = $value;
            }
        }
        curl_setopt_array($curl_handle, $curlopt);

        $response = curl_exec($curl_handle);

        // TODO: debug mode with logging extended info
        $responseCode = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);

        $this->lastError = '';
        if($response === false) {
            $this->lastError = curl_error($curl_handle);
        }
        curl_close($curl_handle);

        return new Response($response, $url, $responseCode);
    }

    public function lastError(): string
    {
        return $this->lastError;
    }
}
