<?php

namespace App\Http\Controllers;

class TestController
{
    public function index()
    {
        $this->notExistMethod();
    }
}
