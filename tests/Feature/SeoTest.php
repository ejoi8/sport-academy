<?php

use App\Models\Offering;
use App\Models\Program;
use Database\Seeders\BaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(BaselineSeeder::class));

it('emits full SEO + OG meta on the home page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach ([
        '<meta name="description"',
        '<link rel="canonical"',
        'property="og:type" content="website"',
        'property="og:title"',
        'property="og:description"',
        'property="og:url"',
        'property="og:site_name"',
        'name="twitter:card"',
        'application/ld+json',
        '"@type":"SportsActivityLocation"',
        'content="index, follow"',
    ] as $needle) {
        expect($html)->toContain($needle);
    }
});

it('gives each programme its own title + description', function () {
    $program = Program::where('name', 'Group')->firstOrFail();

    $html = $this->get('/programs/'.$program->id)->assertOk()->getContent();

    expect($html)->toContain('<title>Group · ')
        ->and($html)->toContain('property="og:title" content="Group · ');
});

it('keeps the transactional booking flow out of the index', function () {
    $offering = Offering::where('is_open', true)->firstOrFail();

    $html = $this->get('/book/'.$offering->id)->assertOk()->getContent();

    expect($html)->toContain('content="noindex, follow"');
});

it('serves a robots.txt that points at the sitemap and hides the panel', function () {
    $response = $this->get('/robots.txt')->assertOk();
    $response->assertHeader('content-type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain('Sitemap: '.route('sitemap'))
        ->toContain('Disallow: /app');
});

it('serves a sitemap listing the home and live programmes', function () {
    $response = $this->get('/sitemap.xml')->assertOk();
    $response->assertHeader('content-type', 'application/xml; charset=UTF-8');

    $program = Program::where('name', 'Group')->firstOrFail();

    expect($response->getContent())
        ->toContain('<loc>'.route('home').'</loc>')
        ->toContain('<loc>'.route('programs.show', $program).'</loc>');
});
