<?php

namespace App\Services\Search;

/**
 * Interface for search indexing and querying drivers
 */
interface SearchServiceInterface
{
    /**
     * Add or update a document in the search index.
     *
     * @param string $index The name of the index (e.g. 'posts', 'threads')
     * @param string $id The unique identifier of the document
     * @param array $document Associative array of document fields to index
     * @return bool True on success, false on failure
     */
    public function index(string $index, string $id, array $document): bool;

    /**
     * Search the index for a given query.
     *
     * @param string $index The name of the index
     * @param string $query The search query string
     * @param array $filters Additional filters (optional)
     * @return array List of matched document IDs (or full documents if retrieved by driver)
     */
    public function search(string $index, string $query, array $filters = []): array;

    /**
     * Remove a document from the search index.
     *
     * @param string $index The name of the index
     * @param string $id The unique identifier of the document
     * @return bool True on success, false on failure
     */
    public function delete(string $index, string $id): bool;
}
