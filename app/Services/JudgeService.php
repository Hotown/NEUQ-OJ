<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 17/8/21
 * Time: 下午7:19
 */

namespace NEUQOJ\Services;


use NEUQOJ\Exceptions\Judge\JudgeServerNotExistException;
use NEUQOJ\Repository\Eloquent\JudgeServerRepository;

class JudgeService
{
    private $judgeServerRepo;
    private $cacheService;

    public function __construct(JudgeServerRepository $judgeServerRepo,CacheService $cacheService)
    {
        $this->judgeServerRepo = $judgeServerRepo;
        $this->cacheService = $cacheService;
    }

    public function getAllJudgeServer()
    {
        return $this->judgeServerRepo->all();
    }

    public function getAllJudgeServerInfo()
    {
        $judgeServers = $this->judgeServerRepo->all();

        foreach ($judgeServers as &$judgeServer) {
            if ($judgeServer->status == 1) {
                $serverURL = "http://" . $judgeServer->host . ":" . $judgeServer->port;
                $pong = \Requests::get($serverURL . '/ping', ['token' => $judgeServer->rpc_token]);
                if ($pong->status_code != 200) {
                    $judgeServer->status = -1;
                    $judgeServer->info = null;
                }
                $judgeServer->info = json_decode($pong->body);
            }
        }
        return $judgeServers;
    }

    public function addJudgeServer(array $judgeServer)
    {
        return $this->judgeServerRepo->insertWithId($judgeServer);
    }

    public function refreshServerStatus(int $id)
    {
        $server = $this->judgeServerRepo->get($id)->first();
        if ($server == null) {
            throw new JudgeServerNotExistException();
        }
        $serverURL = "http://" . $server->host . ":" . $server->port;
        $pong = \Requests::get($serverURL . '/ping', ['token' => $server->rpc_token]);
        if ($pong->status_code != 200) {
            return null;
        } else {
            return json_decode($pong->body);
        }
    }

    public function updateServer(int $id,array $data)
    {
        return $this->judgeServerRepo->update($data,$id) == 1;
    }

    public function deleteServer(int $id)
    {
        return $this->judgeServerRepo->deleteWhere(['id' => $id]);
    }

    private function getBestServer()
    {
        // 调度算法
        $servers = $this->judgeServerRepo->getBy('status',1,['id','name','host','port','rpc_token']);

        $bestserver = [
            'server' => null,
            'load' => 99999,
        ];

        foreach ($servers as $server) {
            $serverURL = "http://" . $server->host . ":" . $server->port;
            $pong = \Requests::get($serverURL . '/ping', ['token' => $server->rpc_token]);
            if ($pong->status_code == 200&&!isset($pong->code)) {
                $pong = json_decode($pong->body);
                $load = 400*$pong->cpu[0] + 0.6*$pong->memory;
                if ($load < $bestserver['load']) {
                    $bestserver = [
                        'server' => $server,
                        'load' => $load
                    ];
                }
            }
        }
        return $bestserver['server'];
    }

    public function judge(array $data)
    {
        $server = $this->getBestServer();
        $serverURL = 'http://'.$server->host.':'.$server->port.'/judge';
        $result = \Requests::post($serverURL,['token' => $server->rpc_token,'Content-Type' => 'application/json'],json_encode($data));
        if ($result->success){
            $result = json_decode($result->body);
            $result->judgerName = $server->name;
            return $result;
        }else{
            return null;
        }
    }
}