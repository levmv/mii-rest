<?php

namespace mii\rest;

class Response
{
    public array $headers = [];

    public mixed $content = null;

    protected $_uri;

    protected int $responseCode;

    protected $_error;

    protected $_result;

    protected string $rawHeaders = '';

    public string $body = '';

    private bool $parsed = false;

    /**
     * Sets the total number of rows and stores the result locally.
     *
     * @param $response
     * @param $url
     * @param $info
     * @param $error
     */
    public function __construct($response, $url, int $responseCode)
    {
        // Store the result locally
        $this->_result = $response;

        $this->_uri = $url;
//        $this->_info = $info;
        $this->responseCode = $responseCode;

        list($this->rawHeaders, $this->body) = explode("\r\n\r\n", $response, 2);
    }


    public function get(string $name, $default = null)
    {
        $this->parseBody();
        return $this->content[$name] ?? $default;
    }

    public function asArray(): array
    {
        $this->parseBody();
        return $this->content;
    }

    public function iterate($name = null)
    {
        if ($name === null) {
            foreach ($this->content as $value)
                yield $value;
        } else {
            if (!isset($this->content[$name]) || \is_array($this->content))
                return;

            foreach ($this->content[$name] as $value)
                yield $value;
        }
    }

    public function isOk(): bool
    {
        return $this->responseCode >= 200 && $this->responseCode < 300;
    }

    public function statusCode(): int
    {
        return $this->responseCode;
    }

    public function header(string $name): ?string
    {
        if(empty($this->headers)) {
            $this->parseHeaders();
        }

        return $this->headers[strtolower($name)] ?? null;
    }

    protected function parseBody()
    {
        if($this->parsed) {
            return;
        }
        $this->content = json_decode($this->body, true);
        $this->parsed = true;
    }


    protected function parseHeaders()
    {
        $line = strtok($this->rawHeaders, "\n");
        do {
            if (\strlen(trim($line)) == 0) {
                // Since we tokenize on \n, use the remaining \r to detect empty lines.
                if (\count($this->headers) > 0) break; // Must be the newline after headers, move on to response body
            } elseif (strpos($line, 'HTTP') === 0) {
                // One or more HTTP status lines
                $response_status_lines[] = trim($line);
            } else {
                // Has to be a header
                list($key, $value) = explode(':', $line, 2);
                $key = trim(strtolower(str_replace('-', '_', $key)));
                $value = trim($value);

                if (empty($this->headers[$key]))
                    $this->headers[$key] = $value;
                elseif (\is_array($this->headers[$key]))
                    $this->headers[$key][] = $value;
                else
                    $this->headers[$key] = [$this->headers[$key], $value];
            }
        } while ($line = strtok("\n"));
    }
}
