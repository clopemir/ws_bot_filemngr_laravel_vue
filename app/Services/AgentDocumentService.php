<?php

namespace App\Services;

use App\Models\Client;
use App\Models\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Encuentra clientes y documentos a partir de lo que escribe un agente, tolerando errores de dedo.
 * Los agentes pueden consultar a cualquier cliente del despacho, no solo a los asignados.
 */
class AgentDocumentService
{
    private const RFC_PATTERN = '/(?<![a-zñ&0-9])[a-zñ&]{3,4}\d{6}(?:[a-z0-9]{3})?(?![a-z0-9])/u';

    /** Palabras que no identifican ni a un cliente ni a un documento. */
    private const STOP_WORDS = [
        'a', 'al', 'algo', 'ando', 'aqui', 'buen', 'buena', 'buenas', 'buenos', 'cliente', 'clienta', 'como', 'con', 'cual', 'cuales',
        'da', 'dame', 'de', 'del', 'deme', 'dia', 'dias', 'el', 'en', 'empresa', 'envia', 'enviame', 'enviar', 'envie', 'envies',
        'es', 'favor', 'gracias', 'hay', 'hola', 'la', 'las', 'le', 'lo', 'los', 'manda', 'mandame', 'mandar', 'mande', 'mandes', 'me',
        'mi', 'necesito', 'noches', 'ocupo', 'para', 'pasa', 'pasame', 'pasar', 'pase', 'pases', 'pf', 'podrias', 'por', 'porfa', 'porfavor',
        'puedes', 'que', 'quiero', 'requiero', 'se', 'si', 'sobre', 'solicito', 'su', 'sus', 'tambien', 'tarde', 'tardes', 'te', 'tenga',
        'tiene', 'tienes', 'todo', 'todos', 'todas', 'ultima', 'ultimo', 'un', 'una', 'y', 'ya',
    ];

    /** Palabras que indican que se piden archivos, sin decir cuáles. */
    private const GENERIC_DOCUMENT_WORDS = ['archivo', 'archivos', 'doc', 'docs', 'documento', 'documentos', 'pdf', 'descarga', 'descargar'];

    /** Formas habituales de nombrar los documentos del despacho. */
    private const SYNONYMS = [
        'csf' => 'constancia',
        'situacion' => 'constancia',
        'fiscal' => 'constancia',
        'cumplimiento' => 'opinion',
        '32d' => 'opinion',
        'declaracion' => 'impuesto',
    ];

    /**
     * Interpreta el mensaje sin usar IA. Solo reconoce lo que puede afirmar con seguridad.
     *
     * @return array{clients: Collection<int, Client>, term: ?string, has_other_words: bool, wants_documents: bool}
     */
    public function parse(string $message): array
    {
        $lower = Str::lower($message);
        $clients = collect();

        if (preg_match(self::RFC_PATTERN, $lower, $match)) {
            $clients = Client::where('client_rfc', $match[0])->get();
            $lower = str_replace($match[0], ' ', $lower);
        }

        $tokens = $this->significantTokens($lower);
        $vocabulary = $this->documentVocabulary();

        $generic = array_values(array_intersect($tokens, self::GENERIC_DOCUMENT_WORDS));
        $tokens = array_values(array_diff($tokens, self::GENERIC_DOCUMENT_WORDS));

        $documentTokens = array_values(array_filter($tokens, fn (string $token) => $this->matchesAny($token, $vocabulary)));
        $otherTokens = array_values(array_diff($tokens, $documentTokens));

        // Un año o número acompaña al documento ("constancia 2024"), no al nombre del cliente.
        if ($documentTokens !== []) {
            $numbers = array_filter($otherTokens, 'ctype_digit');
            $documentTokens = array_merge($documentTokens, $numbers);
            $otherTokens = array_values(array_diff($otherTokens, $numbers));
        }

        if ($clients->isEmpty() && $otherTokens !== []) {
            $clients = $this->findClients(implode(' ', $otherTokens));
        }

        return [
            'clients' => $clients,
            'term' => $documentTokens !== [] ? implode(' ', $documentTokens) : null,
            'has_other_words' => $otherTokens !== [],
            'wants_documents' => $documentTokens !== [] || $generic !== [],
        ];
    }

    /**
     * Clientes que coinciden con un nombre o RFC. Si hay coincidencias exactas, descarta las aproximadas.
     *
     * @return Collection<int, Client>
     */
    public function findClients(string $query): Collection
    {
        $lower = Str::lower(trim($query));

        if (preg_match(self::RFC_PATTERN, $lower, $match)) {
            $byRfc = Client::where('client_rfc', $match[0])->get();

            if ($byRfc->isNotEmpty()) {
                return $byRfc;
            }
        }

        $queryTokens = $this->significantTokens($lower);

        if ($queryTokens === []) {
            return collect();
        }

        $scored = Client::query()->get(['id', 'agent_id', 'client_name', 'client_lname', 'client_rfc'])
            ->map(fn (Client $client) => ['client' => $client, 'score' => $this->clientScore($client, $queryTokens)])
            ->filter(fn (array $row) => $row['score'] > 0);

        $best = $scored->max('score');

        return $scored->where('score', $best)->pluck('client')->values();
    }

    /**
     * @return array{files: Collection<int, File>, total: int, label: string}
     */
    public function findFiles(Client $client, string $term): array
    {
        $tokens = $this->significantTokens(Str::lower($term));
        $categories = $this->categoriesFor($client);
        $expanded = array_map(fn (string $token) => self::SYNONYMS[$token] ?? $token, $tokens);

        $matched = array_values(array_filter($categories, function (string $category) use ($expanded) {
            $categoryTokens = $this->significantTokens(Str::lower($category));

            return collect($expanded)->contains(fn (string $token) => $this->matchesAny($token, $categoryTokens));
        }));

        $query = File::where('client_rfc', $client->client_rfc)->latest();
        $files = $matched !== [] ? (clone $query)->whereIn('category', $matched)->get() : $query->get();

        // Lo que no describe la categoría (un año, un mes) sirve para afinar por nombre de archivo.
        $extras = array_filter($tokens, function (string $token) use ($matched) {
            $categoryTokens = collect($matched)->flatMap(fn (string $category) => $this->significantTokens(Str::lower($category)))->all();
            $canonical = self::SYNONYMS[$token] ?? $token;

            return !$this->matchesAny($canonical, $categoryTokens);
        });

        $narrowed = $files->filter(fn (File $file) => $extras !== [] && collect($extras)
            ->every(fn (string $token) => Str::contains($this->normalize($file->original_file_name), $token)));

        if ($narrowed->isNotEmpty()) {
            $files = $narrowed;
        } elseif ($matched === []) {
            $files = collect();
        }

        return [
            'files' => $files->take(max(1, (int) config('whatsapp_bot.agent_max_files', 5)))->values(),
            'total' => $files->count(),
            'label' => $matched !== [] ? implode(', ', $matched) : $term,
        ];
    }

    /**
     * @return string[]
     */
    public function categoriesFor(Client $client): array
    {
        return File::where('client_rfc', $client->client_rfc)
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }

    /**
     * @param  string[]  $queryTokens
     */
    private function clientScore(Client $client, array $queryTokens): int
    {
        $nameTokens = $this->tokens(Str::lower("{$client->client_name} {$client->client_lname}"));
        $score = 0;

        foreach ($queryTokens as $token) {
            $points = 0;

            foreach ($nameTokens as $nameToken) {
                $points = max($points, match (true) {
                    $token === $nameToken => 3,
                    strlen($token) >= 3 && str_starts_with($nameToken, $token) => 2,
                    $this->isSimilar($token, $nameToken) => 1,
                    default => 0,
                });
            }

            // Todas las palabras deben coincidir: una sola que falte descarta al cliente.
            if ($points === 0) {
                return 0;
            }

            $score += $points;
        }

        return $score;
    }

    /**
     * @return string[]
     */
    private function documentVocabulary(): array
    {
        $fromCategories = File::query()
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->pluck('category')
            ->flatMap(fn (string $category) => $this->significantTokens(Str::lower($category)))
            ->all();

        return array_values(array_unique(array_merge(
            $fromCategories,
            array_keys(self::SYNONYMS),
            array_values(self::SYNONYMS),
        )));
    }

    /**
     * @param  string[]  $candidates
     */
    private function matchesAny(string $token, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->stem($token) === $this->stem($candidate) || $this->isSimilar($token, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isSimilar(string $a, string $b): bool
    {
        $length = min(strlen($a), strlen($b));

        if ($length < 5) {
            return false;
        }

        return levenshtein($a, $b) <= ($length >= 8 ? 2 : 1);
    }

    private function stem(string $token): string
    {
        return preg_replace('/(es|s)$/', '', $token);
    }

    /**
     * @return string[]
     */
    private function significantTokens(string $text): array
    {
        return array_values(array_diff($this->tokens($text), self::STOP_WORDS));
    }

    /**
     * @return string[]
     */
    private function tokens(string $text): array
    {
        return array_values(array_filter(explode(' ', $this->normalize($text))));
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));
    }
}
