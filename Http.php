<?php

/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 16:19
 */
class Http {
    private $uri;
    public $endpoint;
    public $data;
    public $type;

    public function __construct($endpoint, $type, array $data, $api = '') {
        $this->endpoint = $endpoint;
        $this->data = $data;
        $this->type = $type;
        if ($api === '') {
            $api = 'https://dnsapi.cn/';
        }
        $this->uri = $api . $this->endpoint;
    }

    public function httpQuery():array {
        $options = [
            'http' => [
                'method' => $this->type,
                'header' => [
                    'Content-type:application/x-www-form-urlencoded',
                ],
                'content' => http_build_query((array)$this->data),
            ],
        ];

        $result = fopen($this->uri, 'rb', false, stream_context_create($options));
        if(!$result){
            throw new RuntimeException('请求错误');
        }
        return json_decode(fgets($result), true);
    }
}