<?php

namespace App\Http\Controllers;

use App\Models\DomainRotation;
use App\Models\EmailLog;
use App\Models\SentBox;
use App\Models\SentBoxAttachment;
use App\Models\TempAlias;
use App\Models\TempAliasForwarding;
use App\Services\ModoboaService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            
            if($response['status'] == 'error') throw new Exception("This mail already exists", 400);

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
        $tzcode = $request->header('Timezone', 'UTC');


        $aliasQuery = TempAlias::where('user_id', $user->id)->orderBy('created_at', 'desc');

        if($user->isPremium() == false){
            $aliasQuery->where('in_use', true);
        }

        $aliasIds = $aliasQuery->pluck('id');

        if ($aliasIds->isEmpty()) {
            return response()->json([
                'error' => 'No aliases found'
            ], 404);
        }

        $mails = EmailLog::whereIn('temp_alias_id', $aliasIds)
        ->with('attachments')
        ->orderBy('created_at', 'desc')
        ->get();


        return response()->json(['mails' => $mails]);
    }

    public function sentBox(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tzcode = $request->header('Timezone', 'UTC');


        $aliasQuery = TempAlias::where('user_id', $user->id)->orderBy('created_at', 'desc');

        if($user->isPremium() == false){
            $aliasQuery->where('in_use', true);
        }

        $aliasIds = $aliasQuery->pluck('id');

        if ($aliasIds->isEmpty()) {
            return response()->json([
                'error' => 'No aliases found'
            ], 404);
        }

        $mails = SentBox::whereIn('temp_alias_id', $aliasIds)
        ->with('attachments')
        ->orderBy('created_at', 'desc')
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
            'recipients' => $allRecipients,
            'keep_local' => $request->keep_local
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

    public function forwardingList(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 10);
            $query = TempAliasForwarding::select('temp_alias_forwardings.*', 'temp_aliases.alias_email')
            ->join('temp_aliases', 'temp_aliases.id', '=', 'temp_alias_forwardings.temp_alias_id')
            ->where('temp_aliases.user_id', $user->id);

            $data = $query->paginate($perPage);

            return response()->json(['data' => $data]);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // composing

    public function composeMail(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'from' => 'required|email|exists:temp_aliases,alias_email',
                'to' => 'required|array|min:1', // Ab hum array le rahe hain
                'to.*' => 'email', // Array ka har item valid email hona chahiye
                'subject' => 'required|string',
                'body' => 'required|string',
                'attachments' => 'array',
            ],[
                'to.required' => 'To is required',
                'to.array' => 'To must be an array',
                'to.min' => 'At least one recipient is required',
                'to.*.email' => 'Invalid email format',
                'subject.required' => 'Subject is required',
                'body.required' => 'Body is required',
                'attachments.array' => 'Attachments must be an array',
            ]);
            if($validator->fails()){
                return response()->json(['error' => $validator->errors()], 422);
            }

            $alias = TempAlias::with('domain')->where('user_id', $user->id)->where('alias_email', $request->from)->firstOrFail();
            if(!$alias){
                return response()->json(['error' => 'Alias not found'], 404);
            }
            $masterUser = $alias->domain->master_email; // e.g. master@1ozzmail.store
            $fromEmail  = $alias->alias_email;         // Jo temp mail user use kar raha hai

            $result = $this->modoboa->sendOutgoingEmail(
                $masterUser,
                $fromEmail,
                $request->to, 
                $request->subject,
                $request->body,
                $request->file('attachments') ?? []
            );
            DB::beginTransaction();
            // store data in DB
            $initialSize = strlen($request->body);
            $sentmail = SentBox::create([
                'user_id' => $user->id,
                'from_email' => $fromEmail,
                'to_email' => implode(',', $request->to),
                'subject' => $request->subject,
                'body_html' => $request->body,
                'temp_alias_id' => $alias->id,
                'mail_size' => $initialSize, // initial size without attachments
            ]);

            // add attachment
            if($request->input('has_attachment') == true) {
                $saved = [];
                $totalAttachmentSize = 0;
                foreach ($request->file('attachments') as $attachment) {
                    if (!$attachment->isValid()) {
                        continue;
                    }
                    
                    $originalName = $attachment->getClientOriginalName();
                    $mimeType     = $attachment->getClientMimeType();
                    $fileSize     = $attachment->getSize(); // 👈 SAFE HERE
                    $extension    = $attachment->getClientOriginalExtension();

                    $totalAttachmentSize += $fileSize;

                    $fileName = uniqid('sent_att_') . '.' . $extension;

                    // ✅ safe public path (or storage)
                    $attachment->move(public_path('user-attachment'), $fileName);

                    // (optional) DB me save
                    SentBoxAttachment::create([
                        'sent_box_id' => $sentmail->id,
                        'file_name'     => $originalName,
                        'file_path'    => 'user-attachment/' . $fileName,
                        'file_type'    => $mimeType,
                        'file_size'    => $fileSize,
                    ]);

                    $saved[] ='user-attachment/' . $fileName;
                }
                $sentmail->increment('mail_size', $totalAttachmentSize);

            }
            DB::commit();
            // store data in DB
            if (isset($result['status']) && $result['status'] === 'success') {
                return response()->json(['message' => 'Email sent successfully!']);
            }

            return response()->json(['error' => 'Failed to send email'], 500);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 500);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
