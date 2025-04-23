<?php

declare(strict_types=1);

namespace Luminarix\IconScoop;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Uri;
use Stringable;

final class Favicon implements Stringable
{
    public function __construct(
        public ?string $imageUrl = null,
        public ?string $contentLocation = null,
        public readonly int $statusCode = 200,
    ) {
        $this->imageUrl ??= Uri::of(Config::string('app.url'))
            ->withScheme(Config::string('app.protocol', 'https'))
            ->withPath('vendor/iconscoop/' . Config::string('iconscoop.default_icon'))
            ->getUri()
            ->toString();
        $this->contentLocation ??= $this->imageUrl;
    }

    public function __toString(): string
    {
        return (string)$this->imageUrl;
    }
}
