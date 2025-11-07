<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\housingCms;

class StaticpageController extends Controller
{
    public function index($param){
        if($param !== '') {
            $param = str_replace('-', '_', $param);
            if($param == 'notice'){
                $data = housingCms::where('content_type', $param)->get();
            }else{
                $data = housingCms::where('content_type', $param)->first();
            }
        }
        return response()->json($data);
    }
}
