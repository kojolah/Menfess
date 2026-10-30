<?php

namespace App\Services;

class MenfessAutoReviewService
{
    /** @return list<string> */
    public function reasons(string $body): array
    {
        $reasons = [];
        if (preg_match('/\b[\w.+-]+@[\w.-]+\.[A-Z]{2,}\b/iu', $body) === 1) {
            $reasons[] = 'possible_personal_email';
        }
        if (preg_match('/(?<!\d)(?:\+?62|0)(?:[\s().-]*\d){8,13}(?!\d)/u', $body) === 1) {
            $reasons[] = 'possible_personal_phone';
        }
        if (preg_match('/(?<!\d)\d{16}(?!\d)/u', $body) === 1) {
            $reasons[] = 'possible_identity_number';
        }

        return $reasons;
    }
}
