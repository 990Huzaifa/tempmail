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
                "country" => "PK",
                "phone" => "+92.3133054378",
                "email" => "surajkumar00244vk@gmail.com",
            ];

            $namecheap = new NamecheapService();

            $res = $namecheap->purchaseDomain($domain,$userData);
            if ($res !== true) {
                // Debugging ke liye full error check karein
                $error = isset($res->Errors->Error) ? (string)$res->Errors->Error : 'Unknown Error';
                return response()->json([
                    'status' => 'error',
                    'message' => $error,
                    'raw_response' => $res // Is se aapko XML ki detail mil jayegi
                ], 400);
            }
            return response()->json(['status' => 'success', 'message' => 'Domain bought!'], 200);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }

    }

    public function searchCheapDomain(Request $request)
    {
        try{
            $keyword = $request->keyword ?? null;
            $customTlds = $request->customTlds ?? null;

            $namecheap = new NamecheapService();

            $res = $namecheap->searchCheapDomain($keyword,$customTlds);

             return response()->json(['status' => 'success', 'result' => $res], 200);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }
    }
}
