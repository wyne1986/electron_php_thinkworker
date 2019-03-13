<?php
/**
 *  ThinkWorker - THINK AND WORK FAST
 *  Copyright (c) 2017 http://thinkworker.cn All Rights Reserved.
 *  Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
 *  Author: Dizy <derzart@gmail.com>
 */

namespace think;
use think\exception\HttpException;
use think\exception\UnknownException;
use think\task\TaskClient;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;
use think\exception\FatalException;

class MainServer
{
    /**
     * @var Worker
     */
    public static $worker;

    /**
     * @var callable
     */
    public static $onWorkerStart = null;

    /**
     * @var array
     */
    private static $configs = [
        'listen_ip' => '0.0.0.0',
        'listen_port' => 80,
        'name' => 'ThinkWorker',
        'count' => 4,
        'ssl' => false,
        'ssl_local_cert'  => '/etc/nginx/conf.d/ssl/server.pem',
        'ssl_local_pk'    => '/etc/nginx/conf.d/ssl/server.key',
        'ssl_verify_peer' => false,
        'max_request_restart' => true,
        'max_request_limit' => 1000,
    ];

    /**
     * MainServer initialization method
     *
     * @param array $configs
     */
    public static function _init($configs){
        //Basic Worker config
        !isset($configs['listen_ip']) or self::$configs['listen_ip'] = $configs['listen_ip'];
        !isset($configs['listen_port']) or self::$configs['listen_port'] = $configs['listen_port'];
        !isset($configs['name']) or self::$configs['name'] = $configs['name'];
        !isset($configs['count']) or self::$configs['count'] = $configs['count'];
        !isset($configs['ssl']) or self::$configs['ssl'] = $configs['ssl'];
        !isset($configs['ssl_local_cert']) or self::$configs['ssl_local_cert'] = $configs['ssl_local_cert'];
        !isset($configs['ssl_local_pk']) or self::$configs['ssl_local_pk'] = $configs['ssl_local_pk'];
        !isset($configs['ssl_verify_peer']) or self::$configs['ssl_verify_peer'] = $configs['ssl_verify_peer'];
        !isset($configs['max_request_restart']) or self::$configs['max_request_restart'] = $configs['max_request_restart'];
        !isset($configs['max_request_limit']) or self::$configs['max_request_limit'] = $configs['max_request_limit'];

        //SSL Settings
        $content = array(
            'ssl' => array(
                'local_cert'  => self::$configs['ssl_local_cert'],
                'local_pk'    => self::$configs['ssl_local_pk'],
                'verify_peer' => self::$configs['ssl_verify_peer'],
            )
        );
        //Setting up an Http protocol supported Worker with or without SSL
        if(self::$configs['ssl']){
            self::$worker = new Worker("http://".self::$configs['listen_ip'].":".self::$configs['listen_port'], $content);
            self::$worker->transport = 'ssl';
        }else{
            self::$worker = new Worker("http://".self::$configs['listen_ip'].":".self::$configs['listen_port']);
        }
        self::$worker->name = self::$configs['name'];
        self::$worker->count = self::$configs['count'];

        //Event Hooking
        self::$worker->onWorkerStart = function(){self::onWorkerStart();};
        self::$worker->onWorkerReload = function(){self::onWorkerReload();};
        self::$worker->onMessage = function($connection, $data){self::onMessage($connection, $data);};
    }

    /**
     * Worker onWorkerStart Callback
     *
     * @return void
     */
    private static function onWorkerStart(){
        \think\server\Loader::loadEssentials();
        if(!is_null(self::$onWorkerStart) && is_callable(self::$onWorkerStart)){
            (self::$onWorkerStart)();
        }
    }

    /**
     * Worker onMessage Callback
     *
     * @param TcpConnection $connection
     * @param mixed $data
     * @return void
     */
    private static function onMessage($connection, $data){
        global $TW_ENV_REQUEST, $TW_ENV_RESPONSE;
        //Session auto start
        if(config("session.auto_start")){
            Session::startSession();
        }
        //Init Request and Response Objects
        $req = new Request($data);
        $resp = new Response($connection, $req);
        $TW_ENV_REQUEST = $req;
        $TW_ENV_RESPONSE = $resp;
        try{
            //Static files dispatching
            if(StaticDispatcher::dispatch($req, $resp)){
                return;
            };
            //Routing
            $routingResult = Route::match($req);
            $req->payload($routingResult['payload']);
            //Dispatching
            Dispatcher::dispatch($routingResult['controller'], $req, $resp);
        }catch (HttpException $e){
            //Caught HttpException then deliver msg to browser client
            $resp->setHeader("HTTP", true, $e->getStatusCode());
            $resp->send($e->getHttpBody());
            $eDesc = describeException($e);
            Log::e($eDesc, "HttpException");
        }catch (FatalException $e){
            //Caught FatalException then log error and shut down server
            $eDesc = describeException($e);
            Log::e($eDesc, "FatalException");
        }catch (\Throwable $e){
            //Unknown but not Fatal Exception
            $ne = new UnknownException($e);
            $resp->setHeader("HTTP", true, $ne->getStatusCode());
            $resp->send($ne->getHttpBody());
            $eDesc = describeException($e);
            Log::e($eDesc, "UnkownException");
        }

        //Max Request Restart Support (Linux Only)
        if(self::$configs["max_request_restart"] && !think_core_is_win()){
            static $request_count = 0;
            if(++$request_count >= self::$configs['max_request_limit']){
                self::stop();
            }
        }

    }

    /**
     * Worker onWorkerReload Callback
     *
     * @return void
     */
    public static function onWorkerReload(){
        /** Configs Initialization */
        Config::_init();

        /** Logger Initialization */
        Log::_init(Config::get("log"));

        /** Static File Dispatcher Initialization */
        StaticDispatcher::_init();

        /** Dispatcher Initialization */
        Dispatcher::_init();

        /** Router Initialization */
        Route::_init(Config::get('', "vhost"));

        /** Task Client Initialization */
        if(Config::get("task.enable")){
           TaskClient::_init(Config::get("task"));
        }
    }

    /**
     * Run all workers
     *
     * @return void
     */
    public static function run(){
        Worker::runAll();
    }

    /**
     * Stop current worker process
     *
     * @return void
     */
    public static function stop(){
        Worker::stopAll();
    }
}