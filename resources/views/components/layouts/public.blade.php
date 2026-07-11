@props(['title' => null, 'description' => null, 'ogImage' => null, 'noindex' => false])

@include('layouts.public', [
    'title' => $title,
    'description' => $description,
    'ogImage' => $ogImage,
    'noindex' => $noindex,
    'slot' => $slot,
])
