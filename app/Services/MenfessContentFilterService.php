<?php

namespace App\Services;

use App\Models\ContentFilterException;
use App\Models\ContentFilterRule;

class MenfessContentFilterService
{
    /** @return array{body: string, chars: list<array{char: string, start: int, end: int}>} */
    private function compact(string $value): array
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $compact = '';
        $map = [];
        $offset = 0;

        foreach ($chars as $char) {
            $start = $offset;
            $offset += strlen($char);
            $normalized = class_exists(\Normalizer::class) ? \Normalizer::normalize($char, \Normalizer::FORM_KD) : $char;
            $normalized = mb_strtolower($normalized ?: $char, 'UTF-8');
            $normalized = strtr($normalized, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '@' => 'a', '5' => 's', '$' => 's', '7' => 't']);

            foreach (preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $normalizedChar) {
                if (preg_match('/[\pL\pN]/u', $normalizedChar) !== 1) {
                    continue;
                }
                $compact .= $normalizedChar;
                $map[] = ['char' => $char, 'start' => $start, 'end' => $offset];
            }
        }

        return ['body' => $compact, 'chars' => $map];
    }

    public function filter(string $body): MenfessContentFilterResult
    {
        $rules = ContentFilterRule::query()->where('is_active', true)->orderByRaw('LENGTH(phrase) DESC')->get();
        $exceptions = ContentFilterException::query()->where('is_active', true)->get();
        $bodyCompact = $this->compact($body);
        $exceptionSpans = [];

        foreach ($exceptions as $exception) {
            foreach ($this->spans($body, $bodyCompact, $exception->phrase) as $span) {
                $exceptionSpans[] = $span;
            }
        }

        $censorSpans = [];
        $reviewReasons = [];
        foreach ($rules as $rule) {
            foreach ($this->spans($body, $bodyCompact, $rule->phrase) as $span) {
                if ($this->isExcepted($span, $exceptionSpans)) {
                    continue;
                }

                if ($rule->action === 'review') {
                    $reviewReasons[] = $rule->category;
                } else {
                    $censorSpans[] = $span;
                }
            }
        }

        $censorSpans = $this->mergeSpans($censorSpans);
        foreach (array_reverse($censorSpans) as $span) {
            $body = substr_replace($body, '****', $span['start'], $span['end'] - $span['start']);
        }

        return new MenfessContentFilterResult($body, count($censorSpans), array_values(array_unique($reviewReasons)));
    }

    /** @param array{body: string, chars: list<array{char: string, start: int, end: int}>} $compact
     * @return list<array{start: int, end: int}>
     */
    private function spans(string $source, array $compact, string $phrase): array
    {
        $needle = $this->compact($phrase)['body'];
        if ($needle === '' || $compact['body'] === '') {
            return [];
        }

        $spans = [];
        $offset = 0;
        while (($position = strpos($compact['body'], $needle, $offset)) !== false) {
            $last = $position + strlen($needle) - 1;
            if (isset($compact['chars'][$position], $compact['chars'][$last])) {
                $start = $compact['chars'][$position]['start'];
                $end = $compact['chars'][$last]['end'];
                $before = substr($source, 0, $start);
                $after = substr($source, $end);
                $previous = $before === '' ? '' : mb_substr($before, -1, 1, 'UTF-8');
                $next = $after === '' ? '' : mb_substr($after, 0, 1, 'UTF-8');

                if (($previous === '' || preg_match('/[\pL\pN_]/u', $previous) !== 1)
                    && ($next === '' || preg_match('/[\pL\pN_]/u', $next) !== 1)) {
                    $spans[] = ['start' => $start, 'end' => $end];
                }
            }
            $offset = $position + max(strlen($needle), 1);
        }

        return $spans;
    }

    /** @param array{start: int, end: int} $span
     * @param  list<array{start: int, end: int}>  $exceptions
     */
    private function isExcepted(array $span, array $exceptions): bool
    {
        foreach ($exceptions as $exception) {
            if ($exception['start'] <= $span['start'] && $exception['end'] >= $span['end']) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{start: int, end: int}> $spans
     * @return list<array{start: int, end: int}>
     */
    private function mergeSpans(array $spans): array
    {
        usort($spans, fn (array $left, array $right): int => $left['start'] <=> $right['start']);
        $merged = [];
        foreach ($spans as $span) {
            $last = array_key_last($merged);
            if ($last !== null && $span['start'] <= $merged[$last]['end']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $span['end']);
            } else {
                $merged[] = $span;
            }
        }

        return $merged;
    }
}
