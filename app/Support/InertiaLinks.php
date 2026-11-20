<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class InertiaLinks
{
    /**
     * Paginator links, flattened for the browser: labels decoded, no HTML.
     *
     * @param  LengthAwarePaginator<mixed>  $paginator
     * @return list<array{label: string, url: string|null, active: bool}>
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return array_map(fn (array $link): array => [
            'label' => html_entity_decode(strip_tags((string) $link['label']), ENT_QUOTES, 'UTF-8'),
            'url' => $link['url'] === null ? null : (string) $link['url'],
            'active' => (bool) $link['active'],
        ], $paginator->linkCollection()->toArray());
    }
}
