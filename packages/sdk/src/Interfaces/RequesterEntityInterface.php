<?php

namespace MercadoPago\PP\Sdk\Interfaces;

interface RequesterEntityInterface
{
    /**
     * Get and set custom headers for entity.
     *
     * @return array
     */
    public function getHeaders(): array;

    /**
     * Get uris.
     *
     * @return array
     */
    public function getUris(): array;

    /**
     * Read method (GET).
     *
     * @param array $params
     * @param array $queryStrings
     * @param bool  $shouldTheExpectedResponseBeMappedOntoTheEntity
     *
     * @return mixed
     * @throws \Exception
     */
    public function read(
        array $params = [],
        array $queryStrings = [],
        bool $shouldTheExpectedResponseBeMappedOntoTheEntity = true
    );

    /**
     * Save method (POST).
     *
     * @return mixed
     * @throws \Exception
     */
    public function save();

    /**
     * Save method with params (POST).
     *
     * @param array $params
     * @param array $queryStrings
     *
     * @return mixed
     * @throws \Exception
     */
    public function saveWithParams(array $params = [], array $queryStrings = []);
}
