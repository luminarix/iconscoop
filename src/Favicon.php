<?php

declare(strict_types=1);

namespace Luminarix\IconScoop;

use Illuminate\Support\Facades\Config;
use Stringable;

final class Favicon implements Stringable
{
    public function __construct(
        public ?string $imageUrl = null,
        public ?string $contentLocation = null,
        public readonly int $statusCode = 200,
    ) {
        $this->imageUrl ??= secure_asset('vendor/iconscoop/' . Config::string('iconscoop.default_icon'));
        $this->contentLocation ??= $this->imageUrl;
    }

    public function __toString(): string
    {
        return (string)$this->imageUrl;
    }
}
