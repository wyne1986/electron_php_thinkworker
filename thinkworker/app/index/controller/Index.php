<?php
/**
 *  ThinkWorker - THINK AND WORK FAST
 *  Copyright (c) 2017 http://thinkworker.cn All Rights Reserved.
 *  Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
 *  Author: Dizy <derzart@gmail.com>
 */

namespace app\index\controller;


class Index
{
    public function index(){
        return "<a href='/test'>test</a>";
    }
	
	public function test(){
		return "test222";
	}
}