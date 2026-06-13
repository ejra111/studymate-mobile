<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class OcrService
{
    /**
     * Extract text from image using Tesseract OCR
     */
    public function extractText(string $imagePath): string
    {
        $startTime = microtime(true);
        Log::info('Starting OCR extraction', ['image_path' => $imagePath]);

        // Using exec to call tesseract with optimized parameters for numbers
        $output = [];
        $returnCode = 0;
        
        // Command: use PSM 6 (assume a single uniform block of text)
        $command = "tesseract " . escapeshellarg($imagePath) . " stdout -l eng --psm 6 -c tessedit_char_whitelist=0123456789NIMabcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-";
        
        exec($command, $output, $returnCode);
        
        $duration = round(microtime(true) - $startTime, 3);
        
        if ($returnCode !== 0) {
            Log::error('Tesseract OCR failed', ['return_code' => $returnCode, 'command' => $command, 'image_path' => $imagePath]);
            return '';
        }

        $rawText = implode("\n", $output);
        Log::info('OCR extraction complete', [
            'image_path' => $imagePath,
            'duration_seconds' => $duration,
            'raw_text_length' => strlen($rawText),
            'raw_text' => $rawText,
        ]);

        return $rawText;
    }

    /**
     * Normalize OCR text: fix common misreadings (O ↔ 0, l ↔ 1, etc.)
     */
    private function normalizeOcrText(string $text): string
    {
        // First, remove all non-digit/letter/whitespace characters
        $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
        
        // Fix common misreadings
        $replacements = [
            'O' => '0',
            'o' => '0',
            'I' => '1',
            'l' => '1',
            '|' => '1',
            '!' => '1',
            'S' => '5',
            's' => '5',
            'B' => '8',
            'g' => '9',
            'G' => '9',
        ];

        foreach ($replacements as $from => $to) {
            $text = str_replace($from, $to, $text);
        }

        return $text;
    }

    /**
     * Extract NIM from OCR text
     */
    public function extractNim(string $ocrText): ?string
    {
        Log::info('Starting NIM extraction', ['ocr_text' => $ocrText]);

        // First try with normalized text
        $normalizedText = $this->normalizeOcrText($ocrText);
        Log::info('Normalized OCR text', ['normalized_text' => $normalizedText]);

        // Common NIM patterns (usually 8-15 digits)
        $patterns = [
            '/NIM[^\d]*(\d{8,15})/i',
            '/NIM.*?(\d{8,15})/i',
            '/(\d{8,15})/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedText, $matches)) {
                Log::info('Found potential NIM with pattern', ['pattern' => $pattern, 'extracted_nim' => $matches[1]]);
                return $matches[1];
            }
        }

        $digitsOnlyNormalized = preg_replace('/\D/', '', $normalizedText);
        if (preg_match('/(\d{8,15})/', $digitsOnlyNormalized, $matches)) {
            Log::info('Found NIM in normalized digits-only string', ['extracted_nim' => $matches[1]]);
            return $matches[1];
        }
        
        $digitsOnlyOriginal = preg_replace('/\D/', '', $ocrText);
        if (preg_match('/(\d{8,15})/', $digitsOnlyOriginal, $matches)) {
            Log::info('Found NIM in original digits-only string', ['extracted_nim' => $matches[1]]);
            return $matches[1];
        }
        
        Log::info('Failed to extract any NIM from OCR text');
        return null;
    }

    /**
     * Verify if NIM from OCR matches user's NIM
     */
    public function verifyNim(string $imagePath, string $userNim): bool
    {
        $startTime = microtime(true);

        Log::info('Starting NIM verification', [
            'image_path' => $imagePath,
            'user_nim' => $userNim,
        ]);

        $text = $this->extractText($imagePath);
        $normalizedUser = preg_replace('/\D/', '', $userNim);
        $digitsFromOcr = preg_replace('/\D/', '', $this->normalizeOcrText($text));
        if ($normalizedUser && $digitsFromOcr && str_contains($digitsFromOcr, $normalizedUser)) {
            $duration = round(microtime(true) - $startTime, 3);
            Log::info('NIM verification complete (substring match)', [
                'image_path' => $imagePath,
                'user_nim' => $userNim,
                'normalized_user_nim' => $normalizedUser,
                'digits_from_ocr' => $digitsFromOcr,
                'is_match' => true,
                'duration_seconds' => $duration,
            ]);
            return true;
        }

        $extractedNim = $this->extractNim($text);
        
        if (!$extractedNim) {
            Log::info('NIM extraction failed during verification', ['image_path' => $imagePath]);
            return false;
        }

        // Normalize both NIMs for comparison
        $normalizedExtracted = preg_replace('/\D/', '', $extractedNim);

        $isMatch = $normalizedExtracted === $normalizedUser
            || ($normalizedExtracted && $normalizedUser && str_contains($normalizedExtracted, $normalizedUser))
            || ($normalizedExtracted && $normalizedUser && str_contains($normalizedUser, $normalizedExtracted));
        $duration = round(microtime(true) - $startTime, 3);

        Log::info('NIM verification complete', [
            'image_path' => $imagePath,
            'user_nim' => $userNim,
            'normalized_user_nim' => $normalizedUser,
            'extracted_nim' => $extractedNim,
            'normalized_extracted_nim' => $normalizedExtracted,
            'is_match' => $isMatch,
            'duration_seconds' => $duration,
        ]);
        
        return $isMatch;
    }
}
