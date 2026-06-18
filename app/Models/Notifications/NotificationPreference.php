<?php
namespace App\Models\Notifications;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class NotificationPreference extends Model { use HasUuids; protected $fillable=['user_id','push_enabled','email_enabled','chat_enabled','event_enabled','circle_enabled','business_enabled','campaign_enabled','quiet_hours_start','quiet_hours_end','config']; protected $casts=['push_enabled'=>'boolean','email_enabled'=>'boolean','chat_enabled'=>'boolean','event_enabled'=>'boolean','circle_enabled'=>'boolean','business_enabled'=>'boolean','campaign_enabled'=>'boolean','config'=>'array'];}
