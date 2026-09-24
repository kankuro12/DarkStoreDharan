<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate XML sitemap from active products, categories, and city pages (§43.1)';

    public function handle(): int
    {
        $baseUrl = config('app.url', 'http://localhost:8000');

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        // Homepage
        $this->addUrl($xml, $baseUrl, 'daily', '1.0');

        // City Pages (§43.3)
        foreach (City::active()->get() as $city) {
            $this->addUrl($xml, "{$baseUrl}/?city_id={$city->id}", 'daily', '0.9');
        }

        // Category Pages
        foreach (Category::active()->get() as $cat) {
            $this->addUrl($xml, "{$baseUrl}/?category_id={$cat->id}", 'daily', '0.8');
        }

        // Product Pages (§43.1)
        foreach (Product::active()->get() as $prod) {
            $this->addUrl($xml, "{$baseUrl}/products/{$prod->slug}", 'weekly', '0.8');
        }

        // Legal Pages
        $this->addUrl($xml, "{$baseUrl}/privacy-policy", 'monthly', '0.3');
        $this->addUrl($xml, "{$baseUrl}/terms-of-service", 'monthly', '0.3');

        $outputPath = public_path('sitemap.xml');
        $xml->asXML($outputPath);

        $this->info("XML sitemap successfully generated at: {$outputPath}");

        return self::SUCCESS;
    }

    protected function addUrl(\SimpleXMLElement $xml, string $loc, string $changefreq, string $priority): void
    {
        $url = $xml->addChild('url');
        $url->addChild('loc', htmlspecialchars($loc));
        $url->addChild('lastmod', date('Y-m-d'));
        $url->addChild('changefreq', $changefreq);
        $url->addChild('priority', $priority);
    }
}
