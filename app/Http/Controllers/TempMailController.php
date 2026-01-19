<?php

namespace App\Http\Controllers;

use App\Models\DomainRotation;
use App\Models\EmailLog;
use App\Models\TempAlias;
use App\Services\ModoboaService;
use Exception;
use Illuminate\Database\QueryException;
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
                $getDomain = DomainRotation::where('type','public')->where('is_active',1)->orderBy('alias_count','asc')->first();
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
            $getDomain->increment('alias_count');

        return response()->json(['email' => $aliasEmail]);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function mailBox(Request $request): JsonResponse
    {
        $user = Auth::user();
        $alias = $user->tempAlias();

        $mails = EmailLog::where('temp_alias_id', $alias->id)
        ->with('attachments')
        ->get();

        return response()->json(['mails' => $mails]);
    }
}
