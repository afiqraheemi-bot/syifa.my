<?php

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'https://syifa.my', '/');
$expected = [
    'Syifa Trial' => 0,
    'Syifa Basic' => 29900,
    'Syifa Pro' => 39900,
];
ksort($expected);

/** @return array<string, mixed> */
function inertiaPage(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'timeout' => 20,
            'user_agent' => 'SYIFA-production-catalogue-verifier/1.0',
        ],
    ]);
    $html = file_get_contents($url, false, $context);

    if (! is_string($html) || ! preg_match('/data-page="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException(sprintf('Unable to read an Inertia response from %s.', $url));
    }

    $page = json_decode(
        html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    if (! is_array($page)) {
        throw new RuntimeException(sprintf('Invalid Inertia page received from %s.', $url));
    }

    return $page;
}

/**
 * @param  list<array<string, mixed>>  $offers
 * @return array<string, array{id: string, amountMinor: int, currency: string}>
 */
function normalizedOffers(array $offers, string $nameKey, string $idKey): array
{
    $normalized = [];

    foreach ($offers as $offer) {
        $name = $offer[$nameKey] ?? null;
        $id = $offer[$idKey] ?? null;
        $amount = $offer['amountMinor'] ?? null;
        $currency = $offer['currency'] ?? 'MYR';

        if (! is_string($name) || ! is_string($id) || ! is_int($amount) || ! is_string($currency)) {
            throw new RuntimeException('A catalogue offer has an invalid public contract.');
        }

        if (isset($normalized[$name])) {
            throw new RuntimeException(sprintf('Duplicate public offer detected for %s.', $name));
        }

        $normalized[$name] = ['id' => $id, 'amountMinor' => $amount, 'currency' => $currency];
    }

    ksort($normalized);

    return $normalized;
}

try {
    $home = inertiaPage($baseUrl.'/');
    $registration = inertiaPage($baseUrl.'/register');

    $homeOffers = normalizedOffers($home['props']['packages'] ?? [], 'name', 'id');
    $registrationOffers = normalizedOffers(
        $registration['props']['offers'] ?? [],
        'planName',
        'planOfferingId',
    );

    if (array_keys($homeOffers) !== array_keys($expected)) {
        throw new RuntimeException('The public homepage does not expose exactly the official packages.');
    }

    foreach ($expected as $name => $amountMinor) {
        $homeOffer = $homeOffers[$name];
        $registrationOffer = $registrationOffers[$name] ?? null;

        if ($homeOffer['amountMinor'] !== $amountMinor || $homeOffer['currency'] !== 'MYR') {
            throw new RuntimeException(sprintf('Homepage price verification failed for %s.', $name));
        }

        if ($registrationOffer !== $homeOffer) {
            throw new RuntimeException(sprintf('Registration offer does not match the homepage for %s.', $name));
        }
    }

    fwrite(STDOUT, "Production catalogue verified: Trial MYR 0, Basic MYR 299, Pro MYR 399.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
