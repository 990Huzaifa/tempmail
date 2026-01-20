<?php

namespace App\Http\Controllers;

use App\Events\NewMailReceived;
use App\Models\DomainRotation;
use App\Models\EmailAttachment;
use App\Models\EmailLog;
use App\Models\TempAlias;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

class WebhookController extends Controller
{
    public function mailboxes(Request $request): JsonResponse
    {
        $data = DomainRotation::select('id','master_email as user', 'master_password as password')->get();

        return response()->json($data);
    }

    public function webhook(Request $request): JsonResponse
    {
        // Handle incoming webhook data
        Log::info('Webhook received:', $request->all());
        $alias = TempAlias::where('alias_email', $request->input('to'))->first();
        if (!$alias) {
            Log::warning('Alias not found for email: ' . $request->input('to'));
            return response()->json(['status' => 'alias not found'], 404);
        }

        $bodyHtml = $request->input('html_body') ?? $request->input('text_body') ?? '';

        $initialSize = strlen($bodyHtml);

        $log = EmailLog::create([
            'user_id'       => $alias->user_id,
            'temp_alias_id' => $alias->id,
            'from_email'    => $request->input('from_email'),
            'from_name'     => $request->input('from_name'),
            'subject'       => $request->input('subject'),
            'body_html'     => $bodyHtml,
            'mail_size'     => $initialSize, // initial size without attachments
            'received_at'   => now(),
        ]);

        if($request->input('has_attachment') == true) {
            return response()->json(['status' => 'success', 'id' => $log->id, 'attachment' => true]);
        }else{
            broadcast(new NewMailReceived($log));

            // send fcm notification
            // $user = User::find($alias->user_id);
            // if ($user && $user->fcm_token != null) {
            //     $fcmService = new FirebaseService();
            //     $fcmService->sendToDevice(
            //         $user->fcm_token,
            //         'New Email Received',
            //         'You have received a new email from ' . $log->from_email,
            //         [
            //             'email_log_id' => $log->id,
            //             'alias_id'     => $alias->id,
            //             'sender'       => $log->from_email,
            //             'subject'      => $log->subject,
            //         ]
            //     );
            // }

            // update mail size
        }
        return response()->json(['status' => 'success']);
    }

    public function attachmentWebhook(Request $request, $id): JsonResponse
    {
        $log = EmailLog::find($id);
        if (!$log) {
            Log::warning('EmailLog not found for ID: ' . $id);
            return response()->json(['status' => 'email log not found'], 404);
        }

        // ✅ attachments[] array receive karo
        if (!$request->hasFile('attachments')) {
            Log::warning('No attachments found for EmailLog ID: ' . $id);
            return response()->json(['status' => 'no attachments'], 400);
        }

        // $saved = [];
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

            $fileName = uniqid('att_') . '.' . $extension;

            // ✅ safe public path (or storage)
            $attachment->move(public_path('user-attachment'), $fileName);

            // (optional) DB me save
            EmailAttachment::create([
                'email_log_id' => $log->id,
                'file_name'     => $originalName,
                'file_path'    => 'user-attachment/' . $fileName,
                'file_type'    => $mimeType,
                'file_size'    => $fileSize,
            ]);

            // $saved[] = $fileName;
        }
        $log->increment('mail_size', $totalAttachmentSize);
        broadcast(new NewMailReceived($log));

            // send fcm notification
        // $user = User::find($log->user_id);
        // if ($user && $user->fcm_token != null) {
        //     $fcmService = new FirebaseService();
        //     $fcmService->sendToDevice(
        //         $user->fcm_token,
        //         'New Email Received',
        //         'You have received a new email from ' . $log->from_email,
        //         [
        //             'email_log_id' => $log->id,
        //             'alias_id'     => $log->temp_alias_id,
        //             'sender'       => $log->from_email,
        //             'subject'      => $log->subject,
        //         ]
        //     );
        // }
        // Log::info('Attachments saved for EmailLog ID: ' . $id . ', Files: ' . implode(', ', $saved));

        return response()->json(['status' => 'success']);
    }

}