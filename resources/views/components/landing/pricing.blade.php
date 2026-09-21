@props(['plans', 'branding' => null])

@include('landing.pricing', ['plans' => $plans, 'branding' => $branding])
