<?php

namespace evandroaugusto\StubHub;


class StubHubXMLReader
{
    const filePath = '';

    private $reader;

    private $filePath;
    private $keys   = array(); // support variable
    private $output = array();

    
    public function __construct($filePath='')
    {
        if (!$filePath) {
            return false;
        }

        $this->filePath = $filePath;

        if (!strpos($filePath, '.xml')) {
            throw new \Exception('You must specify a xml file name');
        }

        // load file
        $this->reader = new \XMLReader();
        $this->reader->open($filePath);
    }

    /**
     * Run XML reader
     *
     * Open XML file, organize content, create IDs and categories
     * (based on file content) to be found by API
     */
    public function run($method, $params = array())
    {
        // check reader is set
        if (!$this->reader) {
            return [];
        }

        try {
            $method = $this->call($method);

            // start reading
            while ($this->reader->read()) {
                if ($this->reader->nodeType == \XMLReader::ELEMENT && $this->reader->name == 'product') {
                    $doc = new \DOMDocument('1.0', 'UTF-8');
                                    
                    $simpleXml = simplexml_import_dom($doc->importNode($this->reader->expand(), true));

                    // filter results by country
                    if ($this->filterByCountry($simpleXml, $params)) {
                        continue;
                    }

                    // prepare the product object
                    $xml = $this->prepareProductObject($simpleXml);

                    if ($this->filterByAvailableTickets($xml, $params)) {
                        continue;
                    }

                    // create attributes and generate IDs based on title
                    $xml->categoryId   = hash('fnv1a64', $xml->category);
                    $xml->city 		 	   = (string) $this->fixCityName($xml->city);
                    $xml->cityId			 = hash('fnv1a64', $xml->city);

                    // create an event ID
                    $eventId = (string) $xml->name . (string) $xml->date;
                    $xml->id = hash('fnv1a64', $eventId);

                    // call method
                    $this->$method($xml, $params);
                }
            }

            return $this->output;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate if method exist
     */
    private function call($method)
    {
        if (method_exists($this, $method)) {
            return $method;
        } else {
            throw new \Exception('Method '. $method . ' dont exist');
        }
    }

    /**************************************************************
     *
     * Getters and Setters
     *
     *************************************************************/

    public function getFilePath()
    {
        return $this->filePath;
    }


    /**************************************************************
     *
     * FETCH DATA
     * Prepare data to return based on api
     *
     *************************************************************/

    /**
     * Fetch events
     *
     * Load all events from XML
     */
    private function fetchEvents($xml, $params)
    {
        $json = json_encode($xml);
        $json_decode = json_decode($json, true);

        // filter by category
        if (isset($params['categoryId']) && $params['categoryId'] != $xml->categoryId) {
            return false;
        }

        // filter by city
        if (isset($params['cityId']) && $params['cityId'] != $xml->cityId) {
            return false;
        }

        $this->output[] = $json_decode;
        return true;
    }

    /**
     * Fetch an event
     *
     * Fetch single event from XML
     */
    private function fetchEvent($xml, $params)
    {
        // filter by event
        if (isset($params['id']) && $params['id'] != $xml->id) {
            return false;
        }

        $json = json_encode($xml);
        $json_decode = json_decode($json, true);

        $this->output = $json_decode;
        return true;
    }

    /**
     * Fetch categories
     */
    private function fetchCategories($xml, $params)
    {
        // filter by city
        if (isset($params['cityId']) && $params['cityId'] != $xml->cityId) {
            return false;
        }
                
        $json 			 = json_encode($xml);
        $json_decode = json_decode($json, true);


        // Extract unique categories from XML file
        $catKey = array_search($xml->category, $this->keys);
        
        if ($catKey === false) {
            $n = array_push($this->keys, $xml->category);
            $catKey = $n -1;
            $this->output[$catKey] = array(
                'name'  => $xml->category,
                'id'    => $xml->categoryId,
                'count' => 1,
                'minPrice' => $xml->price
            );
        } else {
            $this->output[$catKey]['count']++;

            // get the lowest price from category
            if ($xml->price < $this->output[$catKey]['minPrice']) {
                $this->output[$catKey]['minPrice'] = $xml->price;
            }
        }

        // get fields to show
        $fields = array();
        if (isset($params['fields'])) {
            $fields = explode(',', $params['fields']);
        }

        // get only next event from city
        if (in_array('next_event', $fields) && !$this->output[$catKey]['next_event']) {
            $this->output[$catKey]['next_event'] = $json_decode;
            return true;
        }

        // get only next event from city
        if (in_array('events', $fields)) {
            $this->output[$catKey]['events'][] = $json_decode;
            return true;
        }
    }

    /**
     * Fetch a category
     *
     * Fetch content from category extracted from XML
     * The category ID is generated with a hash function based on category name
     */
    private function fetchCategory($xml, $params)
    {
        // filter by category
        if (isset($params['id']) && $params['id'] != $xml->categoryId) {
            return false;
        }

        $json 			 = json_encode($xml);
        $json_decode = json_decode($json, true);

        // search by category id
        $catKey = array_search($xml->category, $this->keys);
        if ($catKey === false) {
            $n = array_push($this->keys, $xml->category);
            $catKey = $n -1;
            
            $this->output = array(
                'name'  => $xml->category,
                'image' => $xml->image,
                'id'    => $xml->categoryId,
                'count' => 1,
                'minPrice' => $xml->price
            );
        } else {
            $this->output['count']++;

            // get the lowest price
            if ($xml->price < $this->output['minPrice']) {
                $this->output['minPrice'] = $xml->price;
            }
        }

        // get fields to show
        $fields = array();
        if (isset($params['fields'])) {
            $fields = explode(',', $params['fields']);
        }

        // get only next event from city
        if (in_array('next_event', $fields) && !$this->output['next_event']) {
            $this->output['next_event'] = $json_decode;
            return true;
        }

        // get only next event from city
        if (in_array('events', $fields)) {
            $this->output['events'][] = $json_decode;
            return true;
        }
    }

    /**
     * Fetch cities
     */
    private function fetchCities($xml, $params)
    {
        // filter by category
        if (isset($params['categoryId']) && $params['categoryId'] != $xml->categoryId) {
            return false;
        }

        $json = json_encode($xml);
        $json_decode = json_decode($json, true);

        // extract unique cities from XML
        $cityKey = array_search($xml->city, $this->keys);
        if ($cityKey === false) {
            $n = array_push($this->keys, $xml->city);
            $cityKey = $n -1;

            $this->output[$cityKey] = array(
                'id' => $xml->cityId,
                'name' => (string) $xml->city,
                'count' => 1
            );
        } else {
            // get total number of events
            $this->output[$cityKey]['count']++;
        }

        // get fields to show
        $fields = array();
        if (isset($params['fields'])) {
            $fields = explode(',', $params['fields']);
        }

        // get only next event from city
        if (in_array('next_event', $fields) && !$this->output[$cityKey]['next_event']) {
            $this->output[$cityKey]['next_event'] = $json_decode;
            return true;
        }

        // get only next event from city
        if (in_array('events', $fields)) {
            $this->output[$cityKey]['events'][] = $json_decode;
            return true;
        }
    }

    /**
     * Fetch a city
     */
    private function fetchCity($xml, $params)
    {
        if (isset($params['id']) && $params['id'] != $xml->cityId) {
            return false;
        }

        $this->fetchCities($xml, $params);
    }

    /**
     * Fetch highlights
     *
     * The highlight events are set based on data that helps to increase relevance
     */
    private function fetchHighlights($xml, $params)
    {
        // filter by category
        if (isset($params['cityId']) && $params['cityId'] != $xml->cityId) {
            return false;
        }

        // only nodes with custom images
        $image = explode('/', $xml->image);
        $image = end($image);

        // only get events with custom images (custom images usually as more than 7 characters)
        if (strlen($image) < 7) {
            return false;
        }

        // prepare node result
        $json 			 = json_encode($xml);
        $json_decode = json_decode($json, true);

        $catKey = array_search($xml->category, $this->keys);
        if ($catKey === false) {
            $n = array_push($this->keys, $xml->category);
            $catKey = $n -1;

            $this->output[$catKey] = $json_decode;
            $this->output[$catKey]['minPrice'] = $xml->price;
        } else {
            if ($this->output[$catKey]['minPrice'] == 0 && $xml->price != 0) {
                $this->output[$catKey]['minPrice'] = $xml->price;
            }

            if ($xml->price < $this->output[$catKey]['minPrice'] && $xml->price != 0) {
                $this->output[$catKey]['minPrice'] = $xml->price;
            }
        }
    }


    /**************************************************************
     *
     * HELPER METHODS
     *
     *************************************************************/

    /**
     * Filter results by country
     *
     * Check if current result must be filtered (removed from results)
     */
    private function filterByCountry(&$xml, $params = array())
    {
        // filter by country
        if (isset($params['country'])) {
            $countryQuery = $this->clearString((string)$xml->country);
            $getQuery 		= $this->clearString($params['country']);

            if (!stristr($countryQuery, $getQuery)) {
                return true;
            }
        } else {
            // brazil results by default
            if ((string)$xml->country != 'Brasil') {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter results by country
     *
     * Check if current result must be filtered (removed from results)
     */
    private function filterByAvailableTickets(&$xml, $params = array())
    {
        // filter events with tickets
        if (isset($params['tickets'])) {
            if ($params['tickets'] == 1 && $xml->price == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a product object
     */
    private function prepareProductObject($simpleXml)
    {
        $xml = new \StdClass();

        // prepare attributes
        $xml->price  			  = (float)  $simpleXml->price;
        $xml->name     	    = (string) $simpleXml->name;
        $xml->description   = (string) $simpleXml->description;
        $xml->image 			  = (string) $simpleXml->image;
        $xml->url 				  = (string) $simpleXml->url;
        $xml->currency 		  = (string) $simpleXml->currency;
        $xml->category 	    = (string) $simpleXml->category;
        $xml->country 		  = (string) $simpleXml->country;
        $xml->stock         = (string) $simpleXml->Stock;
        $xml->city    	    = (string) $simpleXml->city;
        $xml->address 		  = (string) $simpleXml->address;
        $xml->date 				  = (string) $simpleXml->date;
            
        return $xml;
    }

    /**
     * Clear string from special characters
     */
    private function clearString($input)
    {
        $search 			= array(' ', 'á', 'â', 'ã', 'é', 'ê', 'í', 'ó', 'õ', 'ô', 'ú', 'ç');
        $replace 			=  array('-', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c');

        return str_replace($search, $replace, $input);
    }

    /**
     * Fix city names
     *
     * Try to fix city names returned from XML file
     */
    private function fixCityName($input)
    {
        $search = array(
            'Sao Paulo',
            'Brasilia',
            'Belem',
            'Goiania',
            'Sao Luis',
            'Sao Goncalo',
            'Maceio',
            'Santo Andre',
            'Cuiaba',
            'Florianopolis'
        );

        $replace = array(
            'São Paulo',
            'Brasília',
            'Belém',
            'Goiânia',
            'São Luís',
            'São Gonçalo',
            'Maceió',
            'Santo André',
            'Cuiabá',
            'Florianópolis'
        );

        // fix cities with special characters
        return str_replace($search, $replace, $input);
    }
}
