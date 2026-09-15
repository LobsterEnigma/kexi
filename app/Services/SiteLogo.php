<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class SiteLogo
{
    public function validName(?string $name): bool
    {
        return is_string($name) && preg_match('/\A[a-f0-9]{32}\.(png|jpg|webp)\z/', $name) === 1;
    }

    public function exists(?string $name): bool
    {
        return $this->validName($name) && Storage::disk('local')->exists('branding/'.$name);
    }

    public function delete(?string $name): void
    {
        if ($this->validName($name)) {
            Storage::disk('local')->delete('branding/'.$name);
        }
    }
}
