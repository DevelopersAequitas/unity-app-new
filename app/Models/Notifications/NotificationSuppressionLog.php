<?php
namespace App\Models\Notifications;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class NotificationSuppressionLog extends Model { use HasUuids; protected $fillable=['user_id','campaign_id','type','dedupe_key','last_sent_at','send_count']; protected $casts=['last_sent_at'=>'datetime'];}
