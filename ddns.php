<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 14:35
 */
require_once 'config.php';
require_once 'lib.php';
require_once 'dae.php';
function ddns($lib,$cache, $config){
    $ip=$lib->getIP();
    sleep(10);
    $newip=$lib->getIP();
    if($newip!=$ip){
        $img=$lib->updateDdns($config['domain'],$config['sub_domain'],$cache);
        if($img){
            $lib->log('success update ip address. '.'latest ip is : '.$newip.' and old is: '.$ip);
        }else{
			$lib->log('failed to update DDNS');
		}
        
    }
}
$dae=new dae($config['check_time']);
$lib=new lib($config['token']);
if(!$config['memcached']['server'] && !$config['memcached']['port']){
    echo 'memcached server is null'.PHP_EOL.'EXIT';die();
}
$cache= new Memcached();
try{
    $cache->addServer($config['memcached']['server'],$config['memcached']['port']);
}catch (\Exception $exception){
    echo 'Memcached server error'.PHP_EOL.'EXIT';
    die();
}
//ddns($lib,$cache);
$dae->setJob(['function'=>'ddns','param'=>[$lib,$cache, $config]]);
$dae->main($argv);