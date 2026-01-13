<?php

namespace App\Http\Controllers;

use App\Services\NamecheapService;
use Exception;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function purchaseDomain(Request $request)
    {
        try{
            $domain = $request->domain;
            $userData = [
                "first_name" => "suraj",
                "last_name" => "kumar",
                "address" => "Anum Empire",
                "city" => "karachi",
                "state" => "sindh",
                "zip" => "75500",
                "country" => "pakistan",
                "phone" => "3133054378",
                "email" => "surajkumar00244vk@gmail.com",
            ];

            $namecheap = new NamecheapService();

            $res = $namecheap->purchaseDomain($domain,$userData);
            if($res->Status != "OK"){
                throw new Exception($res, 500);
                
            }
            return response()->json($res, 200);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }

    }
}
