<?php
namespace App\Models\Notifications;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class NotificationDeliveryLog extends Model { use HasUuids; protected $fillable=['notification_id','user_id','channel','provider','provider_message_id','status','request_payload','response_payload','error_message','attempted_at','delivered_at']; protected $casts=['request_payload'=>'array','response_payload'=>'array','attempted_at'=>'datetime','delivered_at'=>'datetime'];}
