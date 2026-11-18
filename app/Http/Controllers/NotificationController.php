<?php
// Role du fichier: Controleur de consultation et de mise a jour des notifications.
namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['notifications'=>Notification::where('utilisateur_id',$request->user()->id)->latest()->limit(50)->get()->map(function($n){$message=$n->message;$documentId=null;if(preg_match('/^\[document:(\d+)\]\s*/u',$message,$match)){$documentId=(int)$match[1];$message=substr($message,strlen($match[0]));}return ['id'=>$n->id,'type'=>$n->type,'title'=>$n->type,'message'=>$message,'documentId'=>$documentId,'read'=>(bool)$n->lu,'time'=>$n->created_at?->diffForHumans()];})]);
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless($notification->utilisateur_id === $request->user()->id, 403);
        $notification->update(['lu'=>true]);
        return response()->json(['ok'=>true]);
    }

    public function read(Request $request)
    {
        Notification::where('utilisateur_id',$request->user()->id)->where('lu',false)->update(['lu'=>true]);
        return response()->json(['message'=>'Toutes les notifications sont marquées comme lues.']);
    }
}
