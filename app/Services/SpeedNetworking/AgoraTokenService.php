<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use RuntimeException;

class AgoraTokenService
{
    public const ROLE_ATTENDEE = 0;

    public const ROLE_PUBLISHER = 1;

    public const ROLE_SUBSCRIBER = 2;

    public const ROLE_ADMIN = 101;

    public const PRIVILEGE_JOIN_CHANNEL = 1;

    public const PRIVILEGE_PUBLISH_AUDIO_STREAM = 2;

    public const PRIVILEGE_PUBLISH_VIDEO_STREAM = 3;

    public const PRIVILEGE_PUBLISH_DATA_STREAM = 4;

    /**
     * Generate an Agora RTC Token for a channel and participant.
     *
     * @param  string  $channelName  The target room channel name
     * @param  int|string  $uid  The Agora user identifier (0 for wildcard / string uid)
     * @param  int  $expireSeconds  Validity window in seconds
     * @return string Signed Agora RTC Token
     */
    public function generateToken(string $channelName, int|string $uid = 0, int $expireSeconds = 600): string
    {
        $appId = (string) config('services.agora.app_id');
        $appCertificate = (string) config('services.agora.app_certificate');

        if (empty($appId) || empty($appCertificate)) {
            throw new RuntimeException('Agora credentials (AGORA_APP_ID / AGORA_APP_CERTIFICATE) are not set.');
        }

        $privilegeExpireTs = time() + max(60, $expireSeconds);

        return self::buildRtcToken(
            $appId,
            $appCertificate,
            $channelName,
            $uid,
            self::ROLE_PUBLISHER,
            $privilegeExpireTs
        );
    }

    /**
     * Build standard Agora RTC Token006.
     */
    public static function buildRtcToken(
        string $appId,
        string $appCertificate,
        string $channelName,
        int|string $uid,
        int $role,
        int $privilegeExpireTs
    ): string {
        $uidStr = ($uid === 0 || $uid === '0') ? '' : (string) $uid;

        $privileges = [
            self::PRIVILEGE_JOIN_CHANNEL => $privilegeExpireTs,
        ];

        if ($role === self::ROLE_PUBLISHER || $role === self::ROLE_ADMIN || $role === self::ROLE_ATTENDEE) {
            $privileges[self::PRIVILEGE_PUBLISH_AUDIO_STREAM] = $privilegeExpireTs;
            $privileges[self::PRIVILEGE_PUBLISH_VIDEO_STREAM] = $privilegeExpireTs;
            $privileges[self::PRIVILEGE_PUBLISH_DATA_STREAM] = $privilegeExpireTs;
        }

        $salt = random_int(1, 999999);
        $ts = time() + (24 * 3600);

        $msg = pack('V', $salt)
            .pack('V', $ts)
            .pack('v', count($privileges));

        foreach ($privileges as $key => $value) {
            $msg .= pack('v', $key).pack('V', $value);
        }

        $val = $appId.$channelName.$uidStr.$msg;
        $sig = hash_hmac('sha256', $val, $appCertificate, true);

        $crcChannel = crc32($channelName) & 0xFFFFFFFF;
        $crcUid = crc32($uidStr) & 0xFFFFFFFF;

        $content = pack('v', strlen($sig))
            .$sig
            .pack('V', $crcChannel)
            .pack('V', $crcUid)
            .pack('v', strlen($msg))
            .$msg;

        return '006'.$appId.base64_encode($content);
    }
}
