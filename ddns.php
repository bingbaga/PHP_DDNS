<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 14:35
 */
require_once 'config.php';
require_once 'DdnsApiService.php';
require_once 'dae.php';
function ddns(DdnsApiService $lib, $domains){
    try{
        $local_ip=$lib->getIP();
        if(!empty($local_ip)){
            foreach ($domains as $domain){
                $domainInfo=$lib->getCachedDomainInfo($domain['domain'],$domain['sub_domain']);
                $cachedIp=$domainInfo['value'];
                if($cachedIp !== $local_ip){
                    $status[]=$lib->updateDdns($domain['domain'],$domain['sub_domain'], $local_ip);
                }
            }
        }
    }catch(Exception $exception){
        $lib->log('系统错误');
    }

}
$dae=new dae();
$cache= new Memcached();
$lib=new DdnsApiService($config['token'], $cache);

$cache->addServer('127.0.0.1',11211);
//ddns($lib,$config['domains']);
$dae->setJob(['function'=>'ddns','param'=>[$lib,$config['domains']]]);
$dae->main($argv);