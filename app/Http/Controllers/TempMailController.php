<?php

namespace App\Http\Controllers;

use App\Models\DomainRotation;
use App\Models\TempAlias;
use App\Services\ModoboaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $user = Auth::user(); // Logged in user ko lein, agar available ho
            $domain = $request->domain ?? null;
            $alias = $request->alias ?? null;
            $getDomain = null;
            if($domain == null){
                $getDomain = DomainRotation::where('type','public')->where('is_active',1)->orderBy('alias_count','desc')->first();
                $domain = $getDomain->domain_name;
            }else{
                $getDomain = DomainRotation::where('domain_name',$domain)->where('type','public')->where('is_active',1)->first();
                if(!$getDomain){
                    throw new Exception("Domain not found or inactive", 404);
                }
            }
            if($alias == null){
                $alias = Str::random(4);
            }
            

            $aliasEmail = $alias.'@' . $domain;
            $forwardEmail = 'master@' . $domain;


            $response = $this->modoboa->createTempAlias($aliasEmail, $forwardEmail);
            if($response['status'] == 'error') throw new Exception($response['data'], 500);

            $aliasRecord = TempAlias::create([
                'user_id'     => $user->id, // Agar user logged in hai
                'alias_email' => $aliasEmail,
                'domain_id'   => $getDomain->id,
                'expires_at'  => now()->addHours(24) 
            ]);

        return response()->json(['email' => $aliasEmail]);
            return response()->json($response,200);
        }catch(Exception $e){
            return response()->json($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
