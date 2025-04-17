<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Luminarix\IconScoop\Favicon;
use Luminarix\IconScoop\IconScoop;

beforeEach(function () {
    Cache::store('array')->flush();
    $this->service = new IconScoop(
        Http::getFacadeRoot(),
        Cache::store('array')
    );
});

it('fetches favicon from HTML <link> tag for simple domain', function () {
    $favicon = $this->service->find('https://facebook.com');

    expect($favicon)->toBeInstanceOf(Favicon::class)
        ->and($favicon->imageUrl)->toBe('https://www.facebook.com/favicon.ico')
        ->and($favicon->statusCode)->toBe(200);
});

it('uses head() fallback when no <link> tag is present', function () {
    $favicon = $this->service->find('https://x.com');

    expect($favicon->imageUrl)->toBe('https://abs.twimg.com/favicons/twitter.3.ico')
        ->and($favicon->statusCode)->toBe(200);
});

it('uses Google fallback for cloudflare protected domains', function () {
    $domain = 'https://openai.com';
    $faviconUrl = "{$domain}/favicon.ico";
    $fallbackUrl = "https://www.google.com/s2/favicons?domain={$domain}";

    $favicon = $this->service->find($domain);

    expect($favicon->imageUrl)->toBeIn([$fallbackUrl, $faviconUrl])
        ->and($favicon->contentLocation)->toBe($faviconUrl)
        ->and($favicon->statusCode)->toBe(200);
});

it('uses Google fallback for another cloudflare protected domain', function () {
    $domain = 'https://deepseek.com';
    $fallbackUrl = 'https://www.google.com/s2/favicons?domain=' . $domain;

    $favicon = $this->service->find($domain);

    expect($favicon->imageUrl)->toBe($fallbackUrl)
        ->and($favicon->contentLocation)->toBe('https://www.deepseek.com/favicon.ico')
        ->and($favicon->statusCode)->toBe(200);
});

it('returns default icon for domains without favicon', function () {
    $domain = 'https://example.com';

    $favicon = $this->service->find($domain);

    expect($favicon->imageUrl)->toBe(secure_asset('vendor/iconscoop/' . config('iconscoop.default_icon')))
        ->and($favicon->statusCode)->toBe(404);
});
