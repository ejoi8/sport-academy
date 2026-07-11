<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\Response;

/**
 * robots.txt + sitemap.xml, served dynamically so the Sitemap URL always matches the current
 * domain and the sitemap lists exactly the programmes that are live and publicly bookable.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /app\nDisallow: /family\n\nSitemap: ".route('sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
        ];

        Program::query()
            ->where('is_active', true)
            ->whereHas('offerings', fn ($query) => $query->publiclyBookable())
            ->orderBy('name')
            ->get()
            ->each(function (Program $program) use (&$urls): void {
                $urls[] = ['loc' => route('programs.show', $program), 'changefreq' => 'weekly', 'priority' => '0.8'];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc><changefreq>'.$url['changefreq'].'</changefreq><priority>'.$url['priority'].'</priority></url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
