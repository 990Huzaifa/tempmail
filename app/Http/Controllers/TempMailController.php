<?php

namespace App\Http\Controllers;

use App\Models\DomainRotation;
use App\Models\EmailLog;
use App\Models\TempAlias;
use App\Models\TempAliasForwarding;
use App\Services\ModoboaService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
            if($user->isPremium()  == false){
                $oldAlias = TempAlias::where('user_id', $user->id)->first();
                if($oldAlias){
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
        $tzcode = $request->header('Timezone', 'UTC');
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


        return response()->json(['alias' => $alias->alias_email, 'mails' => $mails]);
    }

    public function readMail(Request $request, $mailId): JsonResponse
    {
        $mail = EmailLog::find($mailId);

        if (!$mail) {
            return response()->json(['error' => 'Mail not found'], 404);
        }

        // Mark as read
        if (!$mail->is_read) {
            $mail->update(['is_read' => now()->toISOString()]);
        }

        return response()->json(['mail' => $mail, 'message' => 'Mail marked as read']);
    }
    public function emailList(Request $request): JsonResponse
    {
        $user = Auth::user();
        $aliases = TempAlias::where('user_id', $user->id)->get();

        if (!$aliases) {
            return response()->json(['error' => 'No temporary alias found'], 404);
        }

        return response()->json(['inbox' => $aliases]);
    }
    public function activateMailboxes($id): JsonResponse
    {
        $user = Auth::user();
        // set all alias in_use to false
        TempAlias::where('user_id', $user->id)->update(['in_use' => false]);
        $alias = TempAlias::where('user_id', $user->id)->where('id', $id)->first();
        if (!$alias) {
            return response()->json(['error' => 'Alias not found'], 404);
        }
        $alias->update(['in_use' => true]);

        return response()->json(['success' => 'Mailbox activated']);
    }


    public function deleteMailbox($id): JsonResponse
    {
        $user = Auth::user();
        $alias = TempAlias::where('user_id', $user->id)->where('id', $id)->first();
        if (!$alias) {
            return response()->json(['error' => 'Alias not found'], 404);
        }
        // delete from modoboa
        $this->modoboa->deleteTempAlias($alias->alias_modoboa_id);
        // decrement domain alias count
        $domain = DomainRotation::where('id', $alias->domain_id)->first();
        if($domain){
            $domain->decrement('alias_count');
        }
        // delete from DB
        $alias->delete();

        return response()->json(['success' => 'Mailbox deleted']);
    }

    public function deleteMail(Request $request, $mailId): JsonResponse
    {
        $user = Auth::user();
        $mail = EmailLog::find($mailId);

        if (!$mail) {
            return response()->json(['error' => 'Mail not found'], 404);
        }

        // Check if the mail belongs to the user's alias
        $alias = TempAlias::where('user_id', $user->id)->where('id', $mail->temp_alias_id)->first();
        if (!$alias) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete the mail
        $mail->delete();

        return response()->json(['success' => 'Mail deleted']);
    }


    public function setupForwarding(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'temp_alias_id' => 'required|exists:temp_aliases,id',
            'recipients' => 'required|array|min:1', // Ab hum array le rahe hain
            'recipients.*' => 'email', // Array ka har item valid email hona chahiye
            'keep_local' => 'required|boolean'
        ],
        [
            'temp_alias_id.required' => 'Temp alias ID is required',
            'temp_alias_id.exists' => 'Temp alias not found',
            'recipients.required' => 'Recipients are required',
            'recipients.array' => 'Recipients must be an array',
            'recipients.min' => 'At least one recipient is required',
            'recipients.*.email' => 'Invalid email format',
            'keep_local.required' => 'Keep local is required',
            'keep_local.boolean' => 'Keep local must be a boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $alias = TempAlias::findOrFail($request->temp_alias_id);
        $newEmails = $request->recipients;

        if($request->keep_local){
            $masterEmail = $alias->domain->master_email;
            if (!$masterEmail) {
                return response()->json(['error' => 'Master email not found for this domain'], 404);
            }            
        }

        $newEmails = array_merge([$masterEmail], $newEmails);
        // 1. Database logic: Purani forwarding delete karke nayi insert karein (Ya update karein)
        // Hum simple approach use kar rahe hain: delete old, insert new
        $alias->forwarding()->delete(); 
        
        $allRecipients = implode(',', $newEmails);
        // insert array of new emails in forwarding table
        TempAliasForwarding::create([
            'temp_alias_id' => $alias->id,
            'recipients' => $allRecipients
        ]);

                
        // 2. Modoboa API call (PK use karte hue)
        
        // Modoboa API call (PK use karte hue)
        $sync = $this->modoboa->updateAliasRecipients($alias->alias_modoboa_id, $newEmails);

        if ($sync) {
            return response()->json([
                'status' => 'success',
                'message' => 'Forwarding updated for ' . count($newEmails) . ' recipients.'
            ]);
        }

        return response()->json(['message' => 'Failed to sync with Modoboa server'], 500);
    }

    // Other methods...
    public function domainlist(Request $request): JsonResponse
    {
        $domains = DomainRotation::select('domain_name','type','id')->where('is_active',1)->get();
        return response()->json(['domains' => $domains]);
    }

    public function show($id): JsonResponse
    {
        try{
            $mail = EmailLog::with('attachments')->find($id);
            if (!$mail) {
                return response()->json(['error' => 'Mail not found'], 404);
            }
            return response()->json(['mail' => $mail]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
