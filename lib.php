<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 16:45
 */
include_once 'Http.php';
class lib{
    private $token;
    private $base;
    public function __construct($token)
    {
        $this->token=$token;
        $this->base=['login_token'=>$this->token,'format'=>'json'];
    }

    public function getRecordList(string  $domain,string $sub=''){
        $this->base['domain']=$domain;
        if($sub!=''){
            $this->base['sub_domain']=$sub;
        }
        $http=new Http('Record.List','POST',$this->base);
        $data=$http->httpQuery();
        if($data->status->code==1){
//            unset($this->base['sub_domain']);
//            unset($this->base['domain']);
            return $data->records[0];
        }
        return false;
    }

    public function getIP(){
        $http=new Http('IPShare/info','GET',[],'http://ip.360.cn/');
        try{
            $data=$http->httpQuery();
            if(isset($data->ip)){
                return $data->ip;
            }
        }catch(\Exception $exception){
            $this->log((string)$exception->getMessage());
            return false;
        }
    }

    public function updateDdns($domain,$sub,$cache){
        $ip=$this->getIP();
        $postData=$this->base;
        $postData['domain']=$domain;
        $postData['sub_domain']=$sub;
        if($ip){
			$record=json_decode($cache->get($domain.$sub));
			if(!$record){
				$record=$this->getRecordList($domain,$sub);
				if($record){
					$cache->add("$domain"."$sub",json_encode($record),3600);
				}else{
					return false;
				}
			}
            $record_id=$record->id;
            $dns_ip=$record->value;
            if($dns_ip!=$ip){
                $postData['record_id']=$record_id;
                $postData['record_line']='默认';
                $postData['value']=$ip;
                $http=new Http('Record.Ddns', 'POST', $postData);
                $data=$http->httpQuery();
				$cache->delete("$domain"."$sub");
                if($data) {
                    if($data->status->code==1){
                        return true;
                    }
                }
                return false;
            }
            return true;
        }
    }

    public function log($msg=''){
        $data=date('Y-m-d h:m:s');
        if(!is_dir('./log')){
            @mkdir ('./log',0777,true);
        }
        file_put_contents('./log/'.date('Y-m-d').'.log', $data.':'.$msg.PHP_EOL, FILE_APPEND);
    }
}