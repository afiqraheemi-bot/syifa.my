<?php

declare(strict_types=1);

namespace App\Modules\Blog\Domain;

enum BlogPostStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case CorrectionRequired = 'correction_required';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
