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
use Illuminate\Support\Facades\Log;
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
                $getDomain = DomainRotation::where('domain_name',$domain)->where('is_active',1)->first();
                if(!$getDomain){
                    throw new Exception("Domain not found or inactive", 404);
                }
            }
            if($alias == null){
                $alias = Str::random(4);
            }
            

            $aliasEmail = $alias.'@' . $domain;
            $forwardEmail = 'master@' . $domain;

            // make in lower case alias email
            $aliasEmail = Str::lower($aliasEmail);

            // check  availability in DB
            $existingAlias = TempAlias::where('alias_email', $aliasEmail)->first();
            if ($existingAlias) {
                return response()->json(['error' => 'Already in use'], 409);
            }

            $response = $this->modoboa->createTempAlias($aliasEmail, $forwardEmail);
            if($response['status'] == 'error') throw new Exception($response['data'], 500);

            // delete old alias if exists for the user on free plan and create new one
            if(!$user->isPremium()){
                $oldAlias = TempAlias::where('user_id', $user->id)->first();
                if($user->isPremium()  == false){
                    $this->modoboa->deleteTempAlias($oldAlias->alias_modoboa_id);
                    $getDomain->decrement('alias_count');
                    $oldAlias->delete();
                }

                $aliasRecord = TempAlias::create([
                    'user_id'     => $user->id, // Agar user logged in hai
                    'alias_modoboa_id' => $response['data']['pk'],
                    'alias_email' => $aliasEmail,
                    'domain_id'   => $getDomain->id,
                    'expires_at'  => now()->addMinutes('10'),
                    'in_use'      => true 
                ]);
            }else{
                // set all existing alias in_use to false
                TempAlias::where('user_id', $user->id)->update(['in_use' => false]);
                // create new alias record with in_use true
                $aliasRecord = TempAlias::create([
                    'user_id'     => $user->id, // Agar user logged in hai
                    'alias_modoboa_id' => $response['data']['pk'],
                    'alias_email' => $aliasEmail,
                    'domain_id'   => $getDomain->id,
                    'expires_at'  => null,
                    'in_use'      => true 
                ]);
            }
            $getDomain->increment('alias_count');

        return response()->json(['email' => $aliasEmail]);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 100 && $code <= 599) ? $code : 500;
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    public function mailBox(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = TempAlias::where('user_id', $user->id)->orderBy('created_at', 'desc');

        if($user->isPremium()){
            $query = $query->where('in_use', true);
        }
        $alias = $query->first();

        if (!$alias) {
            return response()->json(['error' => 'No temporary alias found'], 404);
        }
        $mails = EmailLog::where('temp_alias_id', $alias->id)
        ->with('attachments')
        ->get();

        return response()->json(['mails' => $mails]);
    }

    public function readMail(Request $request, $mailId): JsonResponse
    {
        $mail = EmailLog::find($mailId);

        if (!$mail) {
            return response()->json(['error' => 'Mail not found'], 404);
        }

        // Mark as read
        if (!$mail->is_read) {
            $mail->update(['is_read' => now()]);
        }

        return response()->json(['mail' => $mail, 'message' => 'Mail marked as read']);
    }

    public function activateMailboxes(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $alias = TempAlias::where('user_id', $user->id)->where('id', $id)->first();
        if (!$alias) {
            return response()->json(['error' => 'Alias not found'], 404);
        }
        $alias->update(['in_use' => true]);

        return response()->json(['success' => 'Mailbox activated']);
    }

    // Other methods...
    public function domainlist(Request $request): JsonResponse
    {
        $domains = DomainRotation::select('domain_name','type','id')->where('is_active',1)->get();
        return response()->json(['domains' => $domains]);
    }
}
