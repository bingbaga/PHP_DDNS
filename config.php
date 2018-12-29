<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 14:35
 */
return $config=[
    'memcached'=>[
        'server'=>'127.0.0.1',//缓存服务器的ip
        'port'=>'11211'//缓存服务器的端口
    ],
    'check_time'=>10,//检测时间
    'token'=>'id,secret',//dnspod token使用id和密钥拼接
    'domain'=>'',//ddns 域名 例如配置test.demo.com这里写demo.com
    'sub_domain'=>''//二级域名，如上，这里是test 就可以完美解析到test.demo.com
];