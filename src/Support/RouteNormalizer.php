<?php

declare(strict_types=1);

namespace Tracium\Laravel\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

final class RouteNormalizer
{
    public function normalize(Request $request): string
    {
        $route = ($request->getRouteResolver())();

        if ($route instanceof Route) {
            $uri = trim((string) $route->uri(), '/');

            return '/'.$uri;
        }

        $segments = explode('/', trim($request->path(), '/'));
        $normalized = array_map(static function (string $segment): string {
            if (
                ctype_digit($segment)
                || preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27,}$/i', $segment) === 1
                || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $segment) === 1
            ) {
                return '{id}';
            }

            return $segment;
        }, $segments);

        return '/'.implode('/', $normalized);
    }

    public function name(Request $request): ?string
    {
        $route = ($request->getRouteResolver())();

        if (! $route instanceof Route) {
            return null;
        }

        $name = $route->getName();

        return is_string($name) && $name !== '' ? $name : null;
    }
}
