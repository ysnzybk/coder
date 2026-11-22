<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    public const TYPE_SITE_VIEW = 'site_view';

    public const TYPE_EMAIL_OPEN = 'email_open';

    public const TYPE_EMAIL_CLICK = 'email_click';

    public const TYPE_OPT_OUT = 'opt_out';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function outreachMessage(): BelongsTo
    {
        return $this->belongsTo(OutreachMessage::class);
    }
}
