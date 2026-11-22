<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OutreachMessage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Taslak (onay bekliyor)',
        self::STATUS_APPROVED => 'Onaylandı',
        self::STATUS_REJECTED => 'Reddedildi',
        self::STATUS_QUEUED => 'Gönderim kuyruğunda',
        self::STATUS_SENT => 'Gönderildi',
        self::STATUS_FAILED => 'Hata',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'first_clicked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message) {
            $message->token ??= Str::random(32);
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function isSendable(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_QUEUED], true);
    }
}
