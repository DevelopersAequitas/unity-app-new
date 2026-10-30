<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use Illuminate\Support\Facades\Schema;

class AiKnowledgeRagService
{
    public function __construct(
        protected GeminiService $geminiService
    ) {}

    /**
     * Split a knowledge document into ~300-350 word chunks and store with embeddings.
     */
    public function syncDocumentChunks(AiKnowledgeDocument $document): int
    {
        if (! Schema::hasTable('ai_knowledge_chunks')) {
            return 0;
        }

        $document->chunks()->delete();

        $chunks = $this->splitTextIntoChunks((string) $document->raw_content, 320);
        $count = 0;

        foreach ($chunks as $chunkText) {
            $embedding = $this->geminiService->embedText($chunkText);

            AiKnowledgeChunk::create([
                'document_id' => $document->id,
                'chunk_text' => $chunkText,
                'embedding_json' => $embedding,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Search top 4 relevant knowledge chunks using vector cosine similarity + keyword score.
     *
     * @return array<int, array{document_title: string, category: string, content: string}>
     */
    public function searchKnowledgeDocs(string $query, int $limit = 4): array
    {
        if (! Schema::hasTable('ai_knowledge_chunks') || ! Schema::hasTable('ai_knowledge_documents')) {
            return [];
        }

        $chunks = AiKnowledgeChunk::query()
            ->with('document:id,title,category,is_active')
            ->whereHas('document', function ($q): void {
                $q->where('is_active', true);
            })
            ->get();

        if ($chunks->isEmpty()) {
            return [];
        }

        $queryEmbedding = $this->geminiService->embedText($query);
        $keywords = array_filter(
            preg_split('/\s+/', strtolower(trim($query))) ?: [],
            fn (string $w): bool => strlen($w) >= 3
        );

        $scored = [];

        foreach ($chunks as $chunk) {
            $score = 0.0;
            $chunkEmbedding = $chunk->embedding_json;

            if (is_array($queryEmbedding) && is_array($chunkEmbedding) && count($queryEmbedding) === count($chunkEmbedding)) {
                $score += $this->cosineSimilarity($queryEmbedding, $chunkEmbedding) * 0.75;
            }

            $haystack = strtolower($chunk->chunk_text.' '.($chunk->document->title ?? ''));
            foreach ($keywords as $word) {
                if (str_contains($haystack, $word)) {
                    $score += 0.15;
                }
            }

            if ($score > 0.0) {
                $scored[] = [
                    'score' => $score,
                    'document_title' => (string) ($chunk->document->title ?? 'Peers Global Guide'),
                    'category' => (string) ($chunk->document->category ?? 'general'),
                    'content' => (string) $chunk->chunk_text,
                ];
            }
        }

        if ($scored === []) {
            return $chunks->take($limit)->map(fn (AiKnowledgeChunk $c): array => [
                'document_title' => (string) ($c->document->title ?? 'Peers Global Guide'),
                'category' => (string) ($c->document->category ?? 'general'),
                'content' => (string) $c->chunk_text,
            ])->values()->all();
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(fn (array $item): array => [
            'document_title' => $item['document_title'],
            'category' => $item['category'],
            'content' => $item['content'],
        ], array_slice($scored, 0, $limit));
    }

    /**
     * Split long text into chunks of roughly $targetWords words.
     *
     * @return array<int, string>
     */
    public function splitTextIntoChunks(string $text, int $targetWords = 320): array
    {
        $normalized = trim(preg_replace("/\r\n|\r/", "\n", $text) ?? $text);
        if ($normalized === '') {
            return [];
        }

        $paragraphs = preg_split("/\n{2,}/", $normalized) ?: [$normalized];
        $chunks = [];
        $currentChunk = '';
        $currentWords = 0;

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }

            $wordCount = str_word_count($para);
            if ($currentWords + $wordCount > $targetWords && $currentChunk !== '') {
                $chunks[] = trim($currentChunk);
                $currentChunk = $para;
                $currentWords = $wordCount;
            } else {
                $currentChunk .= ($currentChunk !== '' ? "\n\n" : '').$para;
                $currentWords += $wordCount;
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * @param  array<int, float>  $vecA
     * @param  array<int, float>  $vecB
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vecA as $i => $valA) {
            $valB = (float) ($vecB[$i] ?? 0.0);
            $dot += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}

