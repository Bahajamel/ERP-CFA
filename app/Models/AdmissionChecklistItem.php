<?php

namespace App\Models;

use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionChecklistItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'est_obligatoire' => 'boolean',
            'statut' => ChecklistItemStatut::class,
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
