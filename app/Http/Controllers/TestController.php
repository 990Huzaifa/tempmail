<?php

namespace App\Http\Controllers;

use App\Jobs\AddDomainToModoboaJob;
use App\Jobs\FetchDnsFromModoboaJob;
use App\Services\ModoboaService;
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
            if ($res['success'] != true) {
                // Debugging ke liye full error check karein
                $error = isset($res->Errors->Error) ? (string)$res->Errors->Error : 'Unknown Error';
                return response()->json([
                    'status' => 'error',
                    'message' => $error,
                    'raw_response' => $res // Is se aapko XML ki detail mil jayegi
                ], 400);
            }
            return response()->json(['status' => 'success', 'message' => 'Domain bought!','raw_response' => $res], 200);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }

    }

    public function searchCheapDomain(Request $request)
    {
        try{
            $keyword = $request->keyword ?? null;
            $customTld = $request->ltd ?? null;

            $namecheap = new NamecheapService();

            $res = $namecheap->searchCheapDomain($keyword,$customTld);
            if (isset($res['success']) && $res['success'] && !empty($res['list'])) {

                $list = $res['list'];

                $maxIndex = min(count($list) - 1, 3);
                $randomIndex = rand(0, $maxIndex);
                $selectedDomain = $list[$randomIndex];

                $pricingarray = $namecheap->getTldPrice($selectedDomain);
                $buyable = false;
                if($pricingarray['currency'] == 'USD' && $pricingarray['price'] <= 3) $buyable = true;
                return response()->json([
                    'status' => 'success', 
                    'result' => $selectedDomain,
                    'buyable' => $buyable
                ], 200);
            }
            return response()->json(['status' => 'error', 'message' => 'No domains found'], 404);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }
    }


    public function getDomainInfo($id){
        $modoboa = new ModoboaService();

        $res = $modoboa->getDomainDetails($id);

        FetchDnsFromModoboaJob::dispatch($id);

        return $res['dkim_public_key'];
    }

    public function getList(){
        $namecheap = new NamecheapService();
        $res = $namecheap->getlist();


        return response()->json($res);
    }

    public function addDomain(Request $request)
    {
        $domain = $request->domain;
        $price = $request->price;
        AddDomainToModoboaJob::dispatch($domain,$price);
        return response()->json('added');
    }


    public function creatAccount(Request $request)
    {
        $email = 'master@' . $request->domain;

        $modoboa = new ModoboaService();
        $modoboa->createAccount($email);
    }
}
