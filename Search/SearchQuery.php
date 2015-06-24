<?php

/*
 * This file is part of the MassiveSearchBundle
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Massive\Bundle\SearchBundle\Search;

/**
 * Represents a search query with contextual information.
 */
class SearchQuery
{
    /**
     * @var SearchManagerInterface
     */
    private $searchManager;

    /**
     * @var string
     */
    private $queryString;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var array
     */
    private $categories = array();

    /**
     * @var array
     */
    private $indexes = array();

    public function __construct($queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * Return the query string.
     *
     * @return string
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * Return the locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the locale.
     *
     * @param string
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Return the categories to search in.
     *
     * @return array
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Set the categories to search in.
     *
     * @param array
     */
    public function setCategories($categories)
    {
        $this->categories = $categories;
    }

    /**
     * Return the indexes to search in.
     *
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * Set the indexes to search in.
     *
     * @param array
     */
    public function setIndexes($indexes)
    {
        $this->indexes = $indexes;
    }
}
