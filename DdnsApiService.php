<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 2018/11/25
 * Time: 16:45
 */
include_once 'Http.php';

class DdnsApiService {
    //private $token;
    private $base;

    private $cache;//存放缓存实例

    private $cached_ttl = 3600;//缓存时间

    //构造token
    public function __construct(string $token, Memcached $cache) {
        //$this->token=$token;
        $this->base = ['login_token' => $token, 'format' => 'json'];
        $this->cache = $cache;
    }

    //获取记录列表
    public function getRecordList(string $domain, string $sub = ''):array {
        $this->base['domain'] = $domain;
        if (!empty($sub)) {
            $this->base['sub_domain'] = $sub;
        }
        $http = new Http('Record.List', 'POST', $this->base);
        $data = $http->httpQuery();
        if ((int)$data['status']['code'] === 1) {
            return $data['records'][0];
        }
        return [];
    }

    //获取本机IP地址
    public function getIP():string {
        $http = new Http('IPShare/info', 'GET', [], 'http://ip.360.cn/');
        try {
            $data = $http->httpQuery();
            if (!empty($data['ip'])) {
                return $data['ip'];
            }
        } catch (Exception $exception) {
            $this->log((string)$exception->getMessage());
            return '';
        }
    }

    //更新操作
    public function updateDdns($domain, $sub, $ip): bool {
        $postData = $this->base;
        $postData['domain'] = $domain;
        $postData['sub_domain'] = $sub;
        $domainInfo = $this->getCachedDomainInfo($domain, $sub);
        $record_id = $domainInfo['id'];
        $postData['record_id'] = $record_id;
        $postData['record_line'] = '默认';
        $postData['value'] = $ip;
        $http = new Http('Record.Ddns', 'POST', $postData);
        $data = $http->httpQuery();
        $this->cache->delete($domain . $sub);
        if ($data && (int)$data['status']['code'] === 1) {
            return true;
        }
        return false;
    }

    //根据域名获取到域名信息
    public function getCachedDomainInfo($domain, $sub_domain): array {
        $domainInfo = json_decode($this->cache->get($domain . $sub_domain), true);
        if (!$domainInfo) {
            $domainInfo = $this->getRecordList($domain, $sub_domain);
            if(!empty($domainInfo)){
                $this->cache->add($domain . $sub_domain, json_encode($domainInfo), $this->cached_ttl);
            }
        }
        return $domainInfo;
    }

    //日志
    public function log($msg = ''):void {
        $data = date('Y-m-d h:m:s');
        if (!is_dir('./log') && !mkdir('./log', 0777, true) && !is_dir('./log')) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', './log'));
        }
        file_put_contents('./log/' . date('Y-m-d') . '.log', $data . ':' . $msg . PHP_EOL, FILE_APPEND);
    }
}