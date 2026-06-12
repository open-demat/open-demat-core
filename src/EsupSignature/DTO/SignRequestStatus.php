<?php

namespace OpenDemat\Core\EsupSignature\DTO;

enum SignRequestStatus: string
{
    case Uploading = 'uploading';
    case Draft     = 'draft';
    case Pending   = 'pending';
    case Canceled  = 'canceled';
    case Checked   = 'checked';
    case Signed    = 'signed';
    case Refused   = 'refused';
    case Deleted   = 'deleted';
    case Completed = 'completed';
    case Exported  = 'exported';
    case Archived  = 'archived';
    case Cleaned   = 'cleaned';

    public function isTerminal(): bool
    {
        return match($this) {
            self::Signed, self::Completed, self::Refused, self::Canceled,
            self::Deleted, self::Archived, self::Cleaned => true,
            default => false,
        };
    }
}
