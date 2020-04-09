<?php

namespace evandroaugusto\StubHub;

use evandroaugusto\StubHub\StubHubXMLReader;


class StubHubRepository
{
    private $db;


    public function __construct(StubHubXMLReader $xml)
    {
        $this->db = $xml;
    }

    /**
     * Get XML Creation date
     */
    public function fetchXMLCreation()
    {
        $filePath = $this->db->getFilePath();

        return filemtime($filePath);
    }

    /**
     * Fetch all events
     */
    public function fetchEvents($params)
    {
        $events = $this->db->run('fetchEvents', $params);

        // orderby filter
        if (isset($params['orderby'])) {
            $events = $this->orderbyFilter($events, $params);
        }

        // pagination filter
        if (isset($params['limit']) || isset($params['offset'])) {
            $events = $this->paginationFilter($events, $params);
        }

        return $events;
    }

    /**
     * Fetch a single event
     */
    public function fetchEvent($params)
    {
        $events = $this->db->run('fetchEvent', $params);

        return $events;
    }

    /**
     * Fetch categories
     */
    public function fetchCategories($params)
    {
        $categories = $this->db->run('fetchCategories', $params);

        // orderby filter
        if (isset($params['orderby'])) {
            $categories = $this->orderbyFilter($categories, $params);
        }

        // pagination filter
        if (isset($params['limit']) || isset($params['offset'])) {
            $categories = $this->paginationFilter($categories, $params);
        }

        return $categories;
    }

    /**
     * Fetch category
     */
    public function fetchCategory($params)
    {
        return $this->db->run('fetchCategory', $params);
    }

    /**
     * Fetch cities
     */
    public function fetchCities($params)
    {
        $cities = $this->db->run('fetchCities', $params);

        // orderby filter
        if (isset($params['orderby'])) {
            $cities = $this->orderbyFilter($cities, $params);
        }

        // pagination filter
        if (isset($params['limit']) || isset($params['offset'])) {
            $cities = $this->paginationFilter($cities, $params);
        }

        return $cities;
    }

    /**
     * Fetch city
     */
    public function fetchCity($params)
    {
        return $this->db->run('fetchCity', $params);
    }

    /**
     * Fetch highlight
     */
    public function fetchHighlights($params)
    {
        $highlights = $this->db->run('fetchHighlights', $params);

        // orderby filter
        if (isset($params['orderby'])) {
            $highlights = $this->orderbyFilter($highlights, $params);
        }

        // pagination filter
        if (isset($params['limit']) || isset($params['offset'])) {
            $highlights = $this->paginationFilter($highlights, $params);
        }

        return $highlights;
    }
    

    /***************************************
     *
     * Filter functions
     *
     ****************************************/
    

    /**
     * Filter results with pagination
     */
    private function paginationFilter($results, $params)
    {
        if (!is_array($results)) {
            return $results;
        }

        $limit  = (isset($params['limit'])) ? $params['limit'] : null;
        $offset = (isset($params['offset'])) ? $params['offset'] : null;

        return array_slice($results, $offset, $limit);
    }

    /**
     * Order results
     */
    private function orderbyFilter($results, $params)
    {
        if (!is_array($results)) {
            return $results;
        }

        if (!isset($params['orderby'])) {
            return $results;
        }

        // available sorts
        $orders  = array('asc' => SORT_ASC, 'desc' => SORT_DESC);
        $orderby = $params['orderby'];

        $order = 'asc';
        if (isset($params['order'])) {
            $order = $params['order'];
        }

        return $this->arrayOrderby($results, $orderby, $orders[$order]);
    }


    /***************************************
     *
     * Helper functions
     *
     ****************************************/

    /**
     * Order a multidimensional array
     */
    private function arrayOrderby()
    {
        $args = func_get_args();
        $data = array_shift($args);

        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();

                foreach ($data as $key => $row) {
                    $tmp[$key] = $row[$field];
                }

                $args[$n] = $tmp;
            }
        }

        $args[] = &$data;
        call_user_func_array('array_multisort', $args);

        return array_pop($args);
    }
}
