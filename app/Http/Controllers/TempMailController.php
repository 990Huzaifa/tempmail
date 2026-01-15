<?php

namespace App\Http\Controllers;

use App\Models\DomainRotation;
use App\Services\ModoboaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TempMailController extends Controller
{
    protected $modoboa;
    public function __construct(ModoboaService $modoboaService) {
        
        $this->modoboa = $modoboaService;
    }
    public function generateMail(Request $request): JsonResponse
    {
        try{

            $domain = $request->domain ?? null;
            $alias = $request->alias ?? null;
            if($domain == null){
                $getDomain = DomainRotation::where('type','public')->where('is_active',1)->orderBy('alias_count','dese')->first();
                $domain = $getDomain->domain_name;
            }
            if($alias == null){
                $alias = Str::random(4);
            }
            

            $aliasEmail = $alias.'@' . $domain;
            $forwardEmail = 'master@' . $domain;


            $response = $this->modoboa->createTempAlias($aliasEmail, $forwardEmail);
            if($response['status'] == 'error') throw new Exception($response['data'], 500);
            return response()->json($response,200);
        }catch(Exception $e){
            return response()->json($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
