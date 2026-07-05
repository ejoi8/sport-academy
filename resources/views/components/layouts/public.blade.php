@props(['title' => null])

@php
    $title = $title;
@endphp

@include('layouts.public', ['title' => $title, 'slot' => $slot])
