<?php

namespace App\Services\Search;

/**
 * Meilisearch Service Client using raw PHP cURL.
 */
class MeilisearchService implements SearchServiceInterface
{
    protected string $key;
    protected string $endpoint;

    /**
     * Create a new MeilisearchService instance.
     *
     * @param array $config The credential configurations (already decrypted)
     */
    public function __construct(array $config)
    {
        $this->key = $config['MEILISEARCH_KEY'] ?? '';
        $this->endpoint = rtrim($config['MEILISEARCH_ENDPOINT'] ?? 'http://127.0.0.1:7700', '/');
    }

    /**
     * Add or update a document in the search index.
     */
    public function index(string $index, string $id, array $document): bool
    {
        // Meilisearch requires an 'id' attribute on the document
        $document['id'] = (string)$id;
        
        // Wrap document in an array since Meilisearch expects a list of documents
        $payload = [$document];

        $response = $this->request('POST', "/indexes/{$index}/documents", $payload);
        return $response['code'] >= 200 && $response['code'] < 300;
    }

    /**
     * Search the index for a query.
     */
    public function search(string $index, string $query, array $filters = []): array
    {
        $payload = ['q' => $query];

        // Build Meilisearch filters: e.g. "category_id = 5 AND user_id = 10"
        $filterArray = [];
        if (!empty($filters['category_id'])) {
            $filterArray[] = "category_id = " . (int)$filters['category_id'];
        }
        if (!empty($filters['user_id'])) {
            $filterArray[] = "user_id = " . (int)$filters['user_id'];
        }

        if (!empty($filterArray)) {
            $payload['filter'] = implode(' AND ', $filterArray);
        }

        $response = $this->request('POST', "/indexes/{$index}/search", $payload);
        if ($response['code'] !== 200) {
            return [];
        }

        $data = json_decode($response['body'], true);
        if (empty($data['hits'])) {
            return [];
        }

        // Extract IDs from hits
        $ids = [];
        foreach ($data['hits'] as $hit) {
            if (isset($hit['id'])) {
                $ids[] = $hit['id'];
            }
        }

        return $ids;
    }

    /**
     * Remove a document from the search index.
     */
    public function delete(string $index, string $id): bool
    {
        $response = $this->request('DELETE', "/indexes/{$index}/documents/{$id}");
        return $response['code'] >= 200 && $response['code'] < 300;
    }

    /**
     * Execute a cURL request to Meilisearch server.
     */
    protected function request(string $method, string $uri, ?array $payload = null): array
    {
        $ch = curl_init();
        $url = $this->endpoint . $uri;

        $headers = [
            'Content-Type: application/json',
        ];
        if (!empty($this->key)) {
            $headers[] = 'Authorization: Bearer ' . $this->key;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Fail fast to not block the main request thread

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['code' => 0, 'error' => $error];
        }

        curl_close($ch);
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
}
