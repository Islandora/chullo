<?php

/**
 * This file is part of Islandora.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @category Islandora
 * @package  Islandora
 * @author   Daniel Lamb <dlamb@islandora.ca>
 * @author   Nick Ruest <ruestn@gmail.com>
 * @author   Jared Whiklo <Jared.Whiklo@umanitoba.ca>
 * @author   Diego Pino <dpino@metro.org>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     http://www.islandora.ca
 */

namespace Islandora\Chullo;

use EasyRdf\Graph;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7;
use Psr\Http\Message\ResponseInterface;
use \RuntimeException;

/**
 * Default implementation of IFedoraApi using Guzzle.
 */
class FedoraApi implements IFedoraApi
{

    /**
     * The client.
     *
     * @var \GuzzleHttp\Client
     */
    private Client $client;

    /**
     * Fedora Base URI.
     *
     * @var string
     */
    private string $base_uri;

    /**
     * @codeCoverageIgnore
     */
    private function __construct(string $uri, ?HandlerStack $stack)
    {
        $normalized = rtrim($uri);
        $normalized = rtrim($normalized, '/') . '/';
        $guzzle_opts = ['base_uri' => $normalized];
        if (!is_null($stack)) {
            $guzzle_opts['handler'] = $stack;
        }
        $this->client = new Client($guzzle_opts);
        $this->base_uri = $normalized;
    }

    /**
     * @codeCoverageIgnore
     */
    public static function create(string $fedora_rest_url): self
    {
        return new static($fedora_rest_url, null);
    }

    /**
     * @codeCoverageIgnore
     */
    public static function createWithHandler(string $fedora_rest_url, HandlerStack $stack): self
    {
        return new static($fedora_rest_url, $stack);
    }

    /**
     * @inheritDoc
     */
    public function getBaseUri(): string
    {
        return $this->base_uri;
    }

    /**
     * @inheritDoc
     */
    public function getResource(
        string $uri = "",
        array $headers = []
    ): ResponseInterface {
        // Set headers
        $options = ['http_errors' => false, 'headers' => $headers];

        // Send the request.
        return $this->client->request(
            'GET',
            $uri,
            $options
        );
    }

    /**
     * @inheritDoc
     */
    public function getResourceHeaders(
        string $uri = "",
        array $headers = []
    ): ResponseInterface {
        // Send the request.
        return $this->client->request(
            'HEAD',
            $uri,
            ['http_errors' => false, 'headers' => $headers]
        );
    }

    /**
     * @inheritDoc
     */
    public function getResourceOptions(
        string $uri = "",
        array $headers = []
    ): ResponseInterface {
        return $this->client->request(
            'OPTIONS',
            $uri,
            ['http_errors' => false, 'headers' => $headers]
        );
    }

    /**
     * @inheritDoc
     */
    public function createResource(
        string $uri = "",
        $content = null,
        array $headers = []
    ): ResponseInterface {
        $options = ['http_errors' => false];

        // Set content.
        $options['body'] = $content;

        // Set headers.
        $options['headers'] = $headers;

        return $this->client->request(
            'POST',
            $uri,
            $options
        );
    }

    /**
     * @inheritDoc
     */
    public function saveResource(
        string $uri,
        $content = null,
        array $headers = []
    ): ResponseInterface {
        $options = ['http_errors' => false];

        // Set content.
        $options['body'] = $content;

        // Set headers.
        $options['headers'] = $headers;

        return $this->client->request(
            'PUT',
            $uri,
            $options
        );
    }

    /**
     * @inheritDoc
     */
    public function modifyResource(
        string $uri,
        string $sparql = "",
        array $headers = []
    ): ResponseInterface {
        $options = ['http_errors' => false];

        // Set content.
        $options['body'] = $sparql;

        // Set headers.
        $options['headers'] = $headers;
        $options['headers']['content-type'] = 'application/sparql-update';

        return $this->client->request(
            'PATCH',
            $uri,
            $options
        );
    }

    /**
     * @inheritDoc
     */
    public function deleteResource(
        string $uri = '',
        array $headers = []
    ): ResponseInterface {
        $options = ['http_errors' => false, 'headers' => $headers];

        return $this->client->request(
            'DELETE',
            $uri,
            $options
        );
    }

    /**
     * @inheritDoc
     */
    public function saveGraph(
        Graph $graph,
        string $uri = '',
        array $headers = []
    ): ResponseInterface {
        // Serialze the rdf.
        $turtle = $graph->serialise('turtle');

        // Checksum it.
        $checksum_value = sha1($turtle);

        // Set headers.
        $headers['Content-Type'] = 'text/turtle';
        $headers['digest'] = 'sha1=' . $checksum_value;

        // Save it.
        return $this->saveResource($uri, $turtle, $headers);
    }

    /**
     * @inheritDoc
     */
    public function createGraph(
        Graph $graph,
        string $uri = '',
        array $headers = []
    ): ResponseInterface {
        // Serialize the rdf.
        $turtle = $graph->serialise('turtle');

        // Checksum it.
        $checksum_value = sha1($turtle);

        // Set headers.
        $headers['Content-Type'] = 'text/turtle';
        $headers['digest'] = 'sha1=' . $checksum_value;

        // Save it.
        return $this->createResource($uri, $turtle, $headers);
    }

    /**
     * @inheritDoc
     */
    public function getGraph(ResponseInterface $response): Graph
    {
        // Extract rdf as response body and return Easy_RDF Graph object.
        $rdf = $response->getBody()->getContents();
        $graph = new Graph();
        if (!empty($rdf)) {
            $graph->parse($rdf, 'jsonld');
        }
        return $graph;
    }

    /**
     * @inheritDoc
     */
    public function createVersion(
        $uri = '',
        $timestamp = '',
        $content = null,
        $headers = []
    ): ResponseInterface {
        $timemap_uri = $this->getTimemapURI($uri, $headers);
        if ($timemap_uri == null) {
            throw new RuntimeException(
                'Timemap URI is null, cannot create version'
            );
        }
        $options = ['http_errors' => false];
        if ($timestamp != '' && $content != null) {
            $headers['Memento-Datetime'] = $timestamp;
            $options['body'] = $content;
        }
        $options['headers'] = $headers;

        return $this->client->request(
            'POST',
            $timemap_uri,
            $options
        );
    }

    /**
     * @inheritDoc
     */
    public function getVersions(
        $uri = '',
        $headers = []
    ): ResponseInterface {
        $timemap_uri = $this->getTimemapURI($uri, $headers);
        if ($timemap_uri == null) {
            throw new RuntimeException(
                'Timemap URI is null, cannot create version'
            );
        }

        return $this->getResource($timemap_uri, $headers);
    }

    /**
     * Helper method to get the Headers for a resource
     * and parse the timemap header from it
     * @param string $uri Fedora Resource URI
     * @param array $headers HTTP Headers
     *
     * @return string|null
     */
    public function getTimemapURI(
        string $uri = '',
        array $headers = []
    ): ?string {
        $resource_headers = $this->getResourceHeaders($uri, $headers);
        $parsed_link_headers = Psr7\Header::parse(
            $resource_headers->getHeader('Link')
        );
        $timemap_uri = null;
        $timemap_index = array_search(
            'timemap',
            array_column($parsed_link_headers, 'rel')
        );
        if (is_int($timemap_index)) {
            $timemap_uri = $parsed_link_headers[$timemap_index][0];
            $timemap_uri = trim($timemap_uri, "<> \t\n\r\0\x0B");
        }
        return $timemap_uri;
    }
}
