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
function ddns($lib,$cache){
    $ip=$lib->getIP();
    sleep(10);
    $newip=$lib->getIP();
    if($newip!=$ip){
        $img=$lib->updateDdns('shax.vip','img',$cache);
        if($img){
            $lib->log('success update ip address. '.'latest ip is : '.$newip.' and old is: '.$ip);
        }else{
			$lib->log('failed to update DDNS');
		}
        
    }
}
$dae=new dae();
$lib=new lib($config['token']);
$cache= new Memcached();
$cache->addServer('127.0.0.1',11211);
//ddns($lib,$cache);
$dae->setJob(['function'=>'ddns','param'=>[$lib,$cache]]);
$dae->main($argv);