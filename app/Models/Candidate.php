<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Candidate extends Model
{
    protected $fillable = [
        'position_id',
        'name',
        'photo_path',
        'sort_order',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(collect($parts)->map(fn (string $part) => mb_substr($part, 0, 1))->take(2)->implode(''));
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path || ! File::exists(public_path($this->photo_path))) {
            return null;
        }

        return asset($this->photo_path);
    }

    public function storeUploadedPhoto(UploadedFile $file): void
    {
        $directory = public_path('media/candidates');
        File::ensureDirectoryExists($directory);

        if ($this->photo_path && File::exists(public_path($this->photo_path))) {
            File::delete(public_path($this->photo_path));
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $filename = $this->id.'-'.Str::lower(Str::random(6)).'.'.$extension;
        $file->move($directory, $filename);

        $this->update(['photo_path' => 'media/candidates/'.$filename]);
    }
}
