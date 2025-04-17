<?php

declare(strict_types=1);

namespace Luminarix\IconScoop;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use GuzzleHttp\Psr7\Uri as GuzzleUri;
use GuzzleHttp\Psr7\UriResolver as GuzzleUriResolver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Uri;
use InvalidArgumentException;

class IconScoop
{
    use Macroable;

    private const string GOOGLE_FALLBACK_URL = 'https://www.google.com/s2/favicons?domain=';

    public function __construct(
        private readonly HttpClient $http,
        private readonly CacheRepository $cache,
        private ?int $timeout = null,
        private string $userAgent = '',
        private ?int $cacheTtl = null,
    ) {
        if ($this->userAgent === '') {
            $this->userAgent = Config::string('iconscoop.user_agent');
        }
        $this->timeout ??= Config::integer('iconscoop.timeout');
        $this->cacheTtl ??= Config::integer('iconscoop.cache.ttl');
    }

    public function find(string $url): Favicon
    {
        $host = parse_url($url, PHP_URL_HOST) ?: md5($url);
        $cacheKey = 'favicon:' . $host;

        /** @var Favicon $favicon */
        $favicon = when(
            condition: Config::boolean('iconscoop.cache.enabled'),
            value: $this->cache->remember(
                $cacheKey,
                $this->cacheTtl,
                fn (): Favicon => $this->fetchFavicon($url)
            ),
            default: $this->fetchFavicon($url)
        );

        return $favicon;
    }

    public function verifyIconUrl(string $iconUrl): ?Response
    {
        try {
            $response = $this->http
                ->withUserAgent($this->userAgent)
                ->timeout($this->timeout / 2)
                ->get($iconUrl);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->successful()) {
            $contentType = $response->header('Content-Type');

            if ($contentType && Str::contains(mb_strtolower($contentType), 'image')) {
                return $response;
            }
        }

        return null;
    }

    private function fetchFavicon(string $url): Favicon
    {
        if ($this->isGoogleFallbackUrl($url)) {
            $response = $this->makeRequest('get', $url);
            if ($response === null) {
                return $this->newDefaultFavicon(404);
            }

            return new Favicon(
                $url,
                $response->header('Content-Location'),
                $response->status()
            );
        }

        $response = $this->makeRequest('get', $url);
        if ($response === null) {
            return $this->newDefaultFavicon(404);
        }

        $baseUrl = $this->determineBaseUrl($response, $url);

        if (!$response->successful()) {
            return $this->handleFailedFetch($response, $url, $baseUrl);
        }

        $html = $response->body();
        $icon = $this->findIconInHtml($html, $baseUrl);
        if ($icon !== null) {
            return new Favicon($icon);
        }

        $icon = $this->findIconInManifest($html, $baseUrl);
        if ($icon !== null) {
            return new Favicon($icon);
        }

        $fallbackIcon = $this->checkFallbackIcon($baseUrl);
        if ($fallbackIcon !== null) {
            return new Favicon($fallbackIcon);
        }

        return $this->newDefaultFavicon(404);
    }

    private function findIconInHtml(string $htmlBody, string $baseUrl): ?string
    {
        if (empty($htmlBody) || empty($baseUrl)) {
            return null;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlBody);
        libxml_clear_errors();

        $relTypes = ['icon', 'shortcut icon', 'apple-touch-icon', 'mask-icon'];
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//head/link[@rel and @href]');

        $iconLinks = [];
        if ($links) {
            /** @var DOMNodeList<DOMElement> $links */
            foreach ($links as $link) {
                $rel = mb_strtolower(mb_trim($link->getAttribute('rel')));
                $href = mb_trim($link->getAttribute('href'));

                if (
                    !empty($href) &&
                    in_array($rel, $relTypes)
                ) {
                    $iconLinks[$rel][] = $href;
                }
            }
        }

        foreach ($relTypes as $relType) {
            if (!empty($iconLinks[$relType])) {
                foreach ($iconLinks[$relType] as $href) {
                    $resolvedUrl = $this->resolveUrl($href, $baseUrl);
                    if ($resolvedUrl && Str::startsWith($resolvedUrl, ['http://', 'https://'])) {
                        return $resolvedUrl;
                    }
                }
            }
        }

        return null;
    }

    private function findIconInManifest(string $htmlBody, string $baseUrl): ?string
    {
        if (empty($htmlBody) || empty($baseUrl)) {
            return null;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlBody);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $manifestLinkElement = $xpath->query('//head/link[@rel="manifest"][@href]');
        if (!$manifestLinkElement) {
            return null;
        }

        /** @var ?DOMElement $manifestLink */
        $manifestLink = $manifestLinkElement->item(0);
        if (!$manifestLink) {
            return null;
        }

        $manifestHref = mb_trim($manifestLink->getAttribute('href'));
        if (empty($manifestHref)) {
            return null;
        }

        $manifestUrl = $this->resolveUrl($manifestHref, $baseUrl);
        if (!$manifestUrl || !Str::startsWith($manifestUrl, ['http://', 'https://'])) {
            return null;
        }

        try {
            $response = $this->http
                ->withUserAgent($this->userAgent)
                ->timeout($this->timeout / 2)
                ->get($manifestUrl);
        } catch (ConnectionException) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $manifestData = $response->json();
        if (!is_array($manifestData) || empty($manifestData['icons']) || !is_array($manifestData['icons'])) {
            return null;
        }

        /** @var array{
         *    src: mixed,
         *  } $icon */
        foreach ($manifestData['icons'] as $icon) {
            if (!empty($icon['src']) && is_string($icon['src'])) {
                $iconSrcUrl = $this->resolveUrl(mb_trim($icon['src']), $manifestUrl);
                if ($iconSrcUrl && Str::startsWith($iconSrcUrl, ['http://', 'https://'])) {
                    return $iconSrcUrl;
                }
            }
        }

        return null;
    }

    private function checkFallbackIcon(string $siteUrl): ?string
    {
        $uri = Uri::of($siteUrl);
        $host = $uri->host();

        if ($host === null) {
            return null;
        }

        $scheme = $uri->scheme() ?: 'https';
        $port = $uri->port() ? ":{$uri->port()}" : '';

        $faviconIcoUrl = "{$scheme}://{$host}{$port}/favicon.ico";

        try {
            $response = $this->http
                ->withUserAgent($this->userAgent)
                ->timeout($this->timeout / 2)
                ->head($faviconIcoUrl);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->successful()) {
            $contentType = $response->header('Content-Type');
            if ($contentType && Str::contains(mb_strtolower($contentType), 'image')) {
                return $faviconIcoUrl;
            }

            if (!$contentType) {
                return $faviconIcoUrl;
            }
        }

        return null;
    }

    private function resolveUrl(string $url, string $baseUrl): ?string
    {
        try {
            $baseUri = new GuzzleUri($baseUrl);
            $relativeUri = new GuzzleUri($url);
            $resolvedUri = GuzzleUriResolver::resolve($baseUri, $relativeUri);

            return (string) $resolvedUri;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function makeRequest(string $method, string $url, int $timeoutDivisor = 1): ?Response
    {
        try {
            return $this->http
                ->withUserAgent($this->userAgent)
                ->timeout($this->timeout / $timeoutDivisor)
                ->{$method}($url);
        } catch (ConnectionException) {
            return null;
        }
    }

    private function determineBaseUrl(Response $response, string $originalUrl): string
    {
        $effectiveUri = $response->effectiveUri();
        if ($effectiveUri === null) {
            $parsed = Uri::of($originalUrl);
            $scheme = $parsed->scheme() ?? 'https';
            $host = $parsed->host() ?? '';

            return "{$scheme}://{$host}";
        }

        return $this->getBaseUrl((string) $effectiveUri);
    }

    private function handleFailedFetch(Response $response, string $url, string $baseUrl): Favicon
    {
        if ($response->status() === 403 || Str::contains($response->body(), 'cf_chl')) {
            return $this->fetchFavicon(self::GOOGLE_FALLBACK_URL . $url);
        }

        $fallbackFavicon = $this->checkFallbackIcon($baseUrl);
        if ($fallbackFavicon === null) {
            return $this->newDefaultFavicon(404);
        }

        return new Favicon($fallbackFavicon);
    }

    private function isGoogleFallbackUrl(string $url): bool
    {
        return Str::startsWith($url, self::GOOGLE_FALLBACK_URL);
    }

    private function newDefaultFavicon(int $statusCode): Favicon
    {
        return new Favicon(statusCode: $statusCode);
    }

    private function getBaseUrl(string $effectiveUrl): string
    {
        $uri = Uri::of($effectiveUrl)
            ->withQuery([], false)
            ->withFragment('');

        $basePath = mb_rtrim(dirname($uri->path() ?? ''), '/') . '/';

        return (string) $uri->withPath($basePath);
    }
}
