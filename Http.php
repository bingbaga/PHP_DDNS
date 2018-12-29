<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 16:19
 */
class Http {
    private $api;
    private $uri;
    public $endpoint;
    public $data;
    public $type;
    public function __construct($endpoint, $type, array $data, $api='')
    {
        $this->endpoint=$endpoint;
        $this->data=$data;
        $this->type=$type;
        $this->api=$api;
        if($api==''){
            $this->api='https://dnsapi.cn/';
        }
        $this->uri=$this->api.$this->endpoint;
    }

    public function httpQuery(){
        $options = array(
            "http" => array(
                "method" => $this->type,
                "header" => array(
                    "Content-type:application/x-www-form-urlencoded",
                ),
                "content" => http_build_query((array)$this->data),
            ),
        );

        $result = fopen($this->uri, "r", false, stream_context_create($options));
        if ($result) {
            try{
                $data=json_decode(fgets($result));
                return $data;
            }catch (\Exception $exception){
                return false;
            }

        } else {
            return false;
        }
    }
}