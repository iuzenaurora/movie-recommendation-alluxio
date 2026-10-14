<?php

namespace App\Services;

class MovieDataService
{
    public static function generateDynamicSvgPoster(string $title, int $year, string $genre, float $rating): string
    {
        $genreColors = [
            'Action' => ['#ef4444', '#7f1d1d'],
            'Adventure' => ['#f97316', '#7c2d12'],
            'Animation' => ['#06b6d4', '#164e63'],
            'Children' => ['#38bdf8', '#0369a1'],
            'Comedy' => ['#eab308', '#713f12'],
            'Crime' => ['#64748b', '#1e293b'],
            'Documentary' => ['#10b981', '#064e3b'],
            'Drama' => ['#8b5cf6', '#4c1d95'],
            'Fantasy' => ['#d946ef', '#701a75'],
            'Film-Noir' => ['#475569', '#0f172a'],
            'Horror' => ['#dc2626', '#450a0a'],
            'Musical' => ['#ec4899', '#831843'],
            'Mystery' => ['#6366f1', '#312e81'],
            'Romance' => ['#f43f5e', '#881337'],
            'Sci-Fi' => ['#0ea5e9', '#0369a1'],
            'Thriller' => ['#f59e0b', '#78350f'],
            'War' => ['#78716c', '#292524'],
            'Western' => ['#d97706', '#78350f'],
        ];

        $colors = $genreColors[$genre] ?? ['#6366f1', '#1e1b4b'];
        $c1 = $colors[0];
        $c2 = $colors[1];

        $cleanTitle = htmlspecialchars(mb_substr($title, 0, 40), ENT_QUOTES, 'UTF-8');
        $cleanGenre = htmlspecialchars($genre, ENT_QUOTES, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 600" width="100%" height="100%">'
            .'<defs>'
            .'<linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">'
            .'<stop offset="0%" stop-color="'.$c1.'" stop-opacity="0.85"/>'
            .'<stop offset="100%" stop-color="'.$c2.'" stop-opacity="0.95"/>'
            .'</linearGradient>'
            .'<linearGradient id="ov" x1="0%" y1="0%" x2="0%" y2="100%">'
            .'<stop offset="0%" stop-color="#020617" stop-opacity="0.2"/>'
            .'<stop offset="50%" stop-color="#020617" stop-opacity="0.6"/>'
            .'<stop offset="100%" stop-color="#020617" stop-opacity="0.98"/>'
            .'</linearGradient>'
            .'</defs>'
            .'<rect width="400" height="600" fill="#0b0f19"/>'
            .'<rect width="400" height="600" fill="url(#g)"/>'
            .'<circle cx="200" cy="180" r="130" fill="'.$c1.'" opacity="0.3" filter="blur(30px)"/>'
            .'<rect width="400" height="600" fill="url(#ov)"/>'
            .'<g transform="translate(160, 130)" fill="none" stroke="'.$c1.'" stroke-width="2.5" opacity="0.9">'
            .'<rect x="0" y="0" width="80" height="80" rx="20" stroke="rgba(255,255,255,0.25)" fill="rgba(0,0,0,0.35)"/>'
            .'<polygon points="32,24 56,40 32,56" fill="white"/>'
            .'</g>'
            .'<rect x="24" y="24" width="80" height="26" rx="8" fill="rgba(0,0,0,0.65)" stroke="rgba(255,255,255,0.15)"/>'
            .'<text x="64" y="41" fill="#f8fafc" font-family="system-ui,sans-serif" font-size="12" font-weight="700" text-anchor="middle">'.$year.'</text>'
            .'<rect x="290" y="24" width="86" height="26" rx="8" fill="rgba(0,0,0,0.65)" stroke="rgba(255,255,255,0.15)"/>'
            .'<text x="333" y="41" fill="#fbbf24" font-family="system-ui,sans-serif" font-size="12" font-weight="700" text-anchor="middle">★ '.number_format($rating, 1).'</text>'
            .'<rect x="24" y="380" width="'.(mb_strlen($cleanGenre) * 9 + 24).'" height="24" rx="6" fill="'.$c1.'" opacity="0.9"/>'
            .'<text x="36" y="396" fill="#ffffff" font-family="system-ui,sans-serif" font-size="11" font-weight="700">'.$cleanGenre.'</text>'
            .'<text x="24" y="440" fill="#ffffff" font-family="system-ui,sans-serif" font-size="20" font-weight="800" letter-spacing="-0.5">'
            .'<tspan x="24" dy="0">'.htmlspecialchars(mb_substr($title, 0, 24), ENT_QUOTES, 'UTF-8').'</tspan>'
            .(mb_strlen($title) > 24 ? '<tspan x="24" dy="24">'.htmlspecialchars(mb_substr($title, 24, 24), ENT_QUOTES, 'UTF-8').'</tspan>' : '')
            .'</text>'
            .'<text x="24" y="555" fill="#94a3b8" font-family="system-ui,sans-serif" font-size="10" font-weight="600" letter-spacing="1">MOVIELAB / MOVIELENS</text>'
            .'</svg>';

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }
}
