<?php

namespace evandroaugusto\StubHub;

use evandroaugusto\StubHub\StubHubXMLReader;
use evandroaugusto\StubHub\StubHubRepository;
use evandroaugusto\DBCache\DBCache;


class StubhubService
{
    protected $fetcher;
    protected $cache;


    public function __construct($xmlFile, DBCacheService $cache = null)
    {
        if (!isset($xmlFile)) {
            throw new \Exception('You must specify the StubHub xml file');
        }

        // set XML reader
        $xmlFile = new StubHubXMLReader($xmlFile);
        $this->fetcher = new StubHubRepository($xmlFile);
 
        $this->cache = $cache;

        //set default cache table
        if ($this->cache) {
            $this->cache->setTable('thirdapp');
        }
    }


    /**
     * Fetch events
     */
    public function fetchEvents($params)
    {
        // filter allowed parameters
        $validParams = array(
            'orderby', 'order','limit', 'offset',
            'country', 'tickets', 'categoryId', 'cityId'
        );

        $params = $this->validateParameters($params, $validParams);

        // check if using cache
        if (isset($this->cache)) {
            // base cache name
            $cacheId = $this->createHash('events', $params);
            if ($objCache = $this->getCache($cacheId, $params)) {
                return $objCache->data;
            }
        }

        $events = $this->fetcher->fetchEvents($params);

        // Set cache
        if (isset($this->cache) && $events) {
            $this->cache->set($cacheId, $events);
        }

        return $events;
    }

    /**
     * Fetch a event
     */
    public function fetchEvent($params)
    {
        // filter allowed parameters
        $validParams = array(
            'id', 'country', 'tickets'
        );

        $params = $this->validateParameters($params, $validParams);

        // validate fields
        if (!isset($params['id'])) {
            return false;
        }

        // check if using cache
        if (isset($this->cache)) {
            // base cache name
            $cacheId = $this->createHash('event', $params);
            if ($objCache = $this->getCache($cacheId, $params)) {
                return $objCache->data;
            }
        }

        $events = $this->fetcher->fetchEvent($params);

        // Set cache
        if (isset($this->cache) && $events) {
            $this->cache->set($cacheId, $events);
        }

        return $events;
    }

    /**
     * Search categories (artists, festivals)
     */
    public function fetchCategories($params)
    {
        // filter allowed parameters
        $validParams = array(
            'orderby', 'order', 'limit', 'offset',
            'country', 'tickets', 'fields', 'cityId'
        );

        $params = $this->validateParameters($params, $validParams);

        // check if using cache
        if (isset($this->cache)) {
            // base cache name
            $cacheId = $this->createHash('categories', $params);
            if ($objCache = $this->getCache($cacheId, $params)) {
                return $objCache->data;
            }
        }

        $categories = $this->fetcher->fetchCategories($params);

        // Set cache
        if (isset($this->cache) && $categories) {
            $this->cache->set($cacheId, $categories);
        }

        return $categories;
    }

    /**
     * Get a category
     */
    public function fetchCategory($params)
    {
        // filter allowed parameters
        $validParams = array(
            'id', 'orderby', 'order', 'limit',
            'offset', 'country', 'tickets', 'fields'
        );

        $params = $this->validateParameters($params, $validParams);

        // validate fields
        if (!isset($params['id'])) {
            return false;
        }

        // check if using cache
        if (isset($this->cache)) {
            // base cache name
            $cacheId = $this->createHash('category', $params);
            if ($objCache = $this->getCache($cacheId, $params)) {
                return $objCache->data;
            }
        }

        $category = $this->fetcher->fetchCategory($params);

        // Set cache
        if (isset($this->cache) && $category) {
            $this->cache->set($cacheId, $category);
        }

        return $category;
    }

    /**
     * Search cities
     */
    public function fetchCities($params)
    {

        // filter allowed parameters
        $validParams = array(
            'orderby', 'order', 'limit', 'offset',
            'country', 'tickets', 'fields', 'categoryId'
        );

        $params = $this->validateParameters($params, $validParams);

        // check if using cache
        if (isset($this->cache)) {
            $cacheId = $this->createHash('cities', $params);
            if ($objCache = $this->getCache($cacheId, $params)) {
                return $objCache->data;
            }
        }

        $cities = $this->fetcher->fetchCities($params);

        // Set cache
        if (isset($this->cache) && $cities) {
            $this->cache->set($cacheId, $cities);
        }

        return $cities;
    }

    /**
     * Get a city
     */
    public function fetchCity($params)
    {
        // filter allowed parameters
        $validParams = array(
            'id', 'orderby', 'order', 'limit',
            'offset', 'country', 'tickets', 'fields'
        );

        $params = $this->validateParameters($params, $validParams);

        // validate fields
        if (!isset($params['id'])) {
            return false;
        }

        // check if using cache
        if (isset($this->cache)) {
            $cacheId = $this->createHash('city', $params);
            if ($objCache = $this->getCache($cacheId, $params)) {
                return $objCache->data;
            }
        }

        $city = $this->fetcher->fetchCity($params);

        // Set cache
        if (isset($this->cache) && $city) {
            $this->cache->set($cacheId, $city);
        }

        return $city;
    }

    /**
     * Search highlights
     */
    public function fetchHighlights($params)
    {
        // filter allowed parameters
        $validParams = array(
            'orderby', 'order', 'limit', 'offset',
            'country', 'tickets', 'fields', 'cityId'
        );

        $params = $this->validateParameters($params, $validParams);

        // check if using cache
        if (isset($this->cache)) {
            $cacheId = 'stub_highlights';
            $cacheId = $this->createHash('highlights', $params);

            if ($objCache = $this->getCache($cacheId, $params)) {
                return $objCache->data;
            }
        }

        $highlights = $this->fetcher->fetchHighlights($params);

        // Set cache
        if (isset($this->cache) && $highlights) {
            $this->cache->set($cacheId, $highlights);
        }

        return $highlights;
    }


    /********************************************************************
     *
     * HELPER FUNCTIONS
     *
     * *******************************************************************/
        
    /**
     * Get content from cache and validate if it's expired
     * @return [type] [description]
     */
    private function getCache($cacheId, $params)
    {
        // check if using cache
        if (!isset($this->cache)) {
            return false;
        }

        if ($objCache = $this->cache->get($cacheId)) {
            // validate if must clear all caches, this happen when
            // a new xml is available

            if ($objCache->created <= $this->fetcher->fetchXMLCreation()) {
                $this->cache->clearAll('stub_');
                return false;
            }

            if (time() < $objCache->expire) {
                return $objCache;
            }
        }

        return false;
    }


    /**
     * Create a hash string from array params
     * @param  array $params
     * @return string
     */
    private function createHash($string, $params = array())
    {
        $query = null;

        if ($string) {
            $query .= $string;
        }

        if ($params && is_array($params)) {
            $query .= http_build_query($params);
        }

        if ($query) {
            $hash = 'stub_' . hash('fnv1a64', $query);
            return $hash;
        }

        return false;
    }

    /**
     * Validate paramenters
     */
    private function validateParameters($params, $validParams)
    {
        if (isset($params['limit']) && !is_numeric($params['limit'])) {
            unset($params['limit']);
        }

        if (isset($params['offset']) && !is_numeric($params['offset'])) {
            unset($params['offset']);
        }

        foreach ($params as $key=>$value) {
            if (!in_array($key, $validParams)) {
                unset($params[$key]);
            }
        }

        return $params;
    }
}
